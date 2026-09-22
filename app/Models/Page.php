<?php

namespace App\Models;

use App\Core\Model;

class Page extends Model
{
    protected static string $table = 'pages';

    public static function findBySlug(string $slug): ?array
    {
        return static::first('slug', $slug);
    }

    public static function forNav(): array
    {
        return static::query(
            'SELECT * FROM pages WHERE is_published = 1 AND show_in_nav = 1 ORDER BY nav_order ASC, title_en ASC'
        )->fetchAll();
    }

    public static function forFooter(): array
    {
        return static::query(
            'SELECT * FROM pages WHERE is_published = 1 AND show_in_footer = 1 ORDER BY nav_order ASC, title_en ASC'
        )->fetchAll();
    }
}
