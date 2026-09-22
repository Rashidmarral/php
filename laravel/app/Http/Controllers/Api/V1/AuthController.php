<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Token-based API auth (Sanctum personal access tokens) — separate from the session-based
 * web login. Built as the foundation for the future mobile app and third-party integrations;
 * device name lets a user issue/revoke tokens per device from Settings > API tokens.
 */
class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', strtolower(trim($credentials['email'])))->first();
        if (!$user || !Hash::check($credentials['password'], $user->password) || $user->status !== 'active') {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'api-client')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    private function userPayload(User $user): array
    {
        $company = Company::find($user->company_id);
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'company' => $company ? ['id' => $company->id, 'name' => $company->name, 'status' => $company->status] : null,
        ];
    }
}
