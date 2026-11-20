@extends('layouts.site')

@section('content')
<section class="section" style="padding-top:56px;">
  <div class="container" style="max-width:760px;">
    <h1>{{ $heading }}</h1>
    <p class="help-text">Last updated: {{ date('F j, Y') }}</p>

    @if ($type === 'privacy')
      <p style="color:var(--muted);">
        This notice explains how {{ \App\Models\Setting::siteName() }} ("we," "us," "the platform") collects, uses, and
        protects personal data, in line with Saudi Arabia's Personal Data Protection Law (PDPL) and
        its implementing regulations. It covers both subscribing companies (our direct customers)
        and the individuals whose data those companies process through the platform (their clients,
        employees, and site contacts).
      </p>

      <h3 style="margin-top:28px;">What we collect</h3>
      <ul>
        <li><strong>Account data:</strong> name, email, phone, and password (hashed, never stored in plain text) for company staff and platform admins.</li>
        <li><strong>Company data:</strong> legal name, VAT/CR numbers, address, and uploaded registration documents.</li>
        <li><strong>Client data entered by a company:</strong> names, contact details, and project/financial records a company creates for its own clients.</li>
        <li><strong>Payment data:</strong> transaction records and references. Card details themselves are handled directly by our payment processor (Moyasar) — we never see or store full card numbers.</li>
        <li><strong>Usage data:</strong> standard web server logs (IP address, browser, timestamps) for security and troubleshooting.</li>
      </ul>

      <h3 style="margin-top:28px;">Why we process it</h3>
      <p style="color:var(--muted);">To provide the service a company subscribes to (estimating, invoicing, scheduling, e-invoicing compliance), to process payments, to communicate with account holders, and to meet our own legal obligations (e.g. VAT records, ZATCA e-invoicing requirements).</p>

      <h3 style="margin-top:28px;">Who we share it with</h3>
      <p style="color:var(--muted);">Only the processors necessary to run the service, and only the data each one needs: our payment gateway (Moyasar) for transactions, WhatsApp Business Platform (Meta) if a company enables automated WhatsApp notifications, an SMTP provider for transactional email if configured, and ZATCA itself for e-invoicing compliance once a company completes onboarding. We do not sell personal data.</p>

      <h3 style="margin-top:28px;">Your rights under PDPL</h3>
      <p style="color:var(--muted);">Subject to the exceptions set out in the PDPL, you have the right to: know what data is held about you, request a copy of it, request correction of inaccurate data, request deletion, withdraw consent where processing is based on it, and object to certain processing. To exercise these rights, contact your company (if you're their client or employee) or contact us directly using the details on our <a href="{{ url('/contact') }}">Contact page</a> if you're a company account holder.</p>

      <h3 style="margin-top:28px;">Data retention</h3>
      <p style="color:var(--muted);">We retain data for as long as an account is active, plus any period required by law (e.g. VAT/ZATCA invoice records). A company can request deletion of its account and associated data, subject to statutory retention requirements we must still meet.</p>

      <h3 style="margin-top:28px;">Data security</h3>
      <p style="color:var(--muted);">Passwords are hashed, all traffic should be served over HTTPS in production, every company's data is isolated from every other company's, and access to a company's records requires authentication scoped to that company.</p>

      <p style="color:var(--muted);margin-top:28px;font-size:13px;">
        This page is provided as a good-faith starting point, not legal advice — have it reviewed by
        counsel familiar with Saudi PDPL before relying on it for a live product handling real
        customer data.
      </p>
    @else
      <p style="color:var(--muted);">
        These Terms of Service govern use of the {{ \App\Models\Setting::siteName() }} platform by any company or
        individual that creates an account ("you"). By registering, you agree to these terms.
      </p>

      <h3 style="margin-top:28px;">The service</h3>
      <p style="color:var(--muted);">{{ \App\Models\Setting::siteName() }} provides construction estimating, project, and invoicing software on a subscription basis, billed monthly or yearly per the plan you choose at <a href="{{ url('/pricing') }}">/pricing</a>. Features available to your account depend on your subscribed plan.</p>

      <h3 style="margin-top:28px;">Your account</h3>
      <p style="color:var(--muted);">You're responsible for keeping your login credentials confidential and for all activity under your account. Notify us immediately if you suspect unauthorized access.</p>

      <h3 style="margin-top:28px;">Your data</h3>
      <p style="color:var(--muted);">You retain ownership of the data you enter into the platform (clients, projects, estimates, invoices). We process it only to provide the service, as described in our <a href="{{ url('/privacy') }}">Privacy Policy</a>.</p>

      <h3 style="margin-top:28px;">Payment &amp; cancellation</h3>
      <p style="color:var(--muted);">Subscriptions renew automatically for the billing cycle you selected unless cancelled. You can cancel at any time from Billing &amp; Subscription; access continues until the end of the current paid period.</p>

      <h3 style="margin-top:28px;">ZATCA compliance</h3>
      <p style="color:var(--muted);">Phase 1 QR codes are generated automatically. Phase 2 (Fatoora) reporting requires your company to complete onboarding with your own ZATCA account; the platform provides the technical integration, but compliance with tax obligations remains your responsibility as the taxpayer.</p>

      <h3 style="margin-top:28px;">Limitation of liability</h3>
      <p style="color:var(--muted);">The platform is provided "as is." We are not liable for indirect or consequential damages arising from use of the service, to the extent permitted by Saudi law.</p>

      <p style="color:var(--muted);margin-top:28px;font-size:13px;">
        This page is provided as a good-faith starting point, not legal advice — have it reviewed by
        counsel before relying on it for a live commercial product.
      </p>
    @endif
  </div>
</section>
@endsection
