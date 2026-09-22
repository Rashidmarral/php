<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Base model for the whole app. The original app stored every timestamp as a plain
 * 'Y-m-d H:i:s' string and views echo them raw, so array/JSON serialization needs to
 * match that instead of Eloquent's default ISO-8601-with-microseconds format. Date-only
 * columns (start_date, due_date, etc.) still need their own 'date:Y-m-d' cast — this only
 * covers plain 'datetime' casts and the automatic created_at/updated_at timestamps.
 */
abstract class Model extends EloquentModel
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
