<?php

namespace App\Models;


class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** The platform's brand/product name as set by the admin (Settings → General → Site Name) — use this everywhere the brand name is shown, instead of hardcoding it. */
    public static function siteName(): string
    {
        return static::get('site_name', 'BuildXact Saudi') ?: 'BuildXact Saudi';
    }

    /** All settings as a flat [key => value] map, for admin forms that pre-fill from the whole table. */
    public static function all($columns = ['*']): \Illuminate\Support\Collection
    {
        return static::query()->pluck('value', 'key');
    }
}
