<?php

namespace App\Http\Controllers\V1\Config;

use App\Http\Controllers\Controller;
use App\Services\HolmesGptService;
use Illuminate\Http\Request;

class HolmesChatController extends Controller
{
    public function __construct(protected HolmesGptService $holmesGptService) {}

    public function Chat(Request $request)
    {
        $validated = $request->validate([
            'ask' => 'required|string|max:4000',
            'conversationHistory' => 'nullable|array',
            'conversationHistory.*.role' => 'required_with:conversationHistory|string',
            'conversationHistory.*.content' => 'required_with:conversationHistory|string',
        ]);

        $result = $this->holmesGptService->chat(
            $validated['ask'],
            $validated['conversationHistory'] ?? []
        );

        return response()->json($result);
    }
}
