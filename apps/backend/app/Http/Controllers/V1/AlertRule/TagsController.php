<?php

namespace App\Http\Controllers\V1\AlertRule;

use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Services\AlertRuleService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TagsController extends Controller
{
    public function __construct(protected AlertRuleService $alertRuleService, protected TagService $tagService) {}

    public function All()
    {

        return response()->json($this->tagService->all());
    }

    public function Create(Request $request, $id)
    {
        $alert = AlertRule::where('_id', $id)->first();
        $currentUser = Auth::user();
        if (! $this->alertRuleService->hasAdminAccessAlert($currentUser, $alert)) {
            abort(403);
        }
        $tags = $alert->tags ?? [];

        return response()->json($tags);
    }

    public function Store(Request $request, $id)
    {

        if (! empty($request->tags) && ! is_array($request->tags)) {
            abort(422);
        }
        $alert = AlertRule::where('_id', $id)->first();
        $currentUser = Auth::user();
        $access = $this->alertRuleService->hasAdminAccessAlert($currentUser, $alert);

        $tags = collect($request->tags ?? [])->map(fn ($item) => trim($item))->unique()->toArray();
        if (! $access) {
            abort(403);
        }

        $alert->tags = $tags;
        $alert->save();
        $this->tagService->flushCache();

        return response()->json(['status' => true]);

    }
}
