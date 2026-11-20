<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in internal approval workflow: when a company enables one of these
 * flags, an owner/admin must approve a newly created estimate/invoice
 * before it can reach the client (see Estimate/Invoice approval_status).
 * Both default false so existing companies see no behavior change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('require_estimate_approval')->default(false)->after('client_portal_enabled');
            $table->boolean('require_invoice_approval')->default(false)->after('require_estimate_approval');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['require_estimate_approval', 'require_invoice_approval']);
        });
    }
};
