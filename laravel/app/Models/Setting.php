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

    /**
     * The admin-configured display date format (Settings → General → Date Format), a raw PHP
     * date() format string (d/m/Y, m/d/Y, or Y-m-d). Only wired into a couple of business-facing
     * summary pages so far (see admin/company Settings-visible summaries) — broader adoption
     * across every ->format() call in the app is a documented follow-up, not done here.
     */
    public static function dateFormat(): string
    {
        $format = static::get('date_format', 'd/m/Y');
        return in_array($format, ['d/m/Y', 'm/d/Y', 'Y-m-d'], true) ? $format : 'd/m/Y';
    }

    /** '12' or '24' — stored for future use; no business-facing time display exists yet to wire it into. */
    public static function timeFormat(): string
    {
        return static::get('time_format', '24') === '12' ? '12' : '24';
    }

    /** Formats a Carbon/DateTime-ish value using the admin's configured date_format setting. */
    public static function formatDate(mixed $date): string
    {
        if (!$date) {
            return '';
        }
        $carbon = $date instanceof \Illuminate\Support\Carbon ? $date : \Illuminate\Support\Carbon::parse((string) $date);
        return $carbon->format(static::dateFormat());
    }

    /** Whether the public /register route should accept new signups (Settings → General → Allow new company registrations). */
    public static function allowsNewRegistrations(): bool
    {
        return static::get('allow_new_registrations', '1') !== '0';
    }

    /** All settings as a flat [key => value] map, for admin forms that pre-fill from the whole table. */
    public static function all($columns = ['*']): \Illuminate\Support\Collection
    {
        return static::query()->pluck('value', 'key');
    }
}
