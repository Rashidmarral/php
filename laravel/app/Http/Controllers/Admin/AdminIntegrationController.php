<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Mailer;
use App\Support\WhatsApp;
use Illuminate\View\View;

class AdminIntegrationController extends Controller
{
    public function index(): View
    {
        return view('admin.integrations.index', [
            'moyasarConfigured' => Setting::get('moyasar_enabled') === '1' && trim((string) Setting::get('moyasar_secret_key', '')) !== '',
            'bankTransferEnabled' => Setting::get('bank_transfer_enabled') === '1',
            'whatsappConfigured' => WhatsApp::isConfigured(),
            'smtpConfigured' => Mailer::isConfigured(),
        ]);
    }
}
