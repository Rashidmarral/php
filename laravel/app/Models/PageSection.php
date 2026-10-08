<?php

namespace App\Models;

class PageSection extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public const TYPES = [
        'feature_grid' => 'Feature grid (icon + title + text, repeated)',
        'text_block' => 'Text block (heading + paragraph)',
        'cta_banner' => 'Call-to-action banner (heading + text + button)',
        'stat_row' => 'Stat row (number + label, repeated)',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function title(): string
    {
        return app()->getLocale() === 'ar' && $this->title_ar ? $this->title_ar : (string) $this->title_en;
    }

    public function subtitle(): string
    {
        return app()->getLocale() === 'ar' && $this->subtitle_ar ? $this->subtitle_ar : (string) $this->subtitle_en;
    }

    public function body(): string
    {
        return app()->getLocale() === 'ar' && $this->body_ar ? $this->body_ar : (string) $this->body_en;
    }

    public function buttonText(): string
    {
        return app()->getLocale() === 'ar' && $this->button_text_ar ? $this->button_text_ar : (string) $this->button_text_en;
    }
}
