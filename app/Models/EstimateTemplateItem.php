<?php

namespace App\Models;

use App\Core\Model;

class EstimateTemplateItem extends Model
{
    protected static string $table = 'estimate_template_items';

    public static function forTemplate(int $templateId): array
    {
        return static::query('SELECT * FROM estimate_template_items WHERE template_id = ? ORDER BY sort_order ASC, id ASC', [$templateId])->fetchAll();
    }
}
