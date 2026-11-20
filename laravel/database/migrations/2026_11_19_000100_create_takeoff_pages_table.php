<?php

use App\Support\TakeoffPageBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits a takeoff's single plan image into one-or-many "pages" (takeoff_pages), each with
 * its own calibration, so a multi-sheet PDF set (site plan, floor plans, elevations, details)
 * can be uploaded as one takeoff instead of forcing one takeoff per sheet. See
 * TakeoffPdfConverter for how a PDF becomes a page-per-sheet image set.
 *
 * takeoffs.plan_image_path/scale_px_per_unit/scale_unit are intentionally left in place (not
 * dropped) so a rollback stays simple, but they are now vestigial: every row created from here
 * on gets its image(s)/scale on takeoff_pages instead, and this migration's own data-migration
 * step below is the only thing that ever reads those three columns again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('takeoff_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('takeoff_id')->index();
            $table->unsignedInteger('page_number');
            $table->string('image_path', 255);
            $table->decimal('scale_px_per_unit', 12, 6)->default(1);
            $table->string('scale_unit', 10)->default('m');
            $table->timestamps();
        });

        Schema::table('takeoff_measurements', function (Blueprint $table) {
            $table->unsignedBigInteger('takeoff_page_id')->nullable()->after('takeoff_id')->index();
        });

        // Data-migrate every existing single-image takeoff into a page_number=1 row (carrying
        // over its now-vestigial plan_image_path/scale_px_per_unit/scale_unit) and backfill
        // every existing measurement onto that page. See TakeoffPageBackfill's own docblock
        // for why this is a separate, directly-testable method rather than inline here.
        TakeoffPageBackfill::run();
    }

    public function down(): void
    {
        Schema::table('takeoff_measurements', function (Blueprint $table) {
            $table->dropColumn('takeoff_page_id');
        });
        Schema::dropIfExists('takeoff_pages');
    }
};
