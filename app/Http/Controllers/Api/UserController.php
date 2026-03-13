<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UserController
{
    public function index(Request $request, string $tenantId): JsonResponse
    {
        $users = DB::table('tenant_users')
            ->join('users', 'users.id', '=', 'tenant_users.user_id')
            ->where('tenant_users.tenant_id', $tenantId)
            ->select('users.*', 'tenant_users.role')
            ->paginate(20);

        return response()->json($users);
    }

    public function invite(Request $request, string $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:tenant_admin,operator',
        ]);

        // Check if tenant exists
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Find or create user
        $user = DB::table('users')->where('email', $validated['email'])->first();
        if (!$user) {
            $userId = (string) \Illuminate\Support\Str::uuid();
            DB::table('users')->insert([
                'id' => $userId,
                'name' => explode('@', $validated['email'])[0],
                'email' => $validated['email'],
                'password_hash' => bcrypt('temp-password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = $user->id;
        }

        // Assign to tenant
        DB::table('tenant_users')->updateOrInsert(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            ['role' => $validated['role'], 'created_at' => now()]
        );

        return response()->json(['success' => true], 201);
    }

    public function update(Request $request, string $tenantId, string $userId): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:tenant_admin,operator',
        ]);

        $tenantUser = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$tenantUser) {
            return response()->json(['error' => 'User not found in tenant'], 404);
        }

        DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->update(['role' => $validated['role']]);

        return response()->json(['success' => true]);
    }

    public function remove(Request $request, string $tenantId, string $userId): JsonResponse
    {
        $tenantUser = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$tenantUser) {
            return response()->json(['error' => 'User not found in tenant'], 404);
        }

        DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
