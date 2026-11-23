<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('quick_estimate_addons', 'qty_mode')) {
            Schema::table('quick_estimate_addons', function (Blueprint $table) {
                // 'area'   = price is per m² and multiplies automatically by the total area
                // 'manual' = price is per ton/unit/linear-m/etc — the user enters the quantity
                $table->string('qty_mode', 10)->default('area')->after('unit_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quick_estimate_addons', 'qty_mode')) {
            Schema::table('quick_estimate_addons', function (Blueprint $table) {
                $table->dropColumn('qty_mode');
            });
        }
    }
};
