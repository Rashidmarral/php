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
}
