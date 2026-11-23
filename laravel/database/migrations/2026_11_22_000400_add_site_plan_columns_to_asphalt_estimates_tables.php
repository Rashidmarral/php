<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the inputs that feed App\Support\AsphaltSitePlanLayout's parametric
 * site-plan drawing (see that class for the full layout math).
 *
 * asphalt_estimates.site_width_m/site_length_m: an optional overall site
 * boundary box — purely cosmetic/informational on the drawing (if either is
 * null, the drawing simply omits the boundary rectangle).
 *
 * asphalt_estimate_segments.is_main_road: which single segment is the
 * "spine" of the drawing (drawn vertically, down the middle of the canvas);
 * defaults false, and AsphaltEstimateController::parseSegments() enforces
 * that at most one submitted row is ever persisted as true.
 * plot_rows/plot_cols: an optional subdivision grid drawn flanking a cross
 * segment (e.g. residential plots either side of a cross street) — 0 means
 * "no grid for this segment".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asphalt_estimates', function (Blueprint $table) {
            $table->decimal('site_width_m', 10, 2)->nullable()->after('city');
            $table->decimal('site_length_m', 10, 2)->nullable()->after('site_width_m');
        });

        Schema::table('asphalt_estimate_segments', function (Blueprint $table) {
            $table->boolean('is_main_road')->default(false)->after('sort_order');
            $table->unsignedInteger('plot_rows')->default(0)->after('is_main_road');
            $table->unsignedInteger('plot_cols')->default(0)->after('plot_rows');
        });
    }

    public function down(): void
    {
        Schema::table('asphalt_estimate_segments', function (Blueprint $table) {
            $table->dropColumn(['is_main_road', 'plot_rows', 'plot_cols']);
        });

        Schema::table('asphalt_estimates', function (Blueprint $table) {
            $table->dropColumn(['site_width_m', 'site_length_m']);
        });
    }
};
