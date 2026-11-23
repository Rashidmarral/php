<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a GC invite a supplier to submit their own quote directly, instead of the GC
 * typing it in via RfqController::recordQuote(). Unlike share_token (generated lazily
 * on first "send" and stored on the parent document — Estimate/PaymentCertificate/
 * ChangeOrder), this token lives on the RfqQuote row itself: one Rfq can invite many
 * suppliers, each needing their own private link that only lets them submit/see their
 * own quote, never the RFQ's other quotes or internal notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_quotes', function (Blueprint $table) {
            $table->string('invite_token', 64)->nullable()->unique()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_quotes', function (Blueprint $table) {
            $table->dropColumn('invite_token');
        });
    }
};
