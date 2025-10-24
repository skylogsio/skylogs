<?php

namespace App\Http\Controllers\V1\AlertRule;

use App\Http\Controllers\Controller;
use App\Services\PrometheusInstanceService;
use Illuminate\Http\Request;

class PrometheusController extends Controller
{
    public function __construct(protected PrometheusInstanceService $prometheusInstanceService) {}

    public function Rules(Request $request)
    {
        $dataSourceId = $request->data_source_id;
        $rules = cache()->tags(['prometheus', 'rules'])->remember('prometheusLabels'.$dataSourceId, 3600, function () use ($dataSourceId) {
            return $this->prometheusInstanceService->getRules($dataSourceId);
        });

        return response()->json($rules);

    }

    public function Labels(Request $request)
    {
        $labels = cache()->tags(['prometheus', 'labels'])->remember('prometheusLabels', 3600, function () {
            return $this->prometheusInstanceService->getLabels();
        });

        return response()->json($labels);
    }

    public function LabelValues(Request $request, $label)
    {
        $labelValues = cache()->tags(['prometheus', 'labelValues', $label])->remember('prometheusLabelValues'.$label, 3600, function () use ($label) {
            return $this->prometheusInstanceService->getLabelValues($label);
        });

        return response()->json($labelValues);

    }

    public function Triggered()
    {
        $prometheusAlerts = cache()->tags(['prometheus', 'triggered'])->remember('prometheusTriggered', 5, function () {
            return $this->prometheusInstanceService->getTriggered();
        });

        return response()->json($prometheusAlerts);

    }
}
