<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CampaignController;

// Example API v2.0 routes with middleware

// Public routes (no auth required)
Route::group(['prefix' => 'api/v2'], function () {
    // Health check - no middleware
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    // Authentication endpoints
    Route::post('/auth/login', [\App\Http\Controllers\Api\AuthController::class, 'login'])
        ->name('auth.login');

    Route::post('/auth/register', [\App\Http\Controllers\Api\AuthController::class, 'register'])
        ->name('auth.register');
});

// Protected routes (auth + rate limit)
Route::group([
    'prefix' => 'api/v2',
    'middleware' => ['auth:sanctum', 'rate.limit:500/60'],
], function () {
    // Current user
    Route::get('/me', [\App\Http\Controllers\Api\UserController::class, 'me'])
        ->name('user.me');

    Route::put('/me', [\App\Http\Controllers\Api\UserController::class, 'update'])
        ->name('user.update');

    // User profile
    Route::get('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'show'])
        ->name('profile.show');

    Route::put('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'update'])
        ->name('profile.update');
});

// Multi-tenant routes (auth + tenant validation + permissions)
Route::group([
    'prefix' => 'api/v2/tenants/{tenant_id}',
    'middleware' => ['validate.tenant', 'auth:sanctum'],
], function () {
    // Campaigns - CRUD with specific permissions
    Route::group(['middleware' => 'rate.limit:200/60'], function () {
        Route::get('/campaigns', [CampaignController::class, 'index'])
            ->middleware('validate.permissions:campaigns.read')
            ->name('campaigns.index');

        Route::post('/campaigns', [CampaignController::class, 'store'])
            ->middleware('validate.permissions:campaigns.create')
            ->name('campaigns.store');

        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])
            ->middleware('validate.permissions:campaigns.read')
            ->name('campaigns.show');

        Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])
            ->middleware('validate.permissions:campaigns.update')
            ->name('campaigns.update');

        Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])
            ->middleware('validate.permissions:campaigns.delete')
            ->name('campaigns.destroy');

        // Campaign actions
        Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'send'])
            ->middleware('validate.permissions:campaigns.send')
            ->name('campaigns.send');

        Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])
            ->middleware('validate.permissions:campaigns.update')
            ->name('campaigns.pause');
    });

    // Reports with stricter rate limit
    Route::group(['middleware' => 'rate.limit:50/60'], function () {
        Route::get('/reports/campaigns', [\App\Http\Controllers\Api\ReportController::class, 'campaigns'])
            ->middleware('validate.permissions:reports.view')
            ->name('reports.campaigns');

        Route::get('/reports/messages', [\App\Http\Controllers\Api\ReportController::class, 'messages'])
            ->middleware('validate.permissions:reports.view')
            ->name('reports.messages');

        Route::get('/reports/analytics', [\App\Http\Controllers\Api\ReportController::class, 'analytics'])
            ->middleware('validate.permissions:reports.view')
            ->name('reports.analytics');
    });

    // Messages/Contacts
    Route::group(['middleware' => 'rate.limit:300/60'], function () {
        Route::get('/contacts', [\App\Http\Controllers\Api\ContactController::class, 'index'])
            ->name('contacts.index');

        Route::post('/contacts', [\App\Http\Controllers\Api\ContactController::class, 'store'])
            ->middleware('validate.permissions:campaigns.create')
            ->name('contacts.store');

        Route::get('/contacts/{contact}', [\App\Http\Controllers\Api\ContactController::class, 'show'])
            ->name('contacts.show');

        Route::put('/contacts/{contact}', [\App\Http\Controllers\Api\ContactController::class, 'update'])
            ->middleware('validate.permissions:campaigns.update')
            ->name('contacts.update');

        Route::delete('/contacts/{contact}', [\App\Http\Controllers\Api\ContactController::class, 'destroy'])
            ->middleware('validate.permissions:campaigns.delete')
            ->name('contacts.destroy');
    });

    // Settings - admin only
    Route::group([
        'prefix' => 'settings',
        'middleware' => ['validate.permissions:settings.configure', 'rate.limit:100/60'],
    ], function () {
        Route::get('/integrations', [\App\Http\Controllers\Api\SettingsController::class, 'integrations'])
            ->name('settings.integrations');

        Route::post('/integrations/{provider}', [\App\Http\Controllers\Api\SettingsController::class, 'storeIntegration'])
            ->name('settings.storeIntegration');

        Route::put('/integrations/{provider}', [\App\Http\Controllers\Api\SettingsController::class, 'updateIntegration'])
            ->name('settings.updateIntegration');

        Route::delete('/integrations/{provider}', [\App\Http\Controllers\Api\SettingsController::class, 'deleteIntegration'])
            ->name('settings.deleteIntegration');
    });

    // Users management - admin only
    Route::group([
        'prefix' => 'users',
        'middleware' => ['validate.permissions:users.manage', 'rate.limit:100/60'],
    ], function () {
        Route::get('/', [\App\Http\Controllers\Api\UserController::class, 'index'])
            ->name('users.index');

        Route::post('/', [\App\Http\Controllers\Api\UserController::class, 'store'])
            ->name('users.store');

        Route::get('/{user}', [\App\Http\Controllers\Api\UserController::class, 'show'])
            ->name('users.show');

        Route::put('/{user}', [\App\Http\Controllers\Api\UserController::class, 'update'])
            ->name('users.update');

        Route::delete('/{user}', [\App\Http\Controllers\Api\UserController::class, 'destroy'])
            ->name('users.destroy');

        Route::post('/{user}/invite', [\App\Http\Controllers\Api\UserController::class, 'sendInvite'])
            ->name('users.invite');

        Route::post('/{user}/deactivate', [\App\Http\Controllers\Api\UserController::class, 'deactivate'])
            ->name('users.deactivate');
    });
});

