<?php

namespace App\Models;


class Page extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'show_in_nav' => 'boolean',
            'show_in_footer' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public static function forNav(): \Illuminate\Support\Collection
    {
        return static::where('is_published', true)->where('show_in_nav', true)
            ->orderBy('nav_order')->orderBy('title_en')->get();
    }

    public static function forFooter(): \Illuminate\Support\Collection
    {
        return static::where('is_published', true)->where('show_in_footer', true)
            ->orderBy('nav_order')->orderBy('title_en')->get();
    }
}
