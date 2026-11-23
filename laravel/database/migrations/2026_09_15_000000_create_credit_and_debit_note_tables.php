<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit Notes (UBL InvoiceTypeCode 381) and Debit Notes (383) — the only
 * ZATCA-compliant way to correct an already-cleared/reported invoice,
 * since that document is now part of an immutable tax record (see
 * Invoice::isZatcaLocked()). Each note references the invoice it corrects
 * via invoice_id (mandatory — a note is never issued standalone) and
 * carries its own company-wide ZATCA chain columns, mirroring the
 * invoices table's own shape exactly: a note is the next link in the SAME
 * company-wide ICV/PIH hash chain as regular invoices, not a separate
 * chain (see ZatcaSyncService::submitCreditNote()/submitDebitNote()).
 *
 * Two separate tables per document type (not one polymorphic table),
 * matching Daftri's own proven separation. Deliberately simpler than
 * Daftri's current schema: no branch/salesperson/currency/tax-rate
 * columns, no separate zatca_*_logs table — chain state lives directly on
 * the note row, exactly like Invoice already does.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['credit_notes' => 'credit_note_items', 'debit_notes' => 'debit_note_items'] as $notesTable => $itemsTable) {
            $noteColumn = rtrim($notesTable, 's') . '_id'; // credit_note_id / debit_note_id

            Schema::create($notesTable, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->string('note_number', 30);
                $table->date('issue_date')->nullable();
                $table->text('reason')->nullable();
                $table->string('status', 20)->default('issued');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->nullable();
                $table->decimal('vat_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->string('zatca_uuid', 64)->nullable();
                $table->integer('zatca_icv')->nullable();
                $table->string('zatca_hash', 255)->nullable();
                $table->string('zatca_previous_hash', 255)->nullable();
                $table->string('zatca_status', 20)->default('not_submitted');
                $table->timestamp('zatca_submitted_at')->nullable();
                $table->text('zatca_response')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
            });

            Schema::create($itemsTable, function (Blueprint $table) use ($noteColumn) {
                $table->id();
                $table->unsignedBigInteger($noteColumn)->index();
                $table->string('description', 255);
                $table->string('description_ar', 255)->nullable();
                $table->decimal('qty', 10, 2)->default(1);
                $table->decimal('unit_price', 10, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->timestamp('created_at')->nullable()->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('debit_note_items');
        Schema::dropIfExists('debit_notes');
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
    }
};
