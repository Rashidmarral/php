<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The one-time data-migration the 2026_11_19_000100_create_takeoff_pages_table migration runs
 * to turn every pre-existing single-image takeoff into a page_number=1 TakeoffPage row (and
 * point its measurements at it). Pulled out of the migration into its own method purely so a
 * test can call it directly against a manually-inserted "legacy-style" row (bypassing the
 * Takeoff/TakeoffMeasurement models, to simulate data written before this column/table
 * existed) without re-running Schema::create()/Schema::table() on tables the real migration
 * already created — see TakeoffPageBackfillTest.
 */
class TakeoffPageBackfill
{
    public static function run(): void
    {
        $now = now();

        DB::table('takeoffs')
            ->whereNotNull('plan_image_path')
            ->chunkById(200, function ($takeoffs) use ($now) {
                $rows = [];
                foreach ($takeoffs as $t) {
                    $rows[] = [
                        'takeoff_id' => $t->id,
                        'page_number' => 1,
                        'image_path' => $t->plan_image_path,
                        'scale_px_per_unit' => $t->scale_px_per_unit,
                        'scale_unit' => $t->scale_unit,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows !== []) {
                    DB::table('takeoff_pages')->insert($rows);
                }
            });

        DB::table('takeoff_measurements')
            ->whereNull('takeoff_page_id')
            ->chunkById(200, function ($measurements) {
                $takeoffIds = $measurements->pluck('takeoff_id')->unique()->values()->all();
                $pageIdsByTakeoff = DB::table('takeoff_pages')
                    ->whereIn('takeoff_id', $takeoffIds)
                    ->where('page_number', 1)
                    ->pluck('id', 'takeoff_id');

                foreach ($measurements as $m) {
                    $pageId = $pageIdsByTakeoff[$m->takeoff_id] ?? null;
                    if ($pageId) {
                        DB::table('takeoff_measurements')->where('id', $m->id)->update(['takeoff_page_id' => $pageId]);
                    }
                }
            });
    }
}
