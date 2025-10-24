<?php

namespace App\Http\Controllers\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\ApiService;
use Illuminate\Http\Request;

class ApiAlertController extends Controller
{
    public function __construct(protected ApiService $apiService) {}

    public function FireAlert(Request $request)
    {
        if (! $request->has('instance')) {
            abort(404, 'instance is required');
        }
        $post = $request->all();

        return $this->apiService->fireAlert($post);
    }

    public function ResolveAlert(Request $request)
    {
        if (! $request->has('instance')) {
            abort(404, 'instance is required');
        }
        $post = $request->all();

        return $this->apiService->resolveAlert($post);
    }

    public function StatusAlert(Request $request)
    {
        if (! $request->has('instance')) {
            abort(404, 'instance is required');
        }
        $post = $request->all();

        return $this->apiService->statusAlert($post);
    }

    public function NotificationAlert(Request $request)
    {
        if (! $request->has('instance')) {
            abort(404, 'instance is required');
        }
        $post = $request->all();

        return $this->apiService->notificationAlert($post);
    }
}
