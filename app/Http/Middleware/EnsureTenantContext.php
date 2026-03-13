<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Extract tenant_id from route parameter or header
        $tenantId = $request->route('tenant_id')
            ?? $request->route('tenantId')
            ?? $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'error' => 'Tenant ID is required',
                'message' => 'Missing tenant_id in route or X-Tenant-ID header',
            ], 400);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication required',
            ], 401);
        }

        // Verify user belongs to tenant
        $belongsToTenant = TenantUser::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$belongsToTenant) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Access denied to this tenant',
            ], 403);
        }

        // Set tenant context for request
        $request->attributes->set('tenant_id', $tenantId);
        app()->instance('current_tenant_id', $tenantId);

        return $next($request);
    }
}
