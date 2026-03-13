<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookFreeswitchController;
use App\Http\Controllers\Api\WebhookSesController;
use App\Http\Controllers\Api\WebhookSnsController;
use Illuminate\Support\Facades\Route;

// Public webhook routes (no auth required, signature verified)
Route::post('/webhooks/ses', [WebhookSesController::class, 'handle'])->name('webhook.ses');
Route::post('/webhooks/sns', [WebhookSnsController::class, 'handle'])->name('webhook.sns');
Route::post('/webhooks/freeswitch', [WebhookFreeswitchController::class, 'handle'])->name('webhook.freeswitch');

// Public auth routes (no auth required)
Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

// Protected auth routes (auth required)
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail'])->name('auth.verify-email');
    Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile');
    Route::post('/auth/password', [AuthController::class, 'changePassword'])->name('auth.password');

    // Tenants
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenantId}', [TenantController::class, 'show'])->name('tenants.show');
    Route::put('/tenants/{tenantId}', [TenantController::class, 'update'])->name('tenants.update');
    Route::delete('/tenants/{tenantId}', [TenantController::class, 'destroy'])->name('tenants.destroy');
});

// API routes with tenant + auth middleware
Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    // Campaigns
    Route::post('/tenants/{tenantId}/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/tenants/{tenantId}/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/tenants/{tenantId}/campaigns/{campaignId}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::put('/tenants/{tenantId}/campaigns/{campaignId}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('/tenants/{tenantId}/campaigns/{campaignId}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::post('/tenants/{tenantId}/campaigns/{campaignId}/start', [CampaignController::class, 'start'])->name('campaigns.start');
    Route::post('/tenants/{tenantId}/campaigns/{campaignId}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');

    // Contacts
    Route::get('/tenants/{tenantId}/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('/tenants/{tenantId}/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::get('/tenants/{tenantId}/contacts/{contactId}', [ContactController::class, 'show'])->name('contacts.show');
    Route::put('/tenants/{tenantId}/contacts/{contactId}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/tenants/{tenantId}/contacts/{contactId}', [ContactController::class, 'destroy'])->name('contacts.destroy');
    Route::post('/tenants/{tenantId}/contacts/import', [ContactController::class, 'import'])->name('contacts.import');

    // Reports
    Route::get('/tenants/{tenantId}/reports/campaigns/{campaignId}', [ReportController::class, 'campaignAnalytics'])->name('reports.campaign');
    Route::get('/tenants/{tenantId}/reports/usage', [ReportController::class, 'usage'])->name('reports.usage');

    // Users
    Route::get('/tenants/{tenantId}/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/tenants/{tenantId}/users', [UserController::class, 'invite'])->name('users.invite');
    Route::put('/tenants/{tenantId}/users/{userId}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/tenants/{tenantId}/users/{userId}', [UserController::class, 'remove'])->name('users.remove');
});

