<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive-only FK columns threading the new, optional cost_codes hierarchy
 * onto the three tables that already carry the flat item_type/category
 * dimension. Nullable everywhere, so every existing row in every company's
 * data (and the existing cost-variance/budget calculations that read these
 * tables) is completely unaffected until a user explicitly assigns a code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_code_id')->nullable()->index();
        });
        Schema::table('boq_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_code_id')->nullable()->index();
        });
        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_code_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->dropColumn('cost_code_id');
        });
        Schema::table('boq_items', function (Blueprint $table) {
            $table->dropColumn('cost_code_id');
        });
        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->dropColumn('cost_code_id');
        });
    }
};
