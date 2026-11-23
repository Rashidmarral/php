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

    /**
     * $companyId defaults to null, preserving every existing admin-panel call site's
     * behavior exactly — those are platform-level actions (e.g. "admin approved a
     * cross-company payment") that are never scoped to one company. Tenant-level
     * callers should use recordForCompany() below instead of passing $companyId here
     * directly, so company_id is never accidentally left off a tenant row.
     */
    public static function record(?User $admin, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null, ?int $companyId = null): void
    {
        static::create([
            'company_id' => $companyId,
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * Tenant-level (company-user) activity logging: a company's own Activity Log page
     * (App\Http\Controllers\App\ActivityLogController) filters strictly
     * `where('company_id', ...)` for tenant isolation, so every row it must ever show
     * has to carry a real company_id. This stamps it from the acting user in one call,
     * rather than leaving each call site to remember `$user->company_id` itself.
     */
    public static function recordForCompany(User $user, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
    {
        static::record($user, $action, $targetType, $targetId, $details, $user->company_id);
    }
}
