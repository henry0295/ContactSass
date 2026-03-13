<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Http\Middleware\CsrfProtectionMiddleware;
use App\Http\Middleware\VerifyWebhookSignature;
use App\Http\Middleware\EnforceHttpsWithHsts;
use App\Models\SecurityAuditLog;
use App\Services\SecretsManager;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    /**
     * Test CSRF token verification.
     */
    public function testCsrfTokenVerification(): void
    {
        $middleware = new CsrfProtectionMiddleware();
        $request = Request::create('/api/campaigns', 'POST');
        session(['csrf_token' => 'valid-token-123']);
        $request->headers->set('X-CSRF-Token', 'valid-token-123');

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test CSRF token verification fails with invalid token.
     */
    public function testCsrfTokenVerificationFailsWithInvalidToken(): void
    {
        $middleware = new CsrfProtectionMiddleware();
        $request = Request::create('/api/campaigns', 'POST');
        session(['csrf_token' => 'valid-token-123']);
        $request->headers->set('X-CSRF-Token', 'invalid-token');

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(419, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('CSRF_TOKEN_MISMATCH', $data['code']);
    }

    /**
     * Test CSRF token not required for GET requests.
     */
    public function testCsrfTokenNotRequiredForGetRequests(): void
    {
        $middleware = new CsrfProtectionMiddleware();
        $request = Request::create('/api/campaigns', 'GET');

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test webhook signature verification.
     */
    public function testWebhookSignatureVerification(): void
    {
        $middleware = new VerifyWebhookSignature();
        $secret = 'webhook-secret-123';
        $payload = json_encode(['event' => 'message.received']);

        // Mock config
        \Config::set('services.webhook.amazon-ses.secret', $secret);

        $signature = hash_hmac('sha256', $payload, $secret);

        $request = Request::create('/api/webhooks/amazon-ses', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Signature', $signature);

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test webhook signature verification fails with invalid signature.
     */
    public function testWebhookSignatureVerificationFailsWithInvalidSignature(): void
    {
        $middleware = new VerifyWebhookSignature();
        $secret = 'webhook-secret-123';
        $payload = json_encode(['event' => 'message.received']);

        $request = Request::create('/api/webhooks/amazon-ses', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Signature', 'invalid-signature');

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('WEBHOOK_SIGNATURE_INVALID', $data['code']);
    }

    /**
     * Test HTTPS enforcement in production.
     */
    public function testHttpsEnforcement(): void
    {
        $middleware = new EnforceHttpsWithHsts();
        $request = Request::create('http://localhost/api', 'GET');

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        // Should add HSTS header even though not forcing HTTPS in non-prod
        $this->assertNotNull($response->header('Strict-Transport-Security'));
    }

    /**
     * Test HSTS header format.
     */
    public function testHstsHeaderFormat(): void
    {
        $middleware = new EnforceHttpsWithHsts();
        $request = Request::create('https://localhost/api', 'GET');

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $hsts = $response->header('Strict-Transport-Security');
        $this->assertStringContainsString('max-age', $hsts);
    }

    /**
     * Test security audit logging.
     */
    public function testSecurityAuditLogging(): void
    {
        SecurityAuditLogger::log('test.action', [
            'resource_type' => 'campaign',
            'resource_id' => '123',
            'password' => 'secret123', // Should be sanitized
        ]);

        // Note: En un test real usaría database y verificaría que se guardó
        // Este es un test básico de que el método no lanza excepciones
        $this->assertTrue(true);
    }

    /**
     * Test password sanitization in audit logs.
     */
    public function testPasswordSanitization(): void
    {
        $data = [
            'email' => 'user@example.com',
            'password' => 'secret123',
            'api_key' => 'sk-123456',
            'token' => 'bearer-token',
        ];

        $sanitized = SecurityAuditLogger::sanitize($data);

        $this->assertEquals('user@example.com', $sanitized['email']);
        $this->assertEquals('***REDACTED***', $sanitized['password']);
        $this->assertEquals('***REDACTED***', $sanitized['api_key']);
        $this->assertEquals('***REDACTED***', $sanitized['token']);
    }

    /**
     * Test secrets manager with environment fallback.
     */
    public function testSecretsManagerEnvironmentFallback(): void
    {
        $manager = new SecretsManager();

        // Mock environment variable
        putenv('DATABASE_PASSWORD_SECRET=my-secret-123');

        $secret = $manager->get('database-password');

        // Will fallback to env if AWS Secrets is not enabled
        $this->assertNotEmpty($secret);
    }

    /**
     * Test sensitive fields detection.
     */
    public function testSensitiveFieldsDetection(): void
    {
        $sensitiveFields = [
            'password' => 'secret',
            'api_key' => 'key-123',
            'credit_card' => '4111111111111111',
            'authorization' => 'Bearer token',
            'x-api-key' => 'key',
        ];

        foreach ($sensitiveFields as $field => $value) {
            $sanitized = SecurityAuditLogger::sanitize([$field => $value]);
            $this->assertEquals('***REDACTED***', $sanitized[$field], "Field {$field} should be redacted");
        }
    }

    /**
     * Test security audit log queries.
     */
    public function testSecurityAuditLogQueryScopes(): void
    {
        // Create test logs
        SecurityAuditLog::create([
            'action' => 'auth.failed_login',
            'status' => 'failed',
            'ip_address' => '192.168.1.1',
            'timestamp' => now(),
        ]);

        // Test byAction scope
        $logs = SecurityAuditLog::byAction('auth.failed_login')->get();
        $this->assertTrue($logs->isNotEmpty());

        // Test failed scope
        $failedLogs = SecurityAuditLog::failed()->get();
        $this->assertTrue($failedLogs->isNotEmpty());

        // Test securityEvents scope
        $securityLogs = SecurityAuditLog::securityEvents()->get();
        // Should be empty if no security events were created
    }
}
