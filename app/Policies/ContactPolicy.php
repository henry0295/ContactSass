<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

final class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->tenants()->pluck('tenants.id')->contains($contact->tenant_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contact $contact): bool
    {
        $role = $user->tenants()
            ->where('tenants.id', $contact->tenant_id)
            ->first()
            ?->pivot
            ->role;

        return in_array($role, ['platform_admin', 'tenant_admin', 'operator'], true);
    }

    public function delete(User $user, Contact $contact): bool
    {
        $role = $user->tenants()
            ->where('tenants.id', $contact->tenant_id)
            ->first()
            ?->pivot
            ->role;

        return in_array($role, ['platform_admin', 'tenant_admin'], true);
    }
}
