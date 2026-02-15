<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\SocialAccount;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{

    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();

        return ResponseFormatter::success([
            'url' => $url,
        ], 'OAuth redirect URL generated');
    }

    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            return $this->handleSocialUser($provider, $socialUser);
        } catch (Exception $error) {
            return ResponseFormatter::error(
                null,
                'OAuth callback failed: ' . $error->getMessage(),
                500
            );
        }
    }

    public function loginWithToken(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        $request->validate([
            'access_token' => 'required|string',
        ]);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->userFromToken(
                $request->access_token
            );

            return $this->handleSocialUser($provider, $socialUser);
        } catch (Exception $error) {
            return ResponseFormatter::error(
                null,
                'OAuth token login failed: ' . $error->getMessage(),
                500
            );
        }
    }

    private function handleSocialUser(string $provider, $socialUser)
    {
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user;
        } else {
            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'role' => 'employee',
                    'is_active' => true,
                ]);
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ]);
        }

        if (!$user->is_active) {
            return ResponseFormatter::error(
                null,
                'Account is deactivated',
                403
            );
        }

        $token = $user->createToken('authToken')->plainTextToken;

        return ResponseFormatter::success([
            'user' => new UserResource($user),
            'token_type' => 'Bearer',
            'access_token' => $token,
        ], 'OAuth Login Successful', 200);
    }

    private function validateProvider(string $provider)
    {
        $supported = ['google'];

        if (!in_array($provider, $supported)) {
            abort(response()->json([
                'meta' => [
                    'code' => 400,
                    'status' => 'error',
                    'message' => "Provider '{$provider}' is not supported"
                ],
                'data' => null
            ], 400));
        }
    }
}
