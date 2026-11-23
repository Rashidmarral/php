<?php

namespace App\Models;

use App\Support\Feature;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
            'zatca_icv_counter' => 'integer',
            'zatca_sync_b2b' => 'boolean',
            'zatca_sync_b2c' => 'boolean',
            'zatca_linked_at' => 'datetime',
            'zatca_last_sync_at' => 'datetime',
            'feature_overrides' => 'array',
        ];
    }

    /**
     * The company-level analog of User::permissionOverride(): returns true/false when this
     * ONE company's feature_overrides map has an explicit entry for $key (a super-admin
     * escape hatch overriding the company's subscribed plan for a one-off deal, pilot, or
     * support case), or null when there is no override — meaning the caller must fall
     * through to whatever the plan's feature_flags says, exactly as if this feature didn't
     * exist. See App\Support\Feature::allows()/allowsForCompany(), the only callers.
     */
    public function featureOverride(string $key): ?bool
    {
        $overrides = $this->feature_overrides;
        if (!is_array($overrides) || !array_key_exists($key, $overrides)) {
            return null;
        }
        $value = $overrides[$key];
        return $value === null ? null : (bool) $value;
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

    public function zatcaEnvironmentCredentials(): HasMany
    {
        return $this->hasMany(ZatcaEnvironmentCredential::class);
    }

    /**
     * The fields switchZatcaEnvironment() moves between this company's own
     * flat zatca_* columns (the "currently active environment" cache
     * every other read site depends on) and that environment's save slot
     * in zatca_environment_credentials. Keys are this table's own column
     * names; values are the matching Company column name. icv_counter is
     * included deliberately — it's scoped per environment exactly like
     * the PIH hash chain, so it must be stashed/restored the same way, or
     * switching environments could hand the one being entered a counter
     * left over from the one being left.
     *
     * @return array<string, string>
     */
    private function zatcaEnvironmentSlotFields(): array
    {
        return [
            'onboarding_status' => 'zatca_status',
            'egs_serial' => 'zatca_egs_serial',
            'common_name' => 'zatca_common_name',
            'organization_unit_name' => 'zatca_organization_unit_name',
            'business_category' => 'zatca_business_category',
            'csr' => 'zatca_csr',
            'private_key' => 'zatca_private_key',
            'compliance_request_id' => 'zatca_compliance_request_id',
            'compliance_csid' => 'zatca_compliance_csid',
            'compliance_secret' => 'zatca_compliance_secret',
            'production_request_id' => 'zatca_production_request_id',
            'production_csid' => 'zatca_production_csid',
            'production_secret' => 'zatca_production_secret',
            'last_invoice_hash' => 'zatca_last_invoice_hash',
            'linked_at' => 'zatca_linked_at',
            'last_sync_at' => 'zatca_last_sync_at',
            'icv_counter' => 'zatca_icv_counter',
        ];
    }

    /**
     * Switches this company's "currently active" ZATCA environment
     * without losing either environment's onboarding progress: the
     * environment being left has its live flat zatca_* columns saved into
     * its own zatca_environment_credentials row, then the row for
     * $newEnvironment (or a blank 'not_started'/0 default, if it has
     * never been onboarded) is loaded back onto those same flat columns.
     *
     * Ported from Daftri's Company::switchZatcaEnvironment() — field
     * names adapted to BuildXact's own zatca_* columns via
     * zatcaEnvironmentSlotFields() above. Only fills the in-memory model
     * (via fill(), same as the original) — the caller is responsible for
     * save()/update() once it has also set whatever else it needs to
     * persist (see CompanyZatcaController::updateSettings(), the only
     * caller), exactly as Daftri's version leaves it too.
     */
    public function switchZatcaEnvironment(string $newEnvironment): void
    {
        if ($newEnvironment === $this->zatca_environment) {
            return;
        }

        $fields = $this->zatcaEnvironmentSlotFields();

        ZatcaEnvironmentCredential::updateOrCreate(
            ['company_id' => $this->id, 'environment' => $this->zatca_environment],
            collect($fields)->mapWithKeys(fn ($companyField, $slotField) => [$slotField => $this->{$companyField}])->all()
        );

        $slot = ZatcaEnvironmentCredential::where('company_id', $this->id)->where('environment', $newEnvironment)->first();

        $this->fill(
            collect($fields)->mapWithKeys(fn ($companyField, $slotField) => [
                $companyField => match ($slotField) {
                    'onboarding_status' => $slot?->onboarding_status ?? 'not_started',
                    'icv_counter' => $slot?->icv_counter ?? 0,
                    default => $slot?->{$slotField},
                },
            ])->all()
        );
        $this->zatca_environment = $newEnvironment;
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

    public function approvalChainSteps(): HasMany
    {
        return $this->hasMany(ApprovalChainStep::class);
    }

    /**
     * This company's configured multi-step approval chain for one document
     * type ('estimate'|'invoice'), ordered by step_order. Empty for the
     * (large majority of) companies that never configured one — callers
     * must treat an empty collection as "no chain, use the original
     * single-step approve_documents Gate flow", never as an error.
     */
    public function approvalChainFor(string $documentType): Collection
    {
        return $this->approvalChainSteps()
            ->where('document_type', $documentType)
            ->orderBy('step_order')
            ->get();
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

    public function invoiceTemplates(): HasMany
    {
        return $this->hasMany(InvoiceTemplate::class);
    }

    /**
     * The customized InvoiceTemplate a company has set as its default for one
     * document type (e.g. 'invoice', 'estimate'), or null when it hasn't set
     * one up for that type yet. Null is the normal, expected result for a
     * company that never customized this document type — it is not an
     * error, and a later stage falls back to the old 6-preset system
     * (activeInvoiceTemplate() above) whenever this returns null, exactly
     * like it does today for every company.
     */
    public function activeInvoiceTemplateFor(string $documentType): ?InvoiceTemplate
    {
        return $this->invoiceTemplates()
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->first();
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

    public function domains(): HasMany
    {
        return $this->hasMany(CompanyDomain::class);
    }

    /**
     * This company's first active custom domain hostname (there is normally only one), or
     * null when it has none configured — the platform's own URL keeps working regardless.
     * Used for branding lookups (login page / public layout header) once
     * ResolveCompanyDomain has already matched the visitor's Host to this company; this
     * helper itself does no request-host matching.
     */
    public function activeDomain(): ?string
    {
        return $this->domains()->where('is_active', true)->value('domain');
    }
}
