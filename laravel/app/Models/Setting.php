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

    /** All settings as a flat [key => value] map, for admin forms that pre-fill from the whole table. */
    public static function all($columns = ['*']): \Illuminate\Support\Collection
    {
        return static::query()->pluck('value', 'key');
    }
}
