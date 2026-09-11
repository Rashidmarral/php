<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Zakat ESTIMATE for internal planning — not a substitute for the actual Zakat
 * return filed through ZATCA's own portal. Uses the standard simplified "net
 * invested capital" method (equity + long-term liabilities, minus net fixed
 * assets and other deductions) x 2.5%/2.5775%.
 *
 * Unlike a full accounting system, BuildXact has no general ledger / chart of
 * accounts, so the equity_amount and deduction figures below are entered by hand
 * from the company's own accountant/audited financials — this model never
 * derives them from BuildXact's own invoice/vendor-bill/project data.
 */
class ZakatCalculation extends Model
{
    public const RATE_TYPES = [
        'hijri' => 'Hijri (2.5%)',
        'gregorian' => 'Gregorian (2.5775%)',
    ];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_end_date' => 'date:Y-m-d',
            'equity_amount' => 'decimal:2',
            'long_term_liabilities' => 'decimal:2',
            'net_fixed_assets' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'zakat_base' => 'decimal:2',
            'zakat_due' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Hijri years run ~354 days vs. the Gregorian ~365, so ZATCA scales the Gregorian rate up accordingly. */
    public static function rate(string $rateType): float
    {
        return $rateType === 'gregorian' ? 0.025775 : 0.025;
    }
}
