@extends('layouts.site')

@section('content')
<section class="section" style="padding-top:56px;padding-bottom:12px;">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('features.eyebrow') }}</div>
      <h1>{{ t('features.title') }}</h1>
      <p style="color:var(--muted)">{{ t('features.subtitle') }}</p>
    </div>
    <nav class="reveal" aria-label="{{ t('features.eyebrow') }}" style="display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:28px;">
      <a href="#estimating" class="btn btn-outline" style="padding:7px 16px;font-size:12.5px;">{{ t('featurespage.nav.estimating') }}</a>
      <a href="#delivery" class="btn btn-outline" style="padding:7px 16px;font-size:12.5px;">{{ t('featurespage.nav.delivery') }}</a>
      <a href="#financial" class="btn btn-outline" style="padding:7px 16px;font-size:12.5px;">{{ t('featurespage.nav.financial') }}</a>
      <a href="#team" class="btn btn-outline" style="padding:7px 16px;font-size:12.5px;">{{ t('featurespage.nav.team') }}</a>
      <a href="#vendors" class="btn btn-outline" style="padding:7px 16px;font-size:12.5px;">{{ t('featurespage.nav.vendors') }}</a>
      <a href="#reporting" class="btn btn-outline" style="padding:7px 16px;font-size:12.5px;">{{ t('featurespage.nav.reporting') }}</a>
      <a href="#compliance" class="btn btn-outline" style="padding:7px 16px;font-size:12.5px;">{{ t('featurespage.nav.compliance') }}</a>
    </nav>
  </div>
</section>

