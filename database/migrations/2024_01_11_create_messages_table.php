<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('campaign_batch_id')->constrained();
            $table->uuid('message_uuid')->unique();
            $table->enum('channel', ['email', 'sms', 'voice'])->default('email');
            $table->enum('status', ['pending', 'sending', 'sent', 'failed', 'bounced', 'complained', 'opened', 'clicked'])->default('pending');
            $table->json('payload');
            $table->string('provider_message_id')->nullable();
            $table->string('error_message')->nullable();
            $table->enum('error_type', ['transient', 'permanent', 'rate_limited'])->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('tenant_id');
            $table->index('campaign_id');
            $table->index('contact_id');
            $table->index('status');
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
