<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The supplier portal's review queue (see its creating migration's docblock): a supplier's
 * self-submitted bill lands here with status 'pending' and is never, by itself, a real cost
 * — only VendorBillController::approveSubmission() turning it into an actual VendorBill row
 * makes it count against Project::actualCostTotal()/the cost-variance and WIP reports.
 */
class VendorBillSubmission extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'bill_date' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(Subcontract::class);
    }

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
