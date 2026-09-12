<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal approval state for the opt-in approval workflow (see the
 * companies table migration for the per-company toggle). Mirrors the
 * estimates table exactly. This never touches zatca_uuid/zatca_icv/
 * zatca_hash — the ZATCA chain keeps incrementing at creation time
 * regardless of approval state; approval only gates client-facing reach
 * of an already-numbered invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('not_required')->after('status');
            $table->unsignedBigInteger('approval_requested_by')->nullable()->after('approval_status');
            $table->timestamp('approval_requested_at')->nullable()->after('approval_requested_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approval_requested_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('rejection_reason', 255)->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'approval_status', 'approval_requested_by', 'approval_requested_at',
                'approved_by', 'approved_at', 'rejection_reason',
            ]);
        });
    }
};
