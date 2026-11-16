<?php

namespace App\Models;

/**
 * In-app notification feed row. Company-wide (like AuditLog), NOT per-user:
 * every row belongs to one company and is visible to any user in that
 * company — there is no user_id/recipient column, by design (see the
 * notifications table migration).
 *
 * Rows are written by App\Support\Notifications::record(), called from
 * alongside every existing email/SMS send in that class — this table is
 * purely an additional, in-app channel and never replaces those sends.
 *
 * read_at is null for unread; markRead()/markAllRead() in
 * App\Http\Controllers\App\NotificationController set it.
 */
class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
