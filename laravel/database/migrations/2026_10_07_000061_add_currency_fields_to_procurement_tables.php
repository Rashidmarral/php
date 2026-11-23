<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency support for the procurement/commitment side only (Supplier,
 * PurchaseOrder, Subcontract) — informational/commitment-tracking fields for a
 * foreign-currency vendor or subcontract, never a rewrite of how actual cost flows
 * into the budget. VendorBill (the single source of truth for "actual incurred
 * cost", see ReportController::costVariance()/Project::actualCostTotal()) and
 * Invoice/Estimate/ZATCA are deliberately untouched and stay SAR-only.
 *
 * Both columns default to SAR / 1.0, so every existing row (100% of today's data)
 * is a complete no-op everywhere a SAR-equivalent is computed (rate x amount =
 * amount when rate is 1.0).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['suppliers', 'purchase_orders', 'subcontracts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('currency', 3)->default('SAR');
                $t->decimal('exchange_rate_to_sar', 12, 4)->default(1.0);
            });
        }
    }

    public function down(): void
    {
        foreach (['suppliers', 'purchase_orders', 'subcontracts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['currency', 'exchange_rate_to_sar']);
            });
        }
    }
};
