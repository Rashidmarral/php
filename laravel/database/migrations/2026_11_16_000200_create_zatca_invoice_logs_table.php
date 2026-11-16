<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dedicated, append-friendly history of every ZATCA clearance/reporting
 * attempt for an invoice — one row per attempt (queued, cleared, reported,
 * or failed), independent of the single "current" outcome BuildXact
 * already tracks on invoices.zatca_* (uuid/icv/hash/previous_hash/status/
 * submitted_at/response — left exactly where they are, see Company/
 * Invoice model docblocks). Ported from Daftri's
 * 0001_01_19_100000_create_zatca_invoice_logs_table migration.
 *
 * Both timestamps (created_at AND updated_at) are kept here, unlike this
 * codebase's usual "created_at only" convention — a log row genuinely is
 * updated repeatedly as its status transitions (queued → cleared/reported/
 * failed), the same reasoning Daftri's own migration applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_invoice_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20); // developer, simulation, production
            $table->string('invoice_type', 10); // b2b (standard/clearance), b2c (simplified/reporting)
            $table->string('direction', 20); // clearance, reporting
            $table->string('status', 20)->default('queued'); // queued, cleared, reported, failed
            $table->uuid('request_uuid');
            $table->string('invoice_hash', 128)->nullable();
            $table->string('previous_invoice_hash', 128)->nullable();
            $table->longText('xml_payload')->nullable();
            $table->longText('cryptographic_stamp')->nullable();
            $table->longText('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_invoice_logs');
    }
};
