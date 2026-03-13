<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasTenantScope
{
    /**
     * Boot the tenant scope trait
     */
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = app('current_tenant_id', null)) {
                $builder->where('tenant_id', $tenantId);
            }
        });
    }
    
    /**
     * Scope query to specific tenant
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
