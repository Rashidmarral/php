<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An "Asphalt / Road Estimate" — a paving/road contractor's estimating tool,
 * parallel to QuickEstimate and Estimate, broken into one or more road
 * segments (see AsphaltEstimateSegment) plus a handful of project-wide cost
 * inputs (transport, plant rental, labor, fees). All computed totals here
 * are cached columns, recomputed by App\Support\AsphaltEstimateCalc and
 * re-saved every time the estimate is stored/updated — never trust a stale
 * read elsewhere. convertToEstimate() on the controller turns this into a
 * real Estimate + EstimateItem rows, tagged with the "32 10 00" (Paving)
 * cost code.
 */
class AsphaltEstimate extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'transport_cost_per_ton' => 'decimal:2',
            'paver_daily_rate' => 'decimal:2',
            'paver_days' => 'decimal:2',
            'roller_daily_rate' => 'decimal:2',
            'roller_days' => 'decimal:2',
            'labor_crew_size' => 'integer',
            'labor_days' => 'decimal:2',
            'labor_daily_rate_per_worker' => 'decimal:2',
            'labor_food_daily_rate_per_worker' => 'decimal:2',
            'surveyor_fee' => 'decimal:2',
            'lab_testing_fee' => 'decimal:2',
            'mobilization_fee' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'segments_subtotal' => 'decimal:2',
            'other_costs_subtotal' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'markup_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'total_asphalt_tons' => 'decimal:2',
            'total_area_m2' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(AsphaltEstimateSegment::class)->orderBy('sort_order')->orderBy('id');
    }
}
