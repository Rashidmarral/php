<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Interim Payment Certificate (IPC) cycle: a project claims cumulative
 * progress against its BOQ, one certificate at a time. Only two real
 * states matter — draft (still editable, not yet billed) and certified
 * (immutable, has generated a real ZATCA-chained invoice via
 * PaymentCertificateController::certify()).
 *
 * payment_certificate_lines snapshots each BOQ line's contract qty/rate
 * AND the previous certificate's cumulative qty at creation time, so a
 * certified certificate's numbers never drift even if the BOQ (for a
 * future certificate) or an earlier certificate is later inspected —
 * belt and suspenders alongside the hard BOQ edit-lock in BoqController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->integer('certificate_number');
            $table->date('certificate_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->decimal('retention_amount', 14, 2)->default(0);
            $table->decimal('advance_recovery_percent', 5, 2)->nullable();
            $table->decimal('advance_recovery_amount', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('net_payable', 14, 2)->default(0);
            $table->decimal('cumulative_certified', 14, 2)->default(0);
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('certified_by')->nullable();
            $table->timestamp('certified_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('payment_certificate_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_certificate_id')->index();
            $table->unsignedBigInteger('boq_item_id')->index();
            $table->string('description', 500);
            $table->string('description_ar', 500)->nullable();
            $table->string('uom', 30);
            $table->decimal('contract_qty', 12, 2)->default(0);
            $table->decimal('contract_unit_price', 12, 2)->default(0);
            $table->decimal('contract_total', 14, 2)->default(0);
            $table->decimal('previous_cumulative_qty', 12, 2)->default(0);
            $table->decimal('cumulative_qty', 12, 2)->default(0);
            $table->decimal('this_period_qty', 12, 2)->default(0);
            $table->decimal('this_period_value', 14, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_certificate_lines');
        Schema::dropIfExists('payment_certificates');
    }
};
