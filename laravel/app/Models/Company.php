<?php

namespace App\Models;

use App\Support\Feature;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * The 6 selectable visual PDF templates document.blade.php renders (see
     * App\Support\Pdf\PdfTemplateStyles) — the single source of truth for
     * validating both this company's stored default (activeInvoiceTemplate())
     * and the "activate" action on the Invoice Templates settings tab.
     */
    public const INVOICE_TEMPLATES = ['modern', 'classic', 'minimal', 'bold', 'elegant', 'saudi'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'price_sync_last_at' => 'datetime:Y-m-d H:i:s',
            'trial_reminder_sent_at' => 'datetime',
            'client_portal_enabled' => 'boolean',
            'moyasar_enabled' => 'boolean',
            'require_estimate_approval' => 'boolean',
            'require_invoice_approval' => 'boolean',
            'default_markup_percent' => 'decimal:2',
            'default_retention_percent' => 'decimal:2',
            'zatca_last_icv' => 'integer',
            'zatca_sync_b2b' => 'boolean',
            'zatca_sync_b2c' => 'boolean',
            'zatca_linked_at' => 'datetime',
            'zatca_last_sync_at' => 'datetime',
        ];
    }

    /**
     * True only once ZATCA has actually issued a production CSID for this
     * company — not just when zatca_status says 'onboarded'. Every sync
     * path (InvoiceController::submitZatca, ZatcaSyncService) goes through
     * this single choke point, mirroring Daftri's Company::isZatcaOnboarded().
     */
    public function isZatcaOnboarded(): bool
    {
        return $this->zatca_status === 'onboarded' && (bool) $this->zatca_production_csid;
    }

    /**
     * The credential clearance/reporting submissions must authenticate
     * with. ZATCA requires the production-CSID exchange in every
     * environment (developer, simulation, production) — the compliance
     * CSID from onboarding is only valid for the compliance-check call
     * itself and is rejected (401) if used for clearance/reporting.
     */
    public function zatcaCsidFor(): ?string
    {
        return $this->zatca_production_csid;
    }

    public function zatcaSecretFor(): ?string
    {
        return $this->zatca_production_secret;
    }

    /**
     * True only when this company has both opted in (require_estimate_approval)
     * and its plan still includes the approval_workflow feature — a downgraded
     * plan can't leave a stale toggle silently enforcing the workflow.
     */
    public function requiresEstimateApproval(): bool
    {
        return (bool) $this->require_estimate_approval && Feature::allowsForCompany('approval_workflow', $this);
    }

    public function requiresInvoiceApproval(): bool
    {
        return (bool) $this->require_invoice_approval && Feature::allowsForCompany('approval_workflow', $this);
    }

    /**
     * The PDF template every document-PDF action falls back to when a request has
     * no ?template= override — the company's choice from the Invoice Templates
     * settings tab, or 'modern' if it's somehow empty or not one of the 6 known
     * values (e.g. a stale value from before a template was ever removed).
     */
    public function activeInvoiceTemplate(): string
    {
        return in_array($this->invoice_template, self::INVOICE_TEMPLATES, true) ? $this->invoice_template : 'modern';
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
