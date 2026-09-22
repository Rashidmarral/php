<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring invoice templates (maintenance/retainer contracts, leases,
 * service agreements) — a template holds a fixed set of line items and a
 * schedule; App\Console\Commands\RunDailyTasks generates a real Invoice
 * (+ InvoiceItem rows, + the same eager ZATCA hash-chain link every other
 * invoice gets) from it each time next_run_date comes due, then advances
 * next_run_date by one frequency period. See RecurringInvoice::generateInvoice().
 *
 * Mirrors invoices/invoice_items' shape closely (client_id/project_id,
 * apply_vat, retention_percent, and the bilingual description/description_ar
 * line items) since generation is otherwise a straight copy into a real
 * invoice — see InvoiceController::store() for the arithmetic this repeats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->string('title', 255);
            $table->string('frequency', 20)->default('monthly');
            $table->date('next_run_date')->index();
            $table->timestamp('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('apply_vat')->default(true);
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->unsignedInteger('due_days')->default(14);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('recurring_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recurring_invoice_id')->index();
            $table->string('description', 255);
            $table->string('description_ar', 255)->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_items');
        Schema::dropIfExists('recurring_invoices');
    }
};
