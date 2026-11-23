<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One road segment of an AsphaltEstimate (e.g. "Main Road", "Cross Street
 * 1", "Entrance Road") — its own geometry (length/width/median) and
 * pavement-layer structure (subbase/base-course/asphalt, each with a
 * thickness and unit cost). Every *_m2/*_m3/*_tons/*_cost/segment_total
 * column is a cached value computed by
 * App\Support\AsphaltEstimateCalc::computeSegment() — the controller
 * recomputes and re-saves them on every store()/update(), so they must
 * never be trusted as independently authoritative. Segments are replaced
 * wholesale on every update (delete-all-and-recreate from the submitted
 * array), the same spirit AssemblyController's destroy() uses for
 * AssemblyItem rows — there's no need to diff/patch individual segments.
 */
class AsphaltEstimateSegment extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'length_m' => 'decimal:2',
            'width_m' => 'decimal:2',
            'median_width_m' => 'decimal:2',
            'subbase_thickness_mm' => 'decimal:2',
            'subbase_unit_cost_per_m3' => 'decimal:2',
            'base_course_thickness_mm' => 'decimal:2',
            'base_course_unit_cost_per_m3' => 'decimal:2',
            'asphalt_binder_thickness_mm' => 'decimal:2',
            'asphalt_wearing_thickness_mm' => 'decimal:2',
            'asphalt_density_kg_per_m3' => 'decimal:2',
            'asphalt_unit_cost_per_ton' => 'decimal:2',
            'net_width_m' => 'decimal:2',
            'area_m2' => 'decimal:2',
            'subbase_volume_m3' => 'decimal:3',
            'base_course_volume_m3' => 'decimal:3',
            'asphalt_volume_m3' => 'decimal:3',
            'asphalt_tons' => 'decimal:2',
            'subbase_cost' => 'decimal:2',
            'base_course_cost' => 'decimal:2',
            'asphalt_cost' => 'decimal:2',
            'segment_total' => 'decimal:2',
            'is_main_road' => 'boolean',
            'plot_rows' => 'integer',
            'plot_cols' => 'integer',
        ];
    }

    public function asphaltEstimate(): BelongsTo
    {
        return $this->belongsTo(AsphaltEstimate::class);
    }
}
