<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            // company_id is denormalized alongside project_id so company-scoped queries don't need
            // to join through projects — same convention as change_orders/bank_guarantees.
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('po_number', 30);
            $table->string('status', 20)->default('draft');
            $table->date('issue_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id')->index();
            $table->string('description', 255);
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
