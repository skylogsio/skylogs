<?php

namespace App\Services;

use App\Enums\EndpointType;
use App\Enums\FlowEndpointStepType;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Models\User;
use App\Services\Notification\ChannelRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class EndpointService
{
    public function __construct(
        protected TeamService $teamService,
        protected AlertRuleService $alertRuleService,
        protected ChannelRegistry $channels,
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
        return $user->isAdmin() || $this->isEndpointSharedWithUser($user, $endpoint);
    }

    /**
     * Whether the user owns the endpoint or it is shared with them or one of
     * their teams, ignoring the admin override.
     */
    public function isEndpointSharedWithUser(User $user, Endpoint $endpoint): bool
    {
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
        $endpointIds = $this->normalizeEndpointIds($endpointIds);

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
     * Make the alert endpoints this user owns or has been shared match
     * $endpointIds. Other users' endpoints stay on the alert untouched, even
     * for admins.
     *
     * @param  list<mixed>  $endpointIds
     */
    public function syncAlertEndpoints(User $user, AlertRule $alert, array $endpointIds): void
    {
        $requestedEndpointIds = $this->normalizeEndpointIds($endpointIds);
        $currentEndpointIds = array_map(strval(...), $alert->endpointIds ?? []);

        $deselectedEndpointIds = Endpoint::query()
            ->whereIn('_id', array_values(array_diff($currentEndpointIds, $requestedEndpointIds)))
            ->get()
            ->filter(fn (Endpoint $endpoint) => $this->isEndpointSharedWithUser($user, $endpoint))
            ->map(fn (Endpoint $endpoint) => (string) $endpoint->id);

        foreach ($deselectedEndpointIds as $endpointId) {
            $alert->pull('endpointIds', $endpointId);
        }

        $this->attachAlertEndpoints($user, $alert, $requestedEndpointIds);
    }

    /**
     * @param  list<mixed>  $endpointIds
     * @return list<string>
     */
    private function normalizeEndpointIds(array $endpointIds): array
    {
        return collect($endpointIds)
            ->filter(fn ($endpointId) => is_string($endpointId))
            ->map(fn (string $endpointId) => trim($endpointId))
            ->filter(fn (string $endpointId) => $endpointId !== '')
            ->unique()
            ->values()
            ->all();
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
        $selectableEndpoints = $this->selectableUserEndpoint($user, $alert);
        $alertEndpoints = collect($alert->endpointIds);

        return $selectableEndpoints->pluck('id')->intersect($alertEndpoints)->count();
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

    /**
     * @param  array<string, mixed>  $data  validated endpoint input
     */
    public function create(array $data): Endpoint
    {
        $this->assertCanWrite($data);

        $model = Endpoint::create([
            'userId' => \Auth::id(),
            ...$this->attributesFor($data),
        ]);

        $this->syncOnCallFlag($model, $data);

        return $model->fresh();
    }

    /**
     * @param  array<string, mixed>  $data  validated endpoint input
     */
    public function update(Endpoint $endpoint, array $data): Endpoint
    {
        $this->assertCanWrite($data, $endpoint);

        $endpoint->update($this->attributesFor($data));

        $this->syncOnCallFlag($endpoint->fresh(), $data);

        return $endpoint->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function syncOnCallFlag(Endpoint $endpoint, array $data): void
    {
        if (! array_key_exists('onCall', $data)) {
            return;
        }

        $onCall = (bool) $data['onCall'];

        if ($onCall) {
            Endpoint::query()
                ->where('userId', $endpoint->userId)
                ->where('_id', '!=', $endpoint->id)
                ->update(['onCall' => false]);
        }

        $endpoint->onCall = $onCall;
        $endpoint->save();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $steps
     */
    public function validateFlowEndpointData(?array $steps): void
    {
        if (empty($steps)) {
            abort(422, 'wrong format for flow endpoints. empty steps.');
        }
        foreach ($steps as $step) {
            switch ($step['type'] ?? null) {
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

    /**
     * @param  array{type: string, value: mixed}  $data
     * @return array{message: string, expiredAt: int, timeLeft: int}
     */
    public function otpRequest(array $data): array
    {
        $endpointOtp = EndpointOTP::where('type', $data['type'])->where('value', $data['value'])->first();

        if ($endpointOtp) {
            if (Carbon::now()->lessThan($endpointOtp->expiredAt)) {
                $seconds = intval(Carbon::now()->diffInSeconds($endpointOtp->expiredAt));

                return [
                    'message' => "You have to wait $seconds seconds before otp request.",
                    'expiredAt' => $endpointOtp->expiredAt->getTimestamp(),
                    'timeLeft' => intval(Carbon::now()->diffInSeconds($endpointOtp->expiredAt)),
                ];
            }
        } else {
            $endpointOtp = new EndpointOTP;
            $endpointOtp->type = $data['type'];
            $endpointOtp->value = $data['value'];
        }

        $endpointOtp->status = EndpointOTP::STATUS_PENDING;
        $endpointOtp->expiredAt = Carbon::now()->addMinutes(3);
        $endpointOtp->generateOtpCode();
        $endpointOtp->save();

        $endpointOtp->result = $this->channels->for($data['type'])->sendVerification($endpointOtp)->toArray();
        $endpointOtp->save();

        return [
            'message' => 'OTP code has been sent to your endpoint',
            'expiredAt' => $endpointOtp->expiredAt->getTimestamp(),
            'timeLeft' => intval(Carbon::now()->diffInSeconds($endpointOtp->expiredAt)),
        ];
    }

    /**
     * Flow steps must be well formed, and channels that verify ownership
     * need a valid OTP whenever the address is new.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertCanWrite(array $data, ?Endpoint $existing = null): void
    {
        if ($data['type'] === EndpointType::FLOW->value) {
            $this->validateFlowEndpointData($data['steps'] ?? null);

            return;
        }

        if (! $this->channels->for($data['type'])->requiresVerification()) {
            return;
        }

        if ($existing !== null && $existing->value == ($data['value'] ?? null)) {
            return;
        }

        $otp = EndpointOTP::where('value', $data['value'] ?? null)->first();

        if (! $otp || $otp->expiredAt < Carbon::now()) {
            abort(422, 'otp code expired try again');
        }

        if ($otp->otpCode != ($data['otpCode'] ?? null)) {
            abort(422, 'otp code invalid');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFor(array $data): array
    {
        $type = $data['type'];

        return [
            'name' => $data['name'],
            'type' => $type,
            'accessUserIds' => $data['accessUserIds'] ?? [],
            'accessTeamIds' => $data['accessTeamIds'] ?? [],
            'isPublic' => (bool) ($data['isPublic'] ?? false),
            ...($type === EndpointType::FLOW->value
                ? ['steps' => $data['steps']]
                : $this->channels->for($type)->attributes($data)),
        ];
    }
}
