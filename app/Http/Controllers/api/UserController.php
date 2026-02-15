<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function register(RegisterUserRequest $request)
    {
        try {
            $data = $request->validated();
            $user = User::create($data);
            $token = $user->createToken('authToken')->plainTextToken;

            return ResponseFormatter::success([
                'user' => new UserResource($user),
                'token_type' => 'Bearer',
                'access_token' => $token,
            ], 'User Registered', 201);
        } catch (Exception $error) {
            return ResponseFormatter::error(
                null,
                'Registration failed: ' . $error->getMessage(),
                500
            );
        }
    }

    public function login(LoginUserRequest $request)
    {
        try {
            $data = $request->validated();
            $user = User::where('email', $data['email'])->first();
            if (!$user || !Hash::check($data['password'], $user->password)) {
                return ResponseFormatter::error(
                    null,
                    'Invalid credentials',
                    401
                );
            }
            $token = $user->createToken('authToken')->plainTextToken;
            return ResponseFormatter::success([
                'user' => new UserResource($user),
                'token_type' => 'Bearer',
                'access_token' => $token,
            ], 'User Logged In', 201);
        } catch (Exception $error) {
            return ResponseFormatter::error(
                null,
                'Login failed: ' . $error->getMessage(),
                500
            );
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return ResponseFormatter::success(
                null,
                'User Logged Out',
                201
            );
        } catch (Exception $error) {
            return ResponseFormatter::error(
                null,
                'Logout failed: ' . $error->getMessage(),
                500
            );
        }
    }
}
