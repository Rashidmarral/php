<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    public const CHANNELS = ['platform' => 'To platform support', 'company' => 'From client'];
    public const STATUSES = ['open' => 'Open', 'pending' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed'];
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    public const CATEGORIES = [
        'general' => 'General question',
        'billing' => 'Billing & subscription',
        'technical' => 'Technical issue',
        'feature_request' => 'Feature request',
        'project' => 'Project / job issue',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->orderBy('created_at');
    }

    public function isOpenState(): bool
    {
        return !in_array($this->status, ['resolved', 'closed'], true);
    }
}
