<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidateApiVersionMiddleware;
use App\Http\Middleware\ValidateTenantMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RedisTokenBucketRateLimiter;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class MiddlewareTest extends TestCase
{
    /**
     * Test security headers middleware.
     */
    public function testSecurityHeadersMiddleware(): void
    {
        $middleware = new SecurityHeaders();
        $request = Request::create('/api/campaigns', 'GET');

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertNotNull($response->header('X-Frame-Options'));
        $this->assertEquals('SAMEORIGIN', $response->header('X-Frame-Options'));
        $this->assertNotNull($response->header('X-Content-Type-Options'));
        $this->assertEquals('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertNotNull($response->header('Content-Security-Policy'));
    }

    /**
     * Test CORS middleware.
     */
    public function testCorsMiddleware(): void
    {
        $middleware = new CorsMiddleware();
        $request = Request::create('/api/campaigns', 'GET');
        $request->headers->set('Origin', 'http://localhost:3000');

        // Mock config
        \Config::set('cors.allowed_origins', ['http://localhost:3000']);

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertNotNull($response->header('Access-Control-Allow-Origin'));
        $this->assertNotNull($response->header('Access-Control-Allow-Methods'));
    }

    /**
     * Test API version validation middleware.
     */
    public function testValidateApiVersionMiddleware(): void
    {
        $middleware = new ValidateApiVersionMiddleware();
        $request = Request::create('/api/campaigns', 'GET');
        $request->headers->set('X-API-Version', '2.0');

        $response = $middleware->handle($request, function (Request $req) {
            return response()->json(['api_version' => $req->get('api_version')]);
        });

        $this->assertNotNull($response->header('API-Version'));
        $this->assertEquals('2.0', $response->header('API-Version'));
    }

    /**
     * Test API version validation fails with unsupported version.
     */
    public function testValidateApiVersionMiddlewareFailsWithUnsupportedVersion(): void
    {
        $middleware = new ValidateApiVersionMiddleware();
        $request = Request::create('/api/campaigns', 'GET');
        $request->headers->set('X-API-Version', '99.0');

        $response = $middleware->handle($request, function (Request $req) {
            return response()->json(['api_version' => $req->get('api_version')]);
        });

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('UNSUPPORTED_API_VERSION', $data['code']);
    }

    /**
     * Test tenant validation middleware.
     */
    public function testValidateTenantMiddleware(): void
    {
        $middleware = new ValidateTenantMiddleware();
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $request = Request::create("/api/tenants/{$tenant->id}/campaigns", 'GET');
        $request->attributes->set('tenant_id', $tenant->id);

        $response = $middleware->handle($request, function (Request $req) {
            return response()->json(['tenant' => $req->get('tenant')]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($request->get('tenant'));
    }

    /**
     * Test tenant validation fails with inactive tenant.
     */
    public function testValidateTenantMiddlewareFailsWithInactiveTenant(): void
    {
        $middleware = new ValidateTenantMiddleware();
        $tenant = Tenant::factory()->create(['is_active' => false]);
        $request = Request::create("/api/tenants/{$tenant->id}/campaigns", 'GET');
        $request->attributes->set('tenant_id', $tenant->id);

        $response = $middleware->handle($request, function (Request $req) {
            return response()->json(['tenant' => $req->get('tenant')]);
        });

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_TENANT', $data['code']);
    }

    /**
     * Test rate limit middleware.
     */
    public function testRateLimitMiddleware(): void
    {
        $rateLimiter = new RedisTokenBucketRateLimiter(
            \Redis::connection('default'),
        );

        $middleware = new RateLimitMiddleware($rateLimiter);
        $request = Request::create('/api/campaigns', 'GET');
        $request->setUserResolver(function () {
            return User::factory()->create();
        });

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        }, '10/60');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($response->header('X-RateLimit-Limit'));
        $this->assertEquals('10', $response->header('X-RateLimit-Limit'));
    }

    /**
     * Test rate limit middleware returns 429 when limit exceeded.
     */
    public function testRateLimitMiddlewareExceededLimit(): void
    {
        $rateLimiter = new RedisTokenBucketRateLimiter(
            \Redis::connection('default'),
        );

        $middleware = new RateLimitMiddleware($rateLimiter);
        $user = User::factory()->create();
        $request = Request::create('/api/campaigns', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Make requests beyond limit
        for ($i = 0; $i < 5; $i++) {
            $middleware->handle($request, function () {
                return response('OK', 200);
            }, '3/60');
        }

        // Next request should be rate limited
        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        }, '3/60');

        $this->assertEquals(429, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $data['code']);
    }
}
