<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCertificateLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contract_qty' => 'decimal:2',
            'contract_unit_price' => 'decimal:2',
            'contract_total' => 'decimal:2',
            'previous_cumulative_qty' => 'decimal:2',
            'cumulative_qty' => 'decimal:2',
            'this_period_qty' => 'decimal:2',
            'this_period_value' => 'decimal:2',
        ];
    }

    public function paymentCertificate(): BelongsTo
    {
        return $this->belongsTo(PaymentCertificate::class);
    }

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }
}
