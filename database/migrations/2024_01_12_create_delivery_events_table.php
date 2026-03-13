<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('provider_event_id')->nullable();
            $table->text('provider_response')->nullable();
            $table->text('diagnostic_code')->nullable();
            $table->timestamp('happened_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id');
            $table->index('message_id');
            $table->index('event_type');
            $table->index('happened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_events');
    }
};
