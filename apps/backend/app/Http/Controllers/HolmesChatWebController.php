<?php

namespace App\Http\Controllers;

use App\Enums\Constants;
use App\Models\User;
use App\Services\HolmesGptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class HolmesChatWebController extends Controller
{
    public function __construct(protected HolmesGptService $holmesGptService) {}

    public function index(Request $request)
    {
        $user = $this->sessionUser($request);

        return view('holmes-chat.index', [
            'user' => $user,
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'Invalid username or password.',
            ]);
        }

        if (! $user->hasRole(Constants::ROLE_OWNER->value)) {
            throw ValidationException::withMessages([
                'username' => 'Owner access is required.',
            ]);
        }

        $request->session()->put('holmes_chat_user_id', $user->id);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'user' => [
                    'name' => $user->name,
                    'username' => $user->username,
                ],
            ]);
        }

        return redirect()->route('holmes-chat.index');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('holmes_chat_user_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['status' => true]);
        }

        return redirect()->route('holmes-chat.index');
    }

    public function send(Request $request)
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

    private function sessionUser(Request $request): ?User
    {
        $userId = $request->session()->get('holmes_chat_user_id');

        if (! $userId) {
            return null;
        }

        $user = User::find($userId);

        if (! $user || ! $user->hasRole(Constants::ROLE_OWNER->value)) {
            $request->session()->forget('holmes_chat_user_id');

            return null;
        }

        return $user;
    }
}
