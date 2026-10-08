<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Back-to-back subcontractor billing: a contractor hires a subcontractor under the main
 * project contract for a single lump-sum contract_value (no per-line BOQ — see
 * SubcontractController's own docblock for the scope reasoning) and claims cumulative
 * progress against it one subcontract_payment at a time — the direct analogue of the main
 * IPC module's BOQ/PaymentCertificate pattern, but purchase-side: certifying a payment
 * records a VendorBill (category='subcontractor'), never a ZATCA-chained sales invoice,
 * since this is money the contractor pays OUT, not money it bills a client.
 *
 * Only two real states matter per payment — draft (still editable/deletable, not yet
 * recorded as an expense) and certified (immutable, has a real VendorBill behind it via
 * SubcontractPaymentController::certify()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->decimal('contract_value', 14, 2)->default(0);
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('subcontract_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('subcontract_id')->index();
            $table->integer('payment_number');
            $table->date('payment_date');
            $table->string('status', 20)->default('draft');
            $table->decimal('cumulative_value', 14, 2)->default(0);
            $table->decimal('previous_cumulative_value', 14, 2)->default(0);
            $table->decimal('this_period_value', 14, 2)->default(0);
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->decimal('retention_amount', 14, 2)->default(0);
            $table->decimal('net_payable', 14, 2)->default(0);
            $table->unsignedBigInteger('vendor_bill_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('certified_by')->nullable();
            $table->timestamp('certified_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_payments');
        Schema::dropIfExists('subcontracts');
    }
};
