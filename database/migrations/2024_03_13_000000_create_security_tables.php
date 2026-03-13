<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->id();

            // User performing the action
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Tenant context
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');

            // Action details
            $table->string('action')->index();
            $table->enum('status', ['success', 'failed', 'denied', 'error'])->default('success')->index();

            // Resource being acted upon
            $table->string('resource_type')->nullable()->index();
            $table->string('resource_id')->nullable()->index();

            // Request details
            $table->string('method')->nullable();
            $table->string('path')->nullable();
            $table->ipAddress('ip_address')->nullable()->index();
            $table->text('user_agent')->nullable();

            // Context (JSON) - sanitized data
            $table->json('context')->nullable();

            // Timestamp
            $table->timestamp('timestamp')->useCurrent()->index();

            // Indexes for performance
            $table->index(['tenant_id', 'timestamp']);
            $table->index(['user_id', 'timestamp']);
            $table->index(['action', 'timestamp']);
            $table->index(['resource_type', 'resource_id']);
        });

        // Create webhook_configs table for storing webhook secrets
        Schema::create('webhook_configs', function (Blueprint $table) {
            $table->id();

            // Tenant optional (for tenant-specific webhooks)
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Webhook provider (amazon-ses, freeswitch, etc)
            $table->string('provider')->index();
            $table->string('name')->nullable();

            // Webhook secret (encrypted in DB)
            $table->text('secret');

            // Configuration
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['tenant_id', 'provider']);
        });

        // Create secrets_rotation_log for tracking secret rotations
        Schema::create('secrets_rotation_logs', function (Blueprint $table) {
            $table->id();

            $table->string('secret_name')->index();
            $table->enum('action', ['created', 'rotated', 'deleted', 'retrieved'])->index();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['secret_name', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secrets_rotation_logs');
        Schema::dropIfExists('webhook_configs');
        Schema::dropIfExists('security_audit_logs');
    }
};
