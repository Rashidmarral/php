<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'budget' => 'decimal:2',
            'advance_payment_amount' => 'decimal:2',
            'advance_recovery_percent' => 'decimal:2',
            'defects_liability_end_date' => 'date:Y-m-d',
            'retention_reminder_sent_at' => 'datetime',
            'actual_completion_date' => 'date:Y-m-d',
            'ld_rate_per_day' => 'decimal:2',
            'ld_cap_percent' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class);
    }

    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class);
    }

    public function scheduleTasks(): HasMany
    {
        return $this->hasMany(ScheduleTask::class);
    }

    public function vendorBills(): HasMany
    {
        return $this->hasMany(VendorBill::class);
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }

    public function paymentCertificates(): HasMany
    {
        return $this->hasMany(PaymentCertificate::class);
    }

    public function subcontracts(): HasMany
    {
        return $this->hasMany(Subcontract::class);
    }

    public function extensionOfTimeRequests(): HasMany
    {
        return $this->hasMany(ExtensionOfTimeRequest::class);
    }

    /** Sum of every BOQ line's contract value — the total contract sum this project's certificates claim against. */
    public function boqContractValue(): float
    {
        return (float) $this->boqItems()->sum('total');
    }

    /** True once this project has at least one certificate (draft or certified) — the point past which BOQ qty/rate edits are locked, since a certificate has snapshotted numbers against the BOQ as it stood. */
    public function hasAnyPaymentCertificate(): bool
    {
        return $this->paymentCertificates()->exists();
    }

    /**
     * Retention still held on this project — summed across every one of its invoices
     * (regular invoices and IPC-certificate-generated ones alike, since certify() copies
     * the certificate's own retention_percent/retention_amount onto the Invoice it creates)
     * that carries retention and hasn't had it released yet.
     */
    public function retentionHeld(): float
    {
        return (float) Invoice::where('project_id', $this->id)
            ->where('retention_amount', '>', 0)
            ->where('retention_released', false)
            ->sum('retention_amount');
    }

    public function approvedChangeOrdersTotal(): float
    {
        return (float) $this->changeOrders()->where('status', 'approved')->sum('amount');
    }

    public function actualCostTotal(): float
    {
        return (float) $this->vendorBills()->sum('amount');
    }

    public function revisedBudget(): float
    {
        return (float) $this->budget + $this->approvedChangeOrdersTotal();
    }

    /**
     * The contract completion date after every APPROVED Extension of Time is applied —
     * pending/rejected requests never shift this. Null when end_date itself is null: there's
     * no baseline to push back in the first place.
     */
    public function effectiveCompletionDate(): ?\Carbon\Carbon
    {
        if (!$this->end_date) {
            return null;
        }
        $approvedDays = (int) $this->extensionOfTimeRequests()->where('status', 'approved')->sum('requested_days');
        return $this->end_date->copy()->addDays($approvedDays);
    }

    /**
     * Liquidated Damages exposure — an INFORMATIONAL estimate only (see ZakatController's own
     * docblock for this app's precedent on this kind of worksheet-not-transaction tool). This
     * never creates or feeds a real deduction on any invoice/certificate; it exists purely so a
     * contractor can see where they stand before negotiating with the client.
     *
     * Compares actual_completion_date (once the project has actually finished) — or today, while
     * still ongoing — against effectiveCompletionDate() (end_date shifted by approved EOT days).
     * Delay is floored at zero (never negative), and there's nothing to calculate once end_date
     * or ld_rate_per_day is missing.
     *
     * @return array{contractValue: float, effectiveCompletionDate: ?string, delayDays: int, rawLdAmount: float, cappedLdAmount: float, isCapped: bool}
     */
    public function ldExposure(): array
    {
        $contractValue = $this->boqItems()->exists() ? $this->boqContractValue() : (float) $this->budget;
        $effectiveCompletionDate = $this->effectiveCompletionDate();

        if (!$effectiveCompletionDate || !$this->ld_rate_per_day) {
            return [
                'contractValue' => $contractValue,
                'effectiveCompletionDate' => $effectiveCompletionDate?->format('Y-m-d'),
                'delayDays' => 0,
                'rawLdAmount' => 0.0,
                'cappedLdAmount' => 0.0,
                'isCapped' => false,
            ];
        }

        $referenceDate = $this->actual_completion_date ?? \Carbon\Carbon::today();
        // diffInDays' sign convention varies by direction of comparison, so the "is it even
        // delayed" check is done explicitly with gt() rather than trusting a signed diff.
        $delayDays = $referenceDate->greaterThan($effectiveCompletionDate)
            ? $effectiveCompletionDate->diffInDays($referenceDate)
            : 0;

        $rawLdAmount = $delayDays * (float) $this->ld_rate_per_day;
        $cappedLdAmount = $rawLdAmount;
        if ($this->ld_cap_percent !== null) {
            $capAmount = $contractValue * (float) $this->ld_cap_percent / 100;
            $cappedLdAmount = min($rawLdAmount, $capAmount);
        }

        return [
            'contractValue' => $contractValue,
            'effectiveCompletionDate' => $effectiveCompletionDate->format('Y-m-d'),
            'delayDays' => $delayDays,
            'rawLdAmount' => $rawLdAmount,
            'cappedLdAmount' => $cappedLdAmount,
            'isCapped' => $cappedLdAmount < $rawLdAmount,
        ];
    }
}
