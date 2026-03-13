<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateTenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->route('tenant_id') ?? $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant ID is required',
                'code' => 'MISSING_TENANT_ID',
            ], 400);
        }

        // Validate tenant exists and is active
        $tenant = Tenant::where('id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found or inactive',
                'code' => 'INVALID_TENANT',
            ], 404);
        }

        // Store tenant in request for later use
        $request->merge(['tenant' => $tenant]);

        return $next($request);
    }
}
