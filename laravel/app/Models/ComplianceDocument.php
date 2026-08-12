<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceDocument extends Model
{
    public const TYPES = [
        'cr' => 'Commercial Registration (CR)',
        'vat' => 'VAT Certificate',
        'zakat' => 'Zakat Certificate',
        'gosi' => 'GOSI Certificate of Good Standing',
        'chamber' => 'Chamber of Commerce Membership',
        'nitaqat' => 'Nitaqat (Saudization) Certificate',
        'classification' => 'Contractor Classification Certificate',
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
