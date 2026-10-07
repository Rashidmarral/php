<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Supplier-level version of the "entity has compliance documents with expiry dates" pattern
 * already used twice elsewhere: ComplianceDocument (company-level: CR, VAT, Zakat, GOSI...)
 * and TeamMemberDocument (worker-level: iqama, health certificate...). Same shape, same
 * expiringWithin()/reminder_sent_at convention, picked up by the same daily reminder job.
 */
class SupplierDocument extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date:Y-m-d',
            'reminder_sent_at' => 'datetime',
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

    public static function expiringWithin(int $companyId, int $days): \Illuminate\Support\Collection
    {
        $cutoff = now()->addDays($days)->format('Y-m-d');
        return static::query()
            ->where('company_id', $companyId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $cutoff)
            ->orderBy('expiry_date')
            ->get();
    }
}
