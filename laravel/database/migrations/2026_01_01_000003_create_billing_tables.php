<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('billing_cycle', 10)->default('monthly');
            $table->string('status', 20)->default('trialing');
            $table->timestamp('current_period_end')->nullable();
            $table->string('moyasar_card_token', 255)->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->string('method', 30)->default('manual');
            $table->string('billing_cycle', 10)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('status', 20)->default('paid');
            $table->string('proof_file_path', 255)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->unsignedBigInteger('company_id')->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->string('method', 30)->default('moyasar');
            $table->string('reference', 100)->nullable();
            $table->string('status', 20)->default('paid');
            $table->string('payer_name', 150)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
    }
};
