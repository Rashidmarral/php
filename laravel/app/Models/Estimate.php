<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estimate extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'markup_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'accepted_total' => 'decimal:2',
            'signed_at' => 'datetime:Y-m-d H:i:s',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EstimateTemplate::class, 'template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EstimateItem::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * True while an internal approval (opt-in per company) is outstanding or
     * was refused — the estimate must not reach the client in either state.
     * 'not_required' (companies that never opted in) and 'approved' are the
     * only states that may be sent/viewed publicly.
     */
    public function isApprovalBlocked(): bool
    {
        return in_array($this->approval_status, ['pending', 'rejected'], true);
    }

    /**
     * True once the quote's validity window has passed with the client
     * still undecided — an already-accepted or already-declined estimate is
     * never "expired" regardless of the date, since its outcome is already
     * settled and the date only ever governed whether it could still be
     * signed.
     */
    public function isExpired(): bool
    {
        return self::isExpiredRow(['valid_until' => $this->valid_until, 'status' => $this->status]);
    }

    /**
     * Same rule as isExpired() above, usable against a plain row array (e.g.
     * the raw query-builder rows the estimates list renders) rather than a
     * hydrated model — avoids re-querying/hydrating each row just to check
     * its expiry for a list badge.
     */
    public static function isExpiredRow(array $row): bool
    {
        return !empty($row['valid_until'])
            && \Illuminate\Support\Carbon::parse($row['valid_until'])->endOfDay()->isPast()
            && !in_array($row['status'] ?? null, ['accepted', 'declined'], true);
    }
}
