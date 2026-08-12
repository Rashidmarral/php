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
}
