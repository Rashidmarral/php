<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentCertificate extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'certificate_date' => 'date:Y-m-d',
            'period_from' => 'date:Y-m-d',
            'period_to' => 'date:Y-m-d',
            'retention_percent' => 'decimal:2',
            'retention_amount' => 'decimal:2',
            'advance_recovery_percent' => 'decimal:2',
            'advance_recovery_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'cumulative_certified' => 'decimal:2',
            'certified_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PaymentCertificateLine::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
