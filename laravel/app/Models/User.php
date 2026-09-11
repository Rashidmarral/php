<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    /** Roles assignable to a company team member by the owner/admin. 'owner' is fixed to the account creator. */
    public const ASSIGNABLE_ROLES = [
        'admin' => 'Admin — full access except billing',
        'estimator' => 'Estimator — projects, clients, estimates & schedule',
        'accountant' => 'Accountant — invoices, payments & reports',
        'viewer' => 'Viewer — read-only access',
    ];

    public const ROLE_LABELS = ['owner' => 'Owner'] + self::ASSIGNABLE_ROLES;

    public const ROLE_SHORT_LABELS = [
        'owner' => 'Owner', 'admin' => 'Admin', 'estimator' => 'Estimator',
        'accountant' => 'Accountant', 'viewer' => 'Viewer',
    ];

    /** Platform admin roles (distinct from the company-panel roles above). Stored in the same `users` table. */
    public const ADMIN_ROLES = [
        'super_admin' => 'Super Admin — full control',
        'support_admin' => 'Support Admin — read-only, cannot make changes',
    ];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'name', 'email', 'password', 'role', 'status',
        'national_id', 'nationality', 'bank_iban', 'bank_name',
        'basic_salary', 'housing_allowance', 'other_earnings',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'basic_salary' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'other_earnings' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /** Read-only admin: can browse the whole admin panel but every mutating action is blocked. */
    public function isSupportAdmin(): bool
    {
        return $this->role === 'support_admin';
    }

    public function isAdminStaff(): bool
    {
        return $this->isSuperAdmin() || $this->isSupportAdmin();
    }

    public function isCompanyOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst((string) $this->role);
    }

    public function roleShortLabel(): string
    {
        return self::ROLE_SHORT_LABELS[$this->role] ?? ucfirst((string) $this->role);
    }

    /** A WPS export needs at least a basic salary to produce a real (non-zero) row for this member. */
    public function hasPayrollData(): bool
    {
        return $this->basic_salary !== null;
    }

    /** Basic wage + housing allowance + other earnings — the gross wage a WPS salary file reports before deductions. */
    public function grossWage(): float
    {
        return (float) $this->basic_salary + (float) $this->housing_allowance + (float) $this->other_earnings;
    }
}
