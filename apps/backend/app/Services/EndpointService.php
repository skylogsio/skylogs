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
    public function __construct(protected TeamService $teamService) {}

    public function selectableUserEndpoint(User $user, ?AlertRule $alert = null)
    {

        if ($user->isAdmin()) {
            return Cache::tags(['endpoint', 'admin'])
                ->rememberForever('endpoint:admin', fn () => Endpoint::get());
        }

        if (! $alert || $alert->userId == $user->_id) {
            return Cache::tags(['endpoint', $user->id])
                ->rememberForever("endpoint:global:$user->id", function () use ($user) {
                    $teamIds = $this->teamService->userTeams($user)->pluck('id')->toArray();

                    return Endpoint::where('userId', $user->_id)
                        ->orWhereIn('accessUserIds', [$user->id])
                        ->orWhereIn('accessTeamIds', $teamIds)
                        ->get();
                });
        } elseif (in_array($user->_id, $alert->userIds)) {
            return Cache::tags(['endpoint', $user->id])
                ->rememberForever("endpoint:user:$user->id", fn () => Endpoint::where('userId', $user->_id)->get());
        }

        return collect();

    }

    public function hasActionAccess(User $user, Endpoint $endpoint)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->_id == $endpoint->userId) {
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
            $endpoint->user_id = $toUser->id;
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
                    'user_id' => \Auth::id(),
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

            case EndpointType::FLOW->value:
                $this->validateFlowEndpointData($request);

                $model = Endpoint::create([
                    'user_id' => \Auth::id(),
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
                    'user_id' => \Auth::id(),
                    'userId' => \Auth::id(),
                    'name' => $request->name,
                    'type' => $request->type,
                    'value' => $value,
                    'isPublic' => $isPublic,
                ]);

                break;
            default:
                $model = Endpoint::create([
                    'user_id' => \Auth::id(),
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

        return $model;
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

        return $model;
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
