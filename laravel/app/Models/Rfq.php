<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Request-for-Quote: a company invites multiple suppliers to bid on the same scope
 * of work (its items), records each supplier's quote, and awards one — a documented
 * competitive-bid trail sitting alongside the single-vendor PurchaseOrder flow. Awarding
 * an RfqQuote never auto-creates a PurchaseOrder; that's a deliberate manual next step.
 */
class Rfq extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'comparing' => 'Comparing',
        'awarded' => 'Awarded',
        'cancelled' => 'Cancelled',
    ];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(RfqQuote::class);
    }

    /** Once awarded or cancelled, the RFQ is a closed record of what happened — no further edits. */
    public function isEditable(): bool
    {
        return !in_array($this->status, ['awarded', 'cancelled'], true);
    }
}
