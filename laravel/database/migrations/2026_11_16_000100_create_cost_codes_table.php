<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A new, OPTIONAL cost-code dimension layered ADDITIVELY alongside the
 * existing flat EstimateItem.item_type / VendorBill.category model — see
 * ReportController::costVariance()'s docblock for the exact calculation this
 * must never change, and ReportController::costByCode()'s docblock for how
 * this table is used instead.
 *
 * Two levels only (division -> sub-code, e.g. "03 00 00 Concrete" ->
 * "03 30 00 Cast-in-Place Concrete"), CSI-MasterFormat-style numbering.
 * company_id null = a platform-wide standard code seeded once by
 * CostCodeSeeder and available to every company; non-null = a company's own
 * custom code, visible only to that company. No company ever has to use
 * this table at all — every FK column pointing here is nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('code', 20);
            $table->string('description', 150);
            $table->string('description_ar', 150)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->tinyInteger('level')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_codes');
    }
};
