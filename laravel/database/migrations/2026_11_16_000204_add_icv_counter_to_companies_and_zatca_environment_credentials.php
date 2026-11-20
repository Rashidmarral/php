<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A persisted, per-environment Invoice Counter Value, advanced ONLY on a
 * CONFIRMED successful clearance/reporting response — in the exact same
 * update() call that already advances zatca_last_invoice_hash inside
 * ZatcaSyncService::submitInvoice()/submitCreditNote()/submitDebitNote().
 * Ported from Daftri's 2026_10_16_050000_add_icv_counter_to_companies_and_
 * zatca_environment_credentials_table — see that migration's docblock for
 * the full reasoning: a derived COUNT(*) over log rows pools every
 * environment together with no way to tell them apart, and never resets
 * when onboarding is reset for a fresh CSID even though the PIH hash
 * chain correctly does.
 *
 * This is deliberately a NEW, separate counter from the existing
 * companies.zatca_last_icv / invoices.zatca_icv columns, which BuildXact
 * already advances eagerly at invoice CREATION time (see
 * App\Support\Zatca\InvoiceChainer's docblock) rather than at confirmed
 * submission success — that eager-chaining behaviour is unchanged by this
 * migration and keeps its own existing test (ZatcaGenesisHashTest)
 * passing untouched. zatca_icv_counter instead answers a narrower
 * question — "how many documents has this environment's CSID actually
 * had cleared/reported so far" — which the locked, success-only advance
 * in ZatcaSyncService needs for the concurrency guarantees audited under
 * finding D-1 (see ZatcaSyncService::lockedSubmission()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('zatca_icv_counter')->default(0)->after('zatca_last_sync_at');
        });

        Schema::table('zatca_environment_credentials', function (Blueprint $table) {
            $table->unsignedInteger('icv_counter')->default(0)->after('last_invoice_hash');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('zatca_icv_counter');
        });

        Schema::table('zatca_environment_credentials', function (Blueprint $table) {
            $table->dropColumn('icv_counter');
        });
    }
};
