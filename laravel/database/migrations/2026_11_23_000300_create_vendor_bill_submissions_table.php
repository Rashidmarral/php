<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The supplier portal's review queue: a VendorBill row feeds straight into real cost/WIP
 * reporting (Project::actualCostTotal(), the cost-variance report), so a supplier's own
 * self-submitted bill must land here first rather than becoming a real VendorBill directly.
 * A staff member explicitly approves it (VendorBillController::approveSubmission() then
 * creates a real VendorBill, byte-identical to today's manual-entry path) or rejects it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bill_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
            $table->unsignedBigInteger('subcontract_id')->nullable()->index();
            $table->string('category', 20)->default('material');
            $table->string('description', 255);
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('bill_date')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('vendor_bill_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bill_submissions');
    }
};
