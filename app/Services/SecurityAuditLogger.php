<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SecurityAuditLog;
use Illuminate\Http\Request;

final class SecurityAuditLogger
{
    /**
     * Sensible fields to sanitize in logs.
     *
     * @var array<int, string>
     */
    private static array $sensitiveFields = [
        'password',
        'pin',
        'secret',
        'token',
        'api_key',
        'authorization',
        'bearer',
        'x-api-key',
        'credit_card',
        'cvv',
        'ssn',
        'social_security',
    ];

    /**
     * Log a security-relevant action.
     *
     * @param string $action - Action name (e.g., 'user.created', 'campaign.sent')
     * @param array<string, mixed> $context - Contextual data
     */
    public static function log(string $action, array $context = []): void
    {
        try {
            $log = SecurityAuditLog::create([
                'action' => $action,
                'user_id' => auth()->id(),
                'tenant_id' => $context['tenant_id'] ?? request('tenant_id'),
                'resource_type' => $context['resource_type'] ?? null,
                'resource_id' => $context['resource_id'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'method' => request()->method(),
                'path' => request()->path(),
                'status' => $context['status'] ?? null,
                'context' => self::sanitize($context),
                'timestamp' => now(),
            ]);

            \Log::channel('security')->info("Security audit: {$action}", [
                'audit_id' => $log->id,
                'user_id' => auth()->id(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error logging security audit', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log authentication event.
     */
    public static function logAuthentication(string $userId, bool $success, string $method = 'password'): void
    {
        self::log('auth.attempt', [
            'user_id' => $userId,
            'success' => $success,
            'method' => $method,
            'status' => $success ? 'success' : 'failed',
        ]);
    }

    /**
     * Log authorization failure.
     */
    public static function logAuthorizationFailure(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        string $reason = 'unknown'
    ): void {
        self::log('authorization.denied', [
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'reason' => $reason,
            'status' => 'denied',
        ]);
    }

    /**
     * Log resource modification (create, update, delete).
     */
    public static function logResourceModification(
        string $action,
        string $resourceType,
        string $resourceId,
        array $changes = [],
        string $status = 'success'
    ): void {
        self::log("resource.{$action}", [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'changes' => self::sanitize($changes),
            'status' => $status,
        ]);
    }

    /**
     * Log sensitive operation (e.g., password change, API key generation).
     */
    public static function logSensitiveOperation(
        string $operation,
        array $details = [],
        string $status = 'success'
    ): void {
        self::log("sensitive.{$operation}", array_merge($details, [
            'status' => $status,
        ]));
    }

    /**
     * Log security event (e.g., rate limit exceeded, CSRF failure).
     */
    public static function logSecurityEvent(
        string $event,
        array $details = [],
        string $severity = 'warning'
    ): void {
        $action = "security.{$event}";

        self::log($action, array_merge($details, [
            'severity' => $severity,
        ]));

        // Log también en channel 'security' si es crítico
        if ($severity === 'critical') {
            \Log::critical("Critical security event: {$event}", $details);
        }
    }

    /**
     * Log failed login attempt.
     */
    public static function logFailedLogin(string $email, string $reason = 'invalid_credentials'): void
    {
        self::log('auth.failed_login', [
            'email' => $email,
            'reason' => $reason,
            'status' => 'failed',
        ]);
    }

    /**
     * Log password change.
     */
    public static function logPasswordChange(string $userId): void
    {
        self::log('auth.password_changed', [
            'user_id' => $userId,
            'status' => 'success',
        ]);
    }

    /**
     * Log API key generation.
     */
    public static function logApiKeyGeneration(string $userId, string $name = null): void
    {
        self::log('auth.api_key_generated', [
            'user_id' => $userId,
            'api_key_name' => $name,
            'status' => 'success',
        ]);
    }

    /**
     * Log API key revocation.
     */
    public static function logApiKeyRevocation(string $userId, string $keyId): void
    {
        self::log('auth.api_key_revoked', [
            'user_id' => $userId,
            'api_key_id' => $keyId,
            'status' => 'success',
        ]);
    }

    /**
     * Log campaign sent.
     */
    public static function logCampaignSent(string $campaignId, int $recipientCount, string $status = 'success'): void
    {
        self::log('campaign.sent', [
            'resource_type' => 'campaign',
            'resource_id' => $campaignId,
            'recipient_count' => $recipientCount,
            'status' => $status,
        ]);
    }

    /**
     * Log campaign deleted.
     */
    public static function logCampaignDeleted(string $campaignId, string $status = 'success'): void
    {
        self::log('campaign.deleted', [
            'resource_type' => 'campaign',
            'resource_id' => $campaignId,
            'status' => $status,
        ]);
    }

    /**
     * Log user added to tenant.
     */
    public static function logUserAddedToTenant(string $userId, string $tenantId, string $role = 'operator'): void
    {
        self::log('tenant_user.created', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'role' => $role,
            'status' => 'success',
        ]);
    }

    /**
     * Sanitize sensitive data from arrays/objects.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map(function ($key, $value) {
                if (self::isSensitiveField($key)) {
                    return '***REDACTED***';
                }

                return self::sanitize($value);
            }, array_keys($data), array_values($data));
        }

        if (is_object($data)) {
            $sanitized = (array) $data;

            return (object) self::sanitize($sanitized);
        }

        return $data;
    }

    /**
     * Check if field name is sensitive.
     */
    private static function isSensitiveField(string $fieldName): bool
    {
        $fieldLower = strtolower($fieldName);

        foreach (self::$sensitiveFields as $sensitive) {
            if (str_contains($fieldLower, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get audit logs for a user (last 30 days).
     */
    public static function getUserLogs(string $userId, int $days = 30): \Illuminate\Pagination\LengthAwarePaginator
    {
        return SecurityAuditLog::where('user_id', $userId)
            ->where('timestamp', '>=', now()->subDays($days))
            ->orderByDesc('timestamp')
            ->paginate(50);
    }

    /**
     * Get audit logs for a resource.
     */
    public static function getResourceLogs(string $resourceType, string $resourceId): \Illuminate\Database\Eloquent\Collection
    {
        return SecurityAuditLog::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->orderByDesc('timestamp')
            ->get();
    }

    /**
     * Get recent security events (last 24 hours).
     */
    public static function getRecentSecurityEvents(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return SecurityAuditLog::where('action', 'like', 'security.%')
            ->where('timestamp', '>=', now()->subHours(24))
            ->orderByDesc('timestamp')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed authentication attempts (last hour).
     */
    public static function getFailedAuthAttempts(int $minutes = 60): \Illuminate\Database\Eloquent\Collection
    {
        return SecurityAuditLog::where('action', 'auth.failed_login')
            ->where('timestamp', '>=', now()->subMinutes($minutes))
            ->orderByDesc('timestamp')
            ->get();
    }
}
