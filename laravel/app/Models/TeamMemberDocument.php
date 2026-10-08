<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMemberDocument extends Model
{
    public const TYPES = [
        'iqama' => 'Iqama (Residency Permit)',
        'health_certificate' => 'Health Certificate',
        'passport' => 'Passport',
        'work_permit' => 'Work Permit',
        'driving_license' => 'Driving License',
        'other' => 'Other',
    ];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
