<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'price_sync_last_at' => 'datetime',
            'trial_reminder_sent_at' => 'datetime',
            'client_portal_enabled' => 'boolean',
            'moyasar_enabled' => 'boolean',
            'default_markup_percent' => 'decimal:2',
            'default_retention_percent' => 'decimal:2',
            'zatca_last_icv' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function complianceDocuments(): HasMany
    {
        return $this->hasMany(ComplianceDocument::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function activeSubscription(): HasMany
    {
        return $this->hasMany(Subscription::class)->where('status', 'active');
    }
}
