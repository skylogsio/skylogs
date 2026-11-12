<?php

namespace App\Http\Controllers\V1\AlertRule;

use App\Enums\NotificationDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AlertRule\IndexHistoryFailureRequest;
use App\Http\Resources\AlertRule\HistoryFailureResource;
use App\Models\AlertRule;
use App\Models\NotificationDelivery;
use App\Services\AlertRuleService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class HistoryFailureController extends Controller
{
    public function __construct(private readonly AlertRuleService $alertRuleService) {}

    public function index(IndexHistoryFailureRequest $request): AnonymousResourceCollection
    {
        $alert = AlertRule::query()->where('_id', $request->validated('id'))->firstOrFail();

        abort_unless($this->alertRuleService->hasReadAccessAlert(Auth::user(), $alert), 403);

        $history = $this->alertRuleService->historyQuery($alert)
            ->where('_id', $request->validated('historyId'))
            ->firstOrFail();

        $failures = NotificationDelivery::query()
            ->where('historyId', (string) $history->getKey())
            ->where('status', NotificationDeliveryStatus::FAILED->value)
            ->get(['endpointId', 'endpointName', 'endpointType', 'lastError']);

        return HistoryFailureResource::collection($failures);
    }
}
