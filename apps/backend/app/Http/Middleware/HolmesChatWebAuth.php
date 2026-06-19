<?php

namespace App\Http\Middleware;

use App\Enums\Constants;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HolmesChatWebAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get('holmes_chat_user_id');

        if (! $userId) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('holmes-chat.index');
        }

        $user = User::find($userId);

        if (! $user || ! $user->hasRole(Constants::ROLE_OWNER->value)) {
            $request->session()->forget('holmes_chat_user_id');

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            return redirect()->route('holmes-chat.index');
        }

        $request->attributes->set('holmesChatUser', $user);

        return $next($request);
    }
}
