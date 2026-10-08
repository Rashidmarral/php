<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/** Site name lookup for error pages, which must render even if the DB itself is what's broken. */
class SafeSiteName
{
    public static function get(): string
    {
        try {
            return Setting::siteName();
        } catch (Throwable $e) {
            return 'BuildXact Saudi';
        }
    }
}
