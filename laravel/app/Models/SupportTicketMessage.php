<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
