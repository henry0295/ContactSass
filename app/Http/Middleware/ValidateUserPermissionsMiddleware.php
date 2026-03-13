<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateUserPermissionsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Verify user belongs to the tenant
        $tenant = $request->get('tenant');
        if ($tenant && !$this->userBelongsToTenant($user, $tenant)) {
            return response()->json([
                'message' => 'User does not have access to this tenant',
                'code' => 'UNAUTHORIZED_TENANT_ACCESS',
            ], 403);
        }

        // Check permissions if provided
        if (!empty($permissions)) {
            if (!$this->userHasPermissions($user, $permissions)) {
                return response()->json([
                    'message' => 'Insufficient permissions',
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'required_permissions' => $permissions,
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Check if user belongs to tenant.
     */
    private function userBelongsToTenant(User $user, mixed $tenant): bool
    {
        $tenantId = $tenant instanceof \Illuminate\Database\Eloquent\Model ? $tenant->id : $tenant;

        return $user->tenant_users()
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * Check if user has required permissions.
     *
     * @param  array<int, string>  $permissions
     */
    private function userHasPermissions(User $user, array $permissions): bool
    {
        // Get user's role(s) for the tenant
        $userRoles = $user->tenant_users()
            ->pluck('role')
            ->toArray();

        // Check if user has any of the required permissions
        foreach ($permissions as $permission) {
            if ($this->roleHasPermission($userRoles, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if role(s) have permission.
     *
     * @param  array<int, string>  $roles
     */
    private function roleHasPermission(array $roles, string $permission): bool
    {
        $rolePermissions = [
            'admin' => [
                'campaigns.create',
                'campaigns.read',
                'campaigns.update',
                'campaigns.delete',
                'campaigns.send',
                'users.manage',
                'settings.configure',
                'reports.view',
            ],
            'manager' => [
                'campaigns.create',
                'campaigns.read',
                'campaigns.update',
                'campaigns.send',
                'reports.view',
            ],
            'operator' => [
                'campaigns.read',
                'campaigns.send',
            ],
            'viewer' => [
                'campaigns.read',
                'reports.view',
            ],
        ];

        foreach ($roles as $role) {
            if (isset($rolePermissions[$role]) && in_array($permission, $rolePermissions[$role], true)) {
                return true;
            }
        }

        return false;
    }
}
