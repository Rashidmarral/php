<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the same client-facing e-signature fields Estimate already has (see
 * 2026_01_01_000006_create_estimate_tables.php) to payment_certificates, so an
 * Interim Payment Certificate can be sent to the client for a real signed
 * sign-off — a separate, additional step from the existing internal
 * certify()/status flow, not a replacement for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_certificates', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('invoice_id');
            $table->timestamp('signed_at')->nullable()->after('share_token');
            $table->string('signed_by_name')->nullable()->after('signed_at');
            $table->longText('signature_data')->nullable()->after('signed_by_name');
            $table->string('signed_ip')->nullable()->after('signature_data');
        });
    }

    public function down(): void
    {
        Schema::table('payment_certificates', function (Blueprint $table) {
            $table->dropColumn(['share_token', 'signed_at', 'signed_by_name', 'signature_data', 'signed_ip']);
        });
    }
};
