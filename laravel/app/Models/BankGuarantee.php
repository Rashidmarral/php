<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankGuarantee extends Model
{
    public const TYPES = [
        'advance_payment' => 'Advance Payment Guarantee',
        'performance_bond' => 'Performance Bond',
        'retention_guarantee' => 'Retention Guarantee',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'released' => 'Released',
        'claimed' => 'Claimed',
        'expired' => 'Expired',
    ];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'issue_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Guarantees still on risk (active) that expire within $days — released/claimed/expired ones don't need renewal reminders. */
    public static function expiringWithin(int $companyId, int $days): \Illuminate\Support\Collection
    {
        $cutoff = now()->addDays($days)->format('Y-m-d');
        return static::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $cutoff)
            ->orderBy('expiry_date')
            ->get();
    }
}
