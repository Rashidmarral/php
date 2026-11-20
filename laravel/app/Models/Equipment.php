<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A real equipment/fleet asset register entry — the genuine counterpart to the
 * cost-classification-only "equipment" string in VendorBill::CATEGORIES /
 * EstimateItem.item_type. Carries its own ownership/status lifecycle, a maintenance
 * history (see EquipmentMaintenanceLog) and an assignment/utilization history against
 * projects (see EquipmentAssignment) — structurally the same "register entity with a
 * documents/history sub-list" shape as Supplier.
 */
class Equipment extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    public const OWNERSHIP_TYPES = ['owned' => 'Owned', 'rented' => 'Rented'];

    public const STATUSES = ['available' => 'Available', 'in_use' => 'In Use', 'under_maintenance' => 'Under Maintenance', 'retired' => 'Retired'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date:Y-m-d',
            'purchase_cost' => 'decimal:2',
            'rental_cost_per_day' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(EquipmentMaintenanceLog::class)->orderByDesc('maintenance_date')->orderByDesc('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EquipmentAssignment::class)->orderByDesc('assigned_date')->orderByDesc('id');
    }

    /** The one currently-open (unreturned) assignment for this asset, or null if it isn't assigned anywhere right now. */
    public function activeAssignment(): ?EquipmentAssignment
    {
        return $this->assignments()->whereNull('returned_date')->first();
    }

    /** True once this asset has any maintenance or assignment history — the same "block delete, keep the record" rule ProjectController::destroy() applies to financial history. */
    public function hasHistory(): bool
    {
        return $this->maintenanceLogs()->exists() || $this->assignments()->exists();
    }
}
