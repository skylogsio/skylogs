<?php

namespace App\Services;

use App\Enums\EndpointType;
use App\Enums\FlowEndpointStepType;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class EndpointService
{
    public function selectableUserEndpoint(User $user, ?AlertRule $alert = null)
    {

        if ($user->isAdmin()) {
            return Cache::tags(['endpoint', 'admin'])
                ->rememberForever('endpoint:admin', fn () => Endpoint::get());
        }

        if ($alert) {
            $alertUserIds = $alert->userIds ?? [];
        } else {
            return Cache::tags(['endpoint', $user->id])
                ->rememberForever("endpoint:global:$user->id", fn () => Endpoint::where('userId', $user->_id)->orWhere('isPublic', true)->get());
        }

        if ($alert->userId == $user->_id) {
            return Cache::tags(['endpoint', $user->id])
                ->rememberForever("endpoint:global:$user->id", fn () => Endpoint::where('userId', $user->_id)->orWhere('isPublic', true)->get());
        } elseif (in_array($user->_id, $alertUserIds)) {
            return Cache::tags(['endpoint', $user->id])
                ->rememberForever("endpoint:user:$user->id", fn () => Endpoint::where('userId', $user->_id)->get());
        }

        return collect();

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

        switch ($request->type) {
            case EndpointType::TELEGRAM->value:

                $model = Endpoint::create([
                    'user_id' => \Auth::id(),
                    'userId' => \Auth::id(),
                    'name' => $request->name,
                    'type' => $request->type,
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
                    'steps' => $request->steps,
                    'isPublic' => $isPublic,
                ]);
                break;
            default:
                $model = Endpoint::create([
                    'user_id' => \Auth::id(),
                    'userId' => \Auth::id(),
                    'name' => $request->name,
                    'type' => $request->type,
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

        switch ($request->type) {
            case EndpointType::TELEGRAM->value:

                $model = $endpoint->update([
                    'name' => $request->name,
                    'type' => $request->type,
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
                    'steps' => $request->steps,
                    'isPublic' => $isPublic,
                ]);
                break;
            default:
                $model = $endpoint->update([
                    'name' => $request->name,
                    'type' => $request->type,
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
}