// Admin routes - highest restrictions
Route::group([
    'prefix' => 'api/v2/admin',
    'middleware' => ['auth:sanctum', 'validate.permissions:users.manage,settings.configure', 'rate.limit:50/60'],
], function () {
    // Tenant management
    Route::get('/tenants', [\App\Http\Controllers\Api\TenantController::class, 'index'])
        ->name('admin.tenants.index');

    Route::post('/tenants', [\App\Http\Controllers\Api\TenantController::class, 'store'])
        ->name('admin.tenants.store');

    Route::get('/tenants/{tenant}', [\App\Http\Controllers\Api\TenantController::class, 'show'])
        ->name('admin.tenants.show');

    Route::put('/tenants/{tenant}', [\App\Http\Controllers\Api\TenantController::class, 'update'])
        ->name('admin.tenants.update');

    Route::post('/tenants/{tenant}/deactivate', [\App\Http\Controllers\Api\TenantController::class, 'deactivate'])
        ->name('admin.tenants.deactivate');

    // System monitoring
    Route::get('/monitoring/health', [\App\Http\Controllers\Api\MonitoringController::class, 'health'])
        ->name('admin.monitoring.health');

    Route::get('/monitoring/logs', [\App\Http\Controllers\Api\MonitoringController::class, 'logs'])
        ->name('admin.monitoring.logs');

    Route::get('/monitoring/errors', [\App\Http\Controllers\Api\MonitoringController::class, 'errors'])
        ->name('admin.monitoring.errors');
});

// Webhook endpoints (custom auth)
Route::group([
    'prefix' => 'api/v2/webhooks',
    'middleware' => ['rate.limit:1000/60'], // Higher limit for webhooks
], function () {
    Route::post('/inbound-messages', [\App\Http\Controllers\Api\WebhookController::class, 'inboundMessages'])
        ->middleware('validate.webhook')
        ->name('webhooks.inbound');

    Route::post('/delivery-status', [\App\Http\Controllers\Api\WebhookController::class, 'deliveryStatus'])
        ->middleware('validate.webhook')
        ->name('webhooks.delivery');
});

/*
 * Middleware Priority/Order:
 *
 * Global (all requests):
 * 1. RequestLoggingMiddleware
 * 2. SecurityHeaders
 * 3. CorsMiddleware
 * 4. ValidateApiVersionMiddleware
 * 5. RateLimitMiddleware
 * 6. ResponseTransformMiddleware
 *
 * Route-specific:
 * 1. validate.tenant: Valida tenant_id en URL
 * 2. auth:sanctum: Valida usuario autenticado
 * 3. validate.permissions: Valida permisos específicos
 * 4. rate.limit: Rate limiting por ruta
 *
 * Ejemplo completo: POST /api/v2/tenants/1/campaigns
 * Middleware ejecutados:
 * - RequestLoggingMiddleware (global)
 * - SecurityHeaders (global)
 * - CorsMiddleware (global)
 * - ValidateApiVersionMiddleware (global)
 * - RateLimitMiddleware 1000/60 (global)
 * - ValidateTenantMiddleware (route)
 * - auth:sanctum (route)
 * - RateLimitMiddleware 200/60 (route specific)
 * - ValidateUserPermissionsMiddleware:campaigns.create (route)
 * - ResponseTransformMiddleware (global)
 */
