<?php

namespace App\Http\Controllers\V1;

use App\Enums\Constants;
use App\Http\Controllers\Controller;
use App\Models\User;
use Hash;
use Illuminate\Http\Request;
use Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('username', 'password');

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $refreshToken = auth()->claims(['refresh' => true])
            ->setTTL(config('jwt.refresh_ttl'))
            ->tokenById(auth()->id());

        return $this->respondWithTokens($token, $refreshToken);
    }

    public function refresh(Request $request)
    {
        try {
            $refreshToken = $request->header('Authorization');
            $refreshToken = str_replace('Bearer ', '', $refreshToken);

            $payload = auth()->setToken($refreshToken)->getPayload();

            if (! $payload->get('refresh')) {
                return response()->json(['message' => 'Invalid refresh token'], 401);
            }

            $newAccessToken = auth()->tokenById($payload->get('sub'));
            $newRefreshToken = auth()->claims(['refresh' => true])
                ->setTTL(config('jwt.refresh_ttl'))
                ->tokenById($payload->get('sub'));

            return $this->respondWithTokens($newAccessToken, $newRefreshToken);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }
    }

    protected function respondWithTokens($accessToken, $refreshToken)
    {
        return response()->json([
            'access_token' => $accessToken,
            'accessToken' => $accessToken,
            'refresh_token' => $refreshToken,
            'refreshToken' => $refreshToken,
            'token_type' => 'bearer',
            'tokenType' => 'bearer',
            'roles' => auth()->user()->roles->pluck('name')->toArray(),
            'expires_in' => config('jwt.ttl') * 60,
            'expiresIn' => config('jwt.ttl') * 60,
            'refresh_expires_in' => config('jwt.refresh_ttl') * 60,
            'refreshExpiresIn' => config('jwt.refresh_ttl') * 60,
        ]);
    }

    public function me()
    {

        $user = auth()->user();
        $result = $user->toArray();
        $result['roles'] = $user->roles->pluck('name')->toArray();
        $result['permissions'] = $user->permissions->pluck('name')->toArray();

        return response()->json($result);
    }


    public function ChangePassword(Request $request)
    {
        Validator::validate(
            $request->all(),
            [
                'currentPassword' => 'required',
                'newPassword' => 'required',
                'confirmPassword' => 'required|same:newPassword',
            ],
        );

        $model = User::where('_id', auth()->user()->id)->firstOrFail();

        if (Hash::check($request->currentPassword, $model->password)) {

            $model->update([
                'password' => Hash::make($request->post('confirmPassword')),
            ]);

            return response()->json([
                'status' => true,
                'data' => $model,
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => "Current password is incorrect",
        ]);

    }


    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }
}
