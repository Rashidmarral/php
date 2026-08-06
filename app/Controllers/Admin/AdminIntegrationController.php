<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Settings;
use App\Core\WhatsApp;

class AdminIntegrationController extends Controller
{
    public function index(): void
    {
        $this->view('admin/integrations/index', [
            'pageTitle' => 'Integrations',
            'moyasarConfigured' => Settings::get('moyasar_enabled') === '1' && trim((string) Settings::get('moyasar_secret_key', '')) !== '',
            'bankTransferEnabled' => Settings::get('bank_transfer_enabled') === '1',
            'whatsappConfigured' => WhatsApp::isConfigured(),
            'smtpConfigured' => Mailer::isConfigured(),
        ], 'layouts/admin');
    }
}
