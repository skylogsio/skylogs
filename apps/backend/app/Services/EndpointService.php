<?php

namespace App\Services;

use App\Enums\EndpointType;
use App\Enums\FlowEndpointStepType;
use App\Helpers\Call;
use App\Helpers\Email;
use App\Helpers\SMS;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class EndpointService
{
    public function __construct(
        protected TeamService $teamService,
        protected AlertRuleService $alertRuleService,
    ) {}

    public function selectableUserEndpoint(User $user, ?AlertRule $alert = null)
    {

        if ($user->isAdmin()) {
            return Cache::tags(['endpoint', 'admin'])
                ->rememberForever('endpoint:admin', fn () => Endpoint::get());
        }

        if (
            ! $alert
            || $this->alertRuleService->hasUserAccessAlert($user, $alert)
        ) {
            return $this->rememberGlobalSelectableEndpoints($user);
        }

        return collect();

    }

    public function rememberGlobalSelectableEndpoints(User $user)
    {
        return Cache::tags(['endpoint', $user->id])
            ->rememberForever("endpoint:global:$user->id", function () use ($user) {
                $teamIds = $this->userTeamIds($user);

                return Endpoint::query()
                    ->where(function ($query) use ($user, $teamIds) {
                        $query->where('userId', $user->id)
                            ->orWhereIn('accessUserIds', [$user->id]);

                        if ($teamIds !== []) {
                            $query->orWhereIn('accessTeamIds', $teamIds);
                        }
                    })
                    ->get();
            });
    }

    public function userCanUseEndpoint(User $user, Endpoint $endpoint): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $userId = (string) $user->id;

        if ((string) $endpoint->userId === $userId) {
            return true;
        }

        $accessUserIds = array_map(strval(...), $endpoint->accessUserIds ?? []);
        if (in_array($userId, $accessUserIds, true)) {
            return true;
        }

        $accessTeamIds = array_map(strval(...), $endpoint->accessTeamIds ?? []);

        return array_intersect($this->userTeamIds($user), $accessTeamIds) !== [];
    }

    public function userCanRemoveAlertEndpoint(User $user, AlertRule $alert, Endpoint $endpoint): bool
    {
        if ($user->isAdmin() || $this->alertRuleService->userOwnsAlert($user, $alert)) {
            return true;
        }

        return $this->userCanUseEndpoint($user, $endpoint);
    }

    /**
     * Alert endpoint payload. Destination fields stay hidden unless the user can use the endpoint.
     *
     * @return array<string, mixed>
     */
    public function presentAlertEndpoint(User $user, AlertRule $alert, Endpoint $endpoint): array
    {
        if (! $this->userCanUseEndpoint($user, $endpoint)) {
            $endpoint->makeHidden(['value', 'chatId', 'botToken', 'threadId', 'steps', 'otpCode']);
        }

        $payload = $endpoint->toArray();
        $payload['canRemove'] = $this->userCanRemoveAlertEndpoint($user, $alert, $endpoint);

        return $payload;
    }

    /**
     * Endpoint ids from $endpointIds that this user owns or can access.
     *
     * @param  list<mixed>  $endpointIds
     * @return list<string>
     */
    public function assignableEndpointIds(User $user, array $endpointIds): array
    {
        $endpointIds = array_values(array_filter(array_map(
            fn ($endpointId) => trim((string) $endpointId),
            $endpointIds,
        ), fn (string $endpointId) => $endpointId !== ''));

        if ($endpointIds === []) {
            return [];
        }

        return Endpoint::query()
            ->whereIn('_id', $endpointIds)
            ->get()
            ->filter(fn (Endpoint $endpoint) => $this->userCanUseEndpoint($user, $endpoint))
            ->map(fn (Endpoint $endpoint) => (string) $endpoint->id)
            ->values()
            ->all();
    }

    /**
     * Add endpoints the user can use. Existing alert endpoints are left in place.
     *
     * @param  list<mixed>  $endpointIds
     */
    public function attachAlertEndpoints(User $user, AlertRule $alert, array $endpointIds): void
    {
        foreach ($this->assignableEndpointIds($user, $endpointIds) as $endpointId) {
            $alert->push('endpointIds', $endpointId, true);
        }
    }

    /**
     * @return list<string>
     */
    private function userTeamIds(User $user): array
    {
        return $this->teamService->userTeams($user)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->filter(fn (string $id) => $id !== '')
            ->values()
            ->all();
    }

    public function hasActionAccess(User $user, Endpoint $endpoint)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id == $endpoint->userId) {
            return true;
        }

        return false;
    }

    public function countUserEndpointAlert(User $user, ?AlertRule $alert = null)
    {
        if ($alert === null || (! $user->isAdmin() && ! $this->alertRuleService->hasUserAccessAlert($user, $alert))) {
            return 0;
        }

        return collect($alert->endpointIds ?? [])
            ->map(fn ($endpointId) => (string) $endpointId)
            ->filter(fn (string $endpointId) => $endpointId !== '')
            ->unique()
            ->count();
    }

    public function deleteEndpointOfAlertRules(Endpoint $endpoint): void
    {
        foreach (app(AlertRuleService::class)->getAlertsDB() as $alertRule) {
            $alertRule->pull('endpointIds', $endpoint->_id);
        }
    }

    public function flushCache(): void
    {
        Cache::tags(['endpoint'])->flush();
    }

    public function ChangeOwnerAll(User $fromUser, User $toUser)
    {
        $endpoints = Endpoint::where('userId', $fromUser->id)->get();
        foreach ($endpoints as $endpoint) {
            $endpoint->userId = $toUser->id;
            $endpoint->save();
        }
    }

    public function create($request)
    {
        $value = trim($request->value);
        $isPublic = $request->boolean('isPublic', false);
        $accessUserIds = $request->accessUserIds ?? [];
        $accessTeamIds = $request->accessTeamIds ?? [];

        switch ($request->type) {
            case EndpointType::TELEGRAM->value:

                $model = Endpoint::create([
                    'userId' => \Auth::id(),
                    'name' => $request->name,
                    'type' => $request->type,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                    'chatId' => $value,
                    'threadId' => $request->threadId,
                    'botToken' => $request->botToken,
                    'isPublic' => $isPublic,
                ]);
                break;

            case EndpointType::BALE->value:

                $model = Endpoint::create([
                    'userId' => \Auth::id(),
                    'name' => $request->name,
                    'type' => $request->type,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                    'chatId' => $value,
                    'botToken' => $request->botToken,
                    'isPublic' => $isPublic,
                ]);
                break;

            case EndpointType::FLOW->value:
                $this->validateFlowEndpointData($request);

                $model = Endpoint::create([
                    'userId' => \Auth::id(),
                    'name' => $request->name,
                    'type' => $request->type,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                    'steps' => $request->steps,
                    'isPublic' => $isPublic,
                ]);
                break;

            case EndpointType::CALL->value:
            case EndpointType::SMS->value:
            case EndpointType::EMAIL->value:
                $otp = EndpointOTP::where('value', $request->value)->first();

                if (! $otp || $otp->expiredAt < Carbon::now()) {
                    abort(422, 'otp code expired try again');
                }

                if ($otp->otpCode != $request->otpCode) {
                    abort(422, 'otp code invalid');
                }
                $model = Endpoint::create([
                    'userId' => \Auth::id(),
                    'name' => $request->name,
                    'type' => $request->type,
                    'value' => $value,
                    'isPublic' => $isPublic,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                ]);

                break;
            default:
                $model = Endpoint::create([
                    'userId' => \Auth::id(),
                    'name' => $request->name,
                    'type' => $request->type,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                    'value' => $value,
                    'isPublic' => $isPublic,
                ]);
                break;
        }

        $this->syncOnCallFlag($model, $request);

        return $model->fresh();
    }

    public function update($endpoint, $request)
    {
        $value = trim($request->value);
        $isPublic = $request->boolean('isPublic', false);
        $accessUserIds = $request->accessUserIds ?? [];
        $accessTeamIds = $request->accessTeamIds ?? [];

        switch ($request->type) {
            case EndpointType::TELEGRAM->value:

                $model = $endpoint->update([
                    'name' => $request->name,
                    'type' => $request->type,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                    'chatId' => $value,
                    'threadId' => $request->threadId,
                    'botToken' => $request->botToken,
                    'isPublic' => $isPublic,
                ]);
                break;

            case EndpointType::BALE->value:

                $model = $endpoint->update([
                    'name' => $request->name,
                    'type' => $request->type,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                    'chatId' => $value,
                    'botToken' => $request->botToken,
                    'isPublic' => $isPublic,
                ]);
                break;

            case EndpointType::FLOW->value:
                $this->validateFlowEndpointData($request);

                $model = $endpoint->update([
                    'name' => $request->name,
                    'type' => $request->type,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                    'steps' => $request->steps,
                    'isPublic' => $isPublic,
                ]);
                break;

            case EndpointType::CALL->value:
            case EndpointType::SMS->value:
            case EndpointType::EMAIL->value:

                if ($endpoint->value != $request->value) {
                    $otp = EndpointOTP::where('value', $request->value)->first();

                    if (! $otp || $otp->expiredAt < Carbon::now()) {
                        abort(422, 'otp code expired try again');
                    }

                    if ($otp->otpCode != $request->otpCode) {
                        abort(422, 'otp code invalid');
                    }
                }

                $model = $endpoint->update([
                    'name' => $request->name,
                    'type' => $request->type,
                    'value' => $value,
                    'isPublic' => $isPublic,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                ]);
                break;

            default:
                $model = $endpoint->update([
                    'name' => $request->name,
                    'type' => $request->type,
                    'accessUserIds' => $accessUserIds,
                    'accessTeamIds' => $accessTeamIds,
                    'value' => $value,
                    'isPublic' => $isPublic,
                ]);
                break;
        }

        $this->syncOnCallFlag($endpoint->fresh(), $request);

        return $endpoint->fresh();
    }

    public function syncOnCallFlag(Endpoint $endpoint, $request): void
    {
        if (! $request->exists('onCall')) {
            return;
        }

        $onCall = $request->boolean('onCall');

        if ($onCall) {
            Endpoint::query()
                ->where('userId', $endpoint->userId)
                ->where('_id', '!=', $endpoint->id)
                ->update(['onCall' => false]);
        }

        $endpoint->onCall = $onCall;
        $endpoint->save();
    }

    public function validateFlowEndpointData($request): void
    {

        $steps = $request->steps;

        if (empty($steps)) {
            abort(422, 'wrong format for flow endpoints. empty steps.');
        }
        foreach ($steps as $step) {
            switch ($step['type']) {
                case FlowEndpointStepType::WAIT->value:
                    if (empty($step['timeUnit']) || ! in_array($step['timeUnit'], ['s', 'm', 'h']) || empty($step['duration']) || ! is_int($step['duration'])) {
                        abort(422, 'wrong format for flow endpoints');
                    }
                    break;
                case FlowEndpointStepType::ENDPOINT->value:
                    if (empty($step['endpointIds'])) {
                        abort(422, 'wrong format for flow endpoints');
                    }
                    break;

                default:
                    abort(422, 'wrong format for flow endpoints');
            }
        }

    }

    public function otpRequest($request)
    {
        $endpointOtp = EndpointOTP::where('type', $request->type)->where('value', $request->value)->first();

        if ($endpointOtp) {
            if (Carbon::now()->lessThan($endpointOtp->expiredAt)) {
                $seconds = intval(Carbon::now()->diffInSeconds($endpointOtp->expiredAt));

                return [
                    'message' => "You have to wait $seconds seconds before otp request.",
                    'expiredAt' => $endpointOtp->expiredAt->getTimestamp(),
                    'timeLeft' => intval(Carbon::now()->diffInSeconds($endpointOtp->expiredAt)),
                ];
                //                abort(422, "You have to wait $seconds seconds before otp request.");
            }
        } else {
            $endpointOtp = new EndpointOTP;
            $endpointOtp->type = $request->type;
            $endpointOtp->value = $request->value;
        }

        $endpointOtp->status = EndpointOTP::STATUS_PENDING;
        $endpointOtp->expiredAt = Carbon::now()->addMinutes(3);
        $endpointOtp->generateOtpCode();
        $endpointOtp->save();

        switch ($request->type) {
            case EndpointType::SMS->value:
                $endpointOtp->result = SMS::sendOTP($endpointOtp);
                break;
            case EndpointType::CALL->value:
                $endpointOtp->result = Call::sendOTP($endpointOtp);
                break;

            case EndpointType::EMAIL->value:
                Email::sendOTP($endpointOtp);
                break;
        }

        $endpointOtp->save();

        return [
            'message' => 'OTP code has been sent to your endpoint',
            'expiredAt' => $endpointOtp->expiredAt->getTimestamp(),
            'timeLeft' => intval(Carbon::now()->diffInSeconds($endpointOtp->expiredAt)),
        ];
    }
}
