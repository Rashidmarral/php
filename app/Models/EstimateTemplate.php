<?php

namespace App\Models;

use App\Core\Model;

class EstimateTemplate extends Model
{
    protected static string $table = 'estimate_templates';

    public static function active(): array
    {
        return static::query('SELECT * FROM estimate_templates WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
    }
}
