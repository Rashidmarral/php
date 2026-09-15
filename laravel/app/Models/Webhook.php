<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    /** Events a company can subscribe a webhook URL to. */
    public const EVENTS = [
        'estimate.created' => 'Estimate created',
        'estimate.signed' => 'Estimate signed by client',
        'invoice.created' => 'Invoice created',
        'invoice.paid' => 'Invoice paid',
        'payment_certificate.certified' => 'Payment certificate certified',
    ];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];
    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function eventsList(): array
    {
        return json_decode((string) $this->events, true) ?: [];
    }
}
