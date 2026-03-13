<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'tenant_name' => 'required|string|max:255',
        ]);

        try {
            $user = DB::transaction(function () use ($validated) {
                // Create user
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);

                // Create tenant for new user
                $tenant = Tenant::create([
                    'name' => $validated['tenant_name'],
                    'slug' => \Str::slug($validated['tenant_name']) . '-' . \Str::random(6),
                    'domain' => config('app.domain'),
                    'status' => 'active',
                ]);

                // Add user as admin to tenant
                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'role' => 'admin',
                    'accepted_at' => now(),
                ]);

                return $user;
            });

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user->only('id', 'name', 'email'),
                'token' => $token,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'User account is inactive',
            ], 403);
        }

        // Revoke all previous tokens
        $user->tokens()->delete();

        // Generate new token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Get user's tenants
        $tenants = $user->tenants()->with('tenantSettings')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'role' => $t->pivot->role,
                'logo_url' => $t->logo_url,
            ]);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->only('id', 'name', 'email'),
            'tenants' => $tenants,
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke the token used to make request
        $request->user()->currentAccessToken()->delete();

        // Optional: revoke all tokens
        // $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ], 200);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Optionally validate user is still active
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'User account is inactive',
            ], 403);
        }

        // Revoke old token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Token refreshed successfully',
            'user' => $user->only('id', 'name', 'email'),
            'token' => $token,
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $tenants = $user->tenants()
            ->with('tenantSettings')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'role' => $t->pivot->role,
                'logo_url' => $t->logo_url,
            ]);

        return response()->json([
            'user' => $user->only('id', 'name', 'email', 'avatar_url'),
            'tenants' => $tenants,
        ], 200);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->user()->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Email verified successfully',
        ], 200);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'avatar_url' => 'sometimes|string|url',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $request->user()->only('id', 'name', 'email', 'avatar_url'),
        ], 200);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Revoke all tokens to force re-login
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully. Please login again.',
        ], 200);
    }
}
