<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $table = 'translations';

    public $timestamps = true;
    const CREATED_AT = null;

    protected $fillable = ['locale', 'translation_key', 'value'];

    /** Returns [key => value] overrides for the given locale, for merging over the file-based defaults. */
    public static function overridesFor(string $locale): array
    {
        return static::query()
            ->where('locale', $locale)
            ->pluck('value', 'translation_key')
            ->all();
    }
}
