<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds cutout support to takeoff measurements: an "area" measurement (e.g. a bedroom floor)
 * can have one or more other "area" measurements (e.g. a closet nook, a window opening)
 * marked as cutouts of it via parent_measurement_id/is_cutout, loosely coupled exactly like
 * every other FK-ish column on this table (no DB-level FK constraint).
 *
 * gross_value is nullable and only ever populated once a measurement gets its first cutout —
 * it then holds the original, un-cut polygon area, while the existing value/total_cost
 * columns become the NET value (gross minus the sum of its cutouts' values). A measurement
 * with no cutouts keeps gross_value null and value as its own polygon area, exactly as
 * before this migration — fully backward compatible with every existing takeoff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('takeoff_measurements', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_measurement_id')->nullable()->after('takeoff_page_id')->index();
            $table->boolean('is_cutout')->default(false)->after('parent_measurement_id');
            $table->decimal('gross_value', 12, 3)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('takeoff_measurements', function (Blueprint $table) {
            $table->dropColumn(['parent_measurement_id', 'is_cutout', 'gross_value']);
        });
    }
};
