<?php

namespace App\Models;

use App\Core\Model;

class Consultation extends Model
{
    protected static string $table = 'consultations';

    public const TYPES = ['chat' => 'Online chat/video call', 'in_person' => 'In-person site visit'];
    public const STATUSES = ['requested', 'scheduled', 'completed', 'cancelled'];

    /** Consultations counted against this month's quota (anything not cancelled). */
    public static function usedThisMonth(int $companyId): int
    {
        $monthStart = date('Y-m-01 00:00:00');
        return (int) static::count(
            "company_id = ? AND status != 'cancelled' AND created_at >= ?",
            [$companyId, $monthStart]
        );
    }
}
