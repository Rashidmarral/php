<?php

namespace App\Models;


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

    public static function upsert(string $locale, string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['locale' => $locale, 'translation_key' => $key],
            ['value' => $value]
        );
    }

    /** Resets a key back to its file-based default by removing the DB override in both locales. */
    public static function deleteKey(string $key): void
    {
        static::query()->where('translation_key', $key)->delete();
    }
}
