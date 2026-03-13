<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TenantController
{
    public function index(Request $request): JsonResponse
    {
        $tenants = $request->user()->tenants()
            ->with('tenantSettings', 'tenantLimits')
            ->paginate(15);

        return response()->json([
            'data' => $tenants,
        ], 200);
    }

    public function show(string $tenantId): JsonResponse
    {
        $tenant = Tenant::with('tenantSettings', 'tenantLimits', 'users')
            ->findOrFail($tenantId);

        return response()->json([
            'data' => $tenant,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'sometimes|string|url',
            'logo_url' => 'sometimes|string|url',
        ]);

        try {
            $tenant = DB::transaction(function () use ($validated, $request) {
                $tenant = Tenant::create([
                    'name' => $validated['name'],
                    'slug' => \Str::slug($validated['name']) . '-' . \Str::random(6),
                    'domain' => $validated['domain'] ?? config('app.domain'),
                    'logo_url' => $validated['logo_url'] ?? null,
                    'status' => 'active',
                    'metadata' => [],
                ]);

                // Add creator as admin
                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $request->user()->id,
                    'role' => 'admin',
                    'accepted_at' => now(),
                ]);

                return $tenant;
            });

            return response()->json([
                'message' => 'Tenant created successfully',
                'data' => $tenant->load('tenantSettings', 'tenantLimits'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(string $tenantId, Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Authorization check
        $this->authorize('update', $tenant, $request->user());

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|url',
            'logo_url' => 'sometimes|string|url|nullable',
            'status' => 'sometimes|in:active,suspended,cancelled',
            'metadata' => 'sometimes|array',
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Tenant updated successfully',
            'data' => $tenant,
        ], 200);
    }

    public function destroy(string $tenantId, Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Authorization check
        $this->authorize('delete', $tenant, $request->user());

        $tenant->delete();

        return response()->json([
            'message' => 'Tenant deleted successfully',
        ], 200);
    }

    private function authorize(string $action, Tenant $tenant, $user): void
    {
        $tenantUser = TenantUser::where([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ])->first();

        if (!$tenantUser || !$tenantUser->isAdmin()) {
            abort(403, 'Not authorized to perform this action');
        }
    }
}
