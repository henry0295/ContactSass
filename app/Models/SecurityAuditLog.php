<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

final class SecurityAuditLog extends Model
{
    /**
     * Indicates if timestamps are used.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'action',
        'user_id',
        'tenant_id',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'method',
        'path',
        'status',
        'context',
        'timestamp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'json',
        'timestamp' => 'datetime',
    ];

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant associated with this audit log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: filter by action.
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: filter by action pattern.
     */
    public function scopeByActionPattern(Builder $query, string $pattern): Builder
    {
        return $query->where('action', 'like', $pattern);
    }

    /**
     * Scope: filter by user.
     */
    public function scopeByUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: filter by tenant.
     */
    public function scopeByTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: filter by resource.
     */
    public function scopeByResource(Builder $query, string $resourceType, string $resourceId): Builder
    {
        return $query
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId);
    }

    /**
     * Scope: filter by IP address.
     */
    public function scopeByIp(Builder $query, string $ip): Builder
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope: filter by date range.
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query
            ->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope: filter by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: failed security events.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: security events (actions starting with 'security.').
     */
    public function scopeSecurityEvents(Builder $query): Builder
    {
        return $query->where('action', 'like', 'security.%');
    }

    /**
     * Scope: authentication events.
     */
    public function scopeAuthenticationEvents(Builder $query): Builder
    {
        return $query->where('action', 'like', 'auth.%');
    }

    /**
     * Scope: authorization failures.
     */
    public function scopeAuthorizationFailures(Builder $query): Builder
    {
        return $query->byActionPattern('authorization.%')->byStatus('denied');
    }

    /**
     * Scope: sensitive operations.
     */
    public function scopeSensitiveOperations(Builder $query): Builder
    {
        return $query->where('action', 'like', 'sensitive.%');
    }

    /**
     * Scope: recent logs (last N hours).
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('timestamp', '>=', now()->subHours($hours));
    }

    /**
     * Get logs for dashboard/monitoring.
     */
    public static function getSecurityDashboard(string $tenantId, int $days = 7): array
    {
        $logs = self::byTenant($tenantId)
            ->where('timestamp', '>=', now()->subDays($days))
            ->get();

        return [
            'total_events' => $logs->count(),
            'failed_auth_attempts' => $logs->where('action', 'auth.failed_login')->count(),
            'authorization_failures' => $logs->where('action', 'authorization.denied')->count(),
            'security_events' => $logs->securityEvents()->count(),
            'unique_ips' => $logs->pluck('ip_address')->unique()->count(),
            'unique_users' => $logs->pluck('user_id')->unique()->count(),
            'events_by_action' => $logs->groupBy('action')->map->count(),
        ];
    }

    /**
     * Check for suspicious activity.
     */
    public static function checkSuspiciousActivity(string $userId, int $failedAttemptThreshold = 5): bool
    {
        $failedAttempts = self::byUser($userId)
            ->byActionPattern('auth.%')
            ->byStatus('failed')
            ->recent(1) // Last hour
            ->count();

        return $failedAttempts >= $failedAttemptThreshold;
    }
}
