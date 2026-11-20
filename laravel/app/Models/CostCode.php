<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * CSI-MasterFormat-style cost code, two levels deep (a division, e.g.
 * "03 00 00 Concrete", optionally has children such as "03 30 00
 * Cast-in-Place Concrete"). This is a NEW, OPTIONAL dimension layered
 * ADDITIVELY alongside the existing flat EstimateItem.item_type /
 * VendorBill.category model — see ReportController::costVariance()'s
 * docblock for the calculation this must never change, and
 * ReportController::costByCode() for the new report built on top of this.
 *
 * company_id null = a platform-wide standard code seeded once by
 * CostCodeSeeder and readable by every company; non-null = a company's own
 * custom code, visible only to that company (see selectableFor()/
 * ownedById() for the exact scoping every caller must use).
 */
class CostCode extends Model
{
    public $timestamps = true;

    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CostCode::class, 'parent_id')->orderBy('sort_order');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Every top-level division (level 1) this company may use — the
     * platform-wide ones (company_id null) plus this company's own custom
     * divisions, if any — each with its children already eager-loaded, for
     * building a grouped "division -> sub-code" <select> or report tree in
     * one query round-trip.
     */
    public static function selectableFor(int $companyId): Collection
    {
        return self::whereNull('parent_id')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->get();
    }

    /**
     * Resolves a raw id from a request into a CostCode this company is
     * actually allowed to assign (platform-wide, or this company's own) —
     * never trust a raw cost_code_id from form input directly. Returns null
     * for a missing/invalid/not-owned id, which is exactly the "uncoded"
     * default every existing row already has.
     */
    public static function ownedById(?int $id, int $companyId): ?self
    {
        if (! $id) {
            return null;
        }
        $code = self::find($id);

        return ($code && ($code->company_id === null || $code->company_id === $companyId)) ? $code : null;
    }
}
