<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    public const TYPES = ['chat' => 'Online chat/video call', 'in_person' => 'In-person site visit'];
    public const STATUSES = ['requested', 'scheduled', 'completed', 'cancelled'];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date:Y-m-d',
            'scheduled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Consultations counted against this month's quota (anything not cancelled). */
    public static function usedThisMonth(int $companyId): int
    {
        return static::where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
