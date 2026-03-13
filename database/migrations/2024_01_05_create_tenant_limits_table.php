<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('emails_per_minute')->default(1000);
            $table->integer('sms_per_minute')->default(600);
            $table->integer('calls_per_minute')->default(120);
            $table->integer('monthly_email_limit')->default(100000);
            $table->integer('monthly_sms_limit')->default(50000);
            $table->integer('monthly_call_limit')->default(10000);
            $table->timestamp('reset_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_limits');
    }
};