<section class="section reveal-section" id="estimating" style="padding-top:20px;">
  <div class="container">
    <div class="feature-category">
      <div class="feature-category-head">
        <div class="cat-icon">🧮</div>
        <h3>{{ t('featurespage.cat_estimating.title') }}</h3>
      </div>
      <p style="color:var(--muted);margin:-10px 0 20px;max-width:680px;">{{ t('featurespage.cat_estimating.desc') }}</p>
      <div class="feature-mini-grid reveal-stagger">
        <div class="feature-mini-card reveal"><div class="mini-icon">✨</div><div><h4>{{ t('featurespage.item.ai_estimate.title') }}</h4><p>{{ t('featurespage.item.ai_estimate.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">⚡</div><div><h4>{{ t('featurespage.item.quick_estimate.title') }}</h4><p>{{ t('featurespage.item.quick_estimate.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📐</div><div><h4>{{ t('featurespage.item.ai_takeoff.title') }}</h4><p>{{ t('featurespage.item.ai_takeoff.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📥</div><div><h4>{{ t('featurespage.item.boq_import.title') }}</h4><p>{{ t('featurespage.item.boq_import.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📋</div><div><h4>{{ t('featurespage.item.estimate_templates.title') }}</h4><p>{{ t('featurespage.item.estimate_templates.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📨</div><div><h4>{{ t('featurespage.item.rfq.title') }}</h4><p>{{ t('featurespage.item.rfq.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🧑‍💼</div><div><h4>{{ t('featurespage.item.bid_portal.title') }}</h4><p>{{ t('featurespage.item.bid_portal.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">✍️</div><div><h4>{{ t('featurespage.item.estimate_esign.title') }}</h4><p>{{ t('featurespage.item.estimate_esign.desc') }}</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section reveal-section" id="delivery" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container">
    <div class="feature-category">
      <div class="feature-category-head">
        <div class="cat-icon">🏗️</div>
        <h3>{{ t('featurespage.cat_delivery.title') }}</h3>
      </div>
      <p style="color:var(--muted);margin:-10px 0 20px;max-width:680px;">{{ t('featurespage.cat_delivery.desc') }}</p>
      <div class="feature-mini-grid reveal-stagger">
        <div class="feature-mini-card reveal"><div class="mini-icon">📅</div><div><h4>{{ t('featurespage.item.scheduling.title') }}</h4><p>{{ t('featurespage.item.scheduling.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📝</div><div><h4>{{ t('featurespage.item.site_log.title') }}</h4><p>{{ t('featurespage.item.site_log.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📸</div><div><h4>{{ t('featurespage.item.photo_diary.title') }}</h4><p>{{ t('featurespage.item.photo_diary.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🛠️</div><div><h4>{{ t('featurespage.item.punch_list.title') }}</h4><p>{{ t('featurespage.item.punch_list.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">❓</div><div><h4>{{ t('featurespage.item.rfi.title') }}</h4><p>{{ t('featurespage.item.rfi.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📑</div><div><h4>{{ t('featurespage.item.submittals.title') }}</h4><p>{{ t('featurespage.item.submittals.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🗂️</div><div><h4>{{ t('featurespage.item.doc_versions.title') }}</h4><p>{{ t('featurespage.item.doc_versions.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📍</div><div><h4>{{ t('featurespage.item.markup_viewer.title') }}</h4><p>{{ t('featurespage.item.markup_viewer.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🚜</div><div><h4>{{ t('featurespage.item.equipment.title') }}</h4><p>{{ t('featurespage.item.equipment.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🔄</div><div><h4>{{ t('featurespage.item.change_orders.title') }}</h4><p>{{ t('featurespage.item.change_orders.desc') }}</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section reveal-section" id="financial" style="padding-top:48px;">
  <div class="container">
    <div class="feature-category">
      <div class="feature-category-head">
        <div class="cat-icon">🧾</div>
        <h3>{{ t('featurespage.cat_financial.title') }}</h3>
      </div>
      <p style="color:var(--muted);margin:-10px 0 20px;max-width:680px;">{{ t('featurespage.cat_financial.desc') }}</p>
      <div class="feature-mini-grid reveal-stagger">
        <div class="feature-mini-card reveal"><div class="mini-icon">🧾</div><div><h4>{{ t('featurespage.item.zatca.title') }}</h4><p>{{ t('featurespage.item.zatca.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">↔️</div><div><h4>{{ t('featurespage.item.credit_debit_notes.title') }}</h4><p>{{ t('featurespage.item.credit_debit_notes.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🔂</div><div><h4>{{ t('featurespage.item.recurring_invoices.title') }}</h4><p>{{ t('featurespage.item.recurring_invoices.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">💵</div><div><h4>{{ t('featurespage.item.payment_certs.title') }}</h4><p>{{ t('featurespage.item.payment_certs.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🛒</div><div><h4>{{ t('featurespage.item.purchase_orders.title') }}</h4><p>{{ t('featurespage.item.purchase_orders.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🤝</div><div><h4>{{ t('featurespage.item.subcontractor_payments.title') }}</h4><p>{{ t('featurespage.item.subcontractor_payments.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🏦</div><div><h4>{{ t('featurespage.item.bank_guarantees.title') }}</h4><p>{{ t('featurespage.item.bank_guarantees.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">⏱️</div><div><h4>{{ t('featurespage.item.liquidated_damages.title') }}</h4><p>{{ t('featurespage.item.liquidated_damages.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">💰</div><div><h4>{{ t('featurespage.item.zakat.title') }}</h4><p>{{ t('featurespage.item.zakat.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">💱</div><div><h4>{{ t('featurespage.item.multi_currency.title') }}</h4><p>{{ t('featurespage.item.multi_currency.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📤</div><div><h4>{{ t('featurespage.item.accounting_export.title') }}</h4><p>{{ t('featurespage.item.accounting_export.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">💳</div><div><h4>{{ t('featurespage.item.online_payments.title') }}</h4><p>{{ t('featurespage.item.online_payments.desc') }}</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section reveal-section" id="team" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container">
    <div class="feature-category">
      <div class="feature-category-head">
        <div class="cat-icon">🤝</div>
        <h3>{{ t('featurespage.cat_team.title') }}</h3>
      </div>
      <p style="color:var(--muted);margin:-10px 0 20px;max-width:680px;">{{ t('featurespage.cat_team.desc') }}</p>
      <div class="feature-mini-grid reveal-stagger">
        <div class="feature-mini-card reveal"><div class="mini-icon">💼</div><div><h4>{{ t('featurespage.item.payroll.title') }}</h4><p>{{ t('featurespage.item.payroll.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">⏲️</div><div><h4>{{ t('featurespage.item.timesheets.title') }}</h4><p>{{ t('featurespage.item.timesheets.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🔐</div><div><h4>{{ t('featurespage.item.permissions.title') }}</h4><p>{{ t('featurespage.item.permissions.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🔑</div><div><h4>{{ t('featurespage.item.two_factor.title') }}</h4><p>{{ t('featurespage.item.two_factor.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">✅</div><div><h4>{{ t('featurespage.item.approval_chains.title') }}</h4><p>{{ t('featurespage.item.approval_chains.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🕵️</div><div><h4>{{ t('featurespage.item.activity_log.title') }}</h4><p>{{ t('featurespage.item.activity_log.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🔔</div><div><h4>{{ t('featurespage.item.notifications_center.title') }}</h4><p>{{ t('featurespage.item.notifications_center.desc') }}</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section reveal-section" id="vendors" style="padding-top:48px;">
  <div class="container">
    <div class="feature-category">
      <div class="feature-category-head">
        <div class="cat-icon">🚚</div>
        <h3>{{ t('featurespage.cat_vendors.title') }}</h3>
      </div>
      <p style="color:var(--muted);margin:-10px 0 20px;max-width:680px;">{{ t('featurespage.cat_vendors.desc') }}</p>
      <div class="feature-mini-grid reveal-stagger">
        <div class="feature-mini-card reveal"><div class="mini-icon">⭐</div><div><h4>{{ t('featurespage.item.supplier_mgmt.title') }}</h4><p>{{ t('featurespage.item.supplier_mgmt.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🧱</div><div><h4>{{ t('featurespage.item.material_library.title') }}</h4><p>{{ t('featurespage.item.material_library.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📃</div><div><h4>{{ t('featurespage.item.vendor_bills.title') }}</h4><p>{{ t('featurespage.item.vendor_bills.desc') }}</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section reveal-section" id="reporting" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container">
    <div class="feature-category">
      <div class="feature-category-head">
        <div class="cat-icon">📊</div>
        <h3>{{ t('featurespage.cat_reporting.title') }}</h3>
      </div>
      <p style="color:var(--muted);margin:-10px 0 20px;max-width:680px;">{{ t('featurespage.cat_reporting.desc') }}</p>
      <div class="feature-mini-grid reveal-stagger">
        <div class="feature-mini-card reveal"><div class="mini-icon">📊</div><div><h4>{{ t('featurespage.item.cost_dashboard.title') }}</h4><p>{{ t('featurespage.item.cost_dashboard.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🔢</div><div><h4>{{ t('featurespage.item.cost_codes.title') }}</h4><p>{{ t('featurespage.item.cost_codes.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📉</div><div><h4>{{ t('featurespage.item.cashflow_forecast.title') }}</h4><p>{{ t('featurespage.item.cashflow_forecast.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">📈</div><div><h4>{{ t('featurespage.item.visual_dashboards.title') }}</h4><p>{{ t('featurespage.item.visual_dashboards.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🔍</div><div><h4>{{ t('featurespage.item.global_search.title') }}</h4><p>{{ t('featurespage.item.global_search.desc') }}</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section reveal-section" id="compliance" style="padding-top:48px;">
  <div class="container">
    <div class="feature-category" style="margin-bottom:0;">
      <div class="feature-category-head">
        <div class="cat-icon">🛡️</div>
        <h3>{{ t('featurespage.cat_compliance.title') }}</h3>
      </div>
      <p style="color:var(--muted);margin:-10px 0 20px;max-width:680px;">{{ t('featurespage.cat_compliance.desc') }}</p>
      <div class="feature-mini-grid reveal-stagger">
        <div class="feature-mini-card reveal"><div class="mini-icon">🌐</div><div><h4>{{ t('featurespage.item.bilingual.title') }}</h4><p>{{ t('featurespage.item.bilingual.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🧑‍💻</div><div><h4>{{ t('featurespage.item.client_portal.title') }}</h4><p>{{ t('featurespage.item.client_portal.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🦺</div><div><h4>{{ t('featurespage.item.hse.title') }}</h4><p>{{ t('featurespage.item.hse.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">💾</div><div><h4>{{ t('featurespage.item.backups.title') }}</h4><p>{{ t('featurespage.item.backups.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">🔌</div><div><h4>{{ t('featurespage.item.webhooks.title') }}</h4><p>{{ t('featurespage.item.webhooks.desc') }}</p></div></div>
        <div class="feature-mini-card reveal"><div class="mini-icon">💬</div><div><h4>{{ t('featurespage.item.notification_channels.title') }}</h4><p>{{ t('featurespage.item.notification_channels.desc') }}</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--border);">
  <div class="container reveal-section reveal" style="text-align:center;">
    <h2>{{ t('cta.title') }}</h2>
    <p style="color:var(--muted)">{{ t('cta.subtitle') }}</p>
    <a href="{{ url('/register') }}" class="btn btn-primary" style="margin-top:10px;">{{ t('cta.button') }}</a>
  </div>
</section>
@include('partials.custom-sections', ['page' => 'features'])
@endsection
