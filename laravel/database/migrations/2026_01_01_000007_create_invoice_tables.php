<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('invoice_number', 30);
            $table->string('status', 20)->default('unpaid');
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('share_token', 64)->nullable()->index();
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->decimal('retention_amount', 12, 2)->default(0);
            $table->boolean('retention_released')->default(false);
            $table->timestamp('retention_released_at')->nullable();
            $table->string('zatca_uuid', 64)->nullable();
            $table->integer('zatca_icv')->nullable();
            $table->string('zatca_hash', 255)->nullable();
            $table->string('zatca_previous_hash', 255)->nullable();
            $table->string('zatca_status', 20)->default('not_submitted');
            $table->timestamp('zatca_submitted_at')->nullable();
            $table->text('zatca_response')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->string('description', 255);
            $table->string('description_ar', 255)->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
