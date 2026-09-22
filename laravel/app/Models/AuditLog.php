<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function record(?User $admin, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
    {
        static::create([
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
            'ip_address' => request()?->ip(),
        ]);
    }
}
