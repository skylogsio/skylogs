<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class HorizonBasicAuthMiddleware
{
    /**
     * Authenticate the Horizon dashboard via HTTP basic auth against the admin user.
     *
     * The authenticated user is set on the default guard so the `viewHorizon` gate can resolve it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = $request->getUser();
        $password = $request->getPassword();

        $admin = null;

        if ($username === 'admin' && filled($password)) {
            $admin = User::query()->where('username', $username)->first();
        }

        if ($admin === null || ! Hash::check($password, $admin->password)) {
            return response()->make('Invalid credentials.', 401, ['WWW-Authenticate' => 'Basic']);
        }

        Auth::setUser($admin);

        return $next($request);
    }
}
