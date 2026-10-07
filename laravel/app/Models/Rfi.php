<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Project-scoped threaded Q&A, built on the exact SupportTicket/SupportTicketMessage
 * shape (see RfiMessage) — a question raised against a project (optionally referencing
 * a specific Document/drawing) that an assignee answers over a message thread.
 */
class Rfi extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    public const STATUSES = ['open' => 'Open', 'answered' => 'Answered', 'closed' => 'Closed'];

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

    public function raisedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RfiMessage::class, 'rfi_id')->orderBy('created_at');
    }

    /** Display number, e.g. "RFI-001" — zero-padded to 3 digits, same formatting as other sequential doc numbers in this app. */
    public function displayNumber(): string
    {
        return 'RFI-' . str_pad((string) $this->rfi_number, 3, '0', STR_PAD_LEFT);
    }
}
