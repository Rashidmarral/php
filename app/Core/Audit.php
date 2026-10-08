<?php

namespace App\Core;

use App\Models\AuditLog;

/** Records who (which admin) did what, for accountability once a platform has more than one admin. */
class Audit
{
    public static function log(string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
    {
        $admin = Auth::user();
        AuditLog::create([
            'admin_id' => $admin['id'] ?? null,
            'admin_name' => $admin['name'] ?? 'System',
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
