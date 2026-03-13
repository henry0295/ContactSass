<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

final class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->tenants()->pluck('tenants.id')->contains($campaign->tenant_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        $role = $user->tenants()
            ->where('tenants.id', $campaign->tenant_id)
            ->first()
            ?->pivot
            ->role;

        return in_array($role, ['platform_admin', 'tenant_admin'], true);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        $role = $user->tenants()
            ->where('tenants.id', $campaign->tenant_id)
            ->first()
            ?->pivot
            ->role;

        return in_array($role, ['platform_admin', 'tenant_admin'], true);
    }

    public function dispatch(User $user, Campaign $campaign): bool
    {
        $role = $user->tenants()
            ->where('tenants.id', $campaign->tenant_id)
            ->first()
            ?->pivot
            ->role;

        return in_array($role, ['platform_admin', 'tenant_admin', 'operator'], true);
    }
}
