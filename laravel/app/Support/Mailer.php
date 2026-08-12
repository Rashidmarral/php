<?php

namespace App\Support;

use App\Models\Setting;

/** SMTP transactional email. Send logic is ported in the business-logic phase; this covers what admin/user screens need today. */
class Mailer
{
    public static function isConfigured(): bool
    {
        return Setting::get('smtp_enabled') === '1'
            && trim((string) Setting::get('smtp_host', '')) !== ''
            && trim((string) Setting::get('smtp_username', '')) !== '';
    }
}
