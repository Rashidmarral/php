<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobilization/advance payment terms for a contract: the amount the client
 * paid up front, and the % of each payment certificate's gross claim that
 * gets deducted to recover it — used by PaymentCertificateController when
 * computing a certificate's net payable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('advance_payment_amount', 14, 2)->nullable()->default(0)->after('budget');
            $table->decimal('advance_recovery_percent', 5, 2)->nullable()->after('advance_payment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['advance_payment_amount', 'advance_recovery_percent']);
        });
    }
};
