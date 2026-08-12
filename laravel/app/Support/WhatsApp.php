<?php

namespace App\Support;

use App\Models\Setting;

/** WhatsApp Cloud API notifications. Send logic is ported in the business-logic phase; this covers what admin/user screens need today. */
class WhatsApp
{
    public static function isConfigured(): bool
    {
        return Setting::get('whatsapp_enabled') === '1'
            && trim((string) Setting::get('whatsapp_phone_number_id', '')) !== ''
            && trim((string) Setting::get('whatsapp_access_token', '')) !== '';
    }
}
