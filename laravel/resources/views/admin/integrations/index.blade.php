@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.integrations.title') ?></h1>
</div>
<p class="help-text" style="max-width:820px;margin-bottom:20px;"><?= t('admin.integrations.intro') ?></p>

<div class="grid grid-2">

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('admin.integrations.moyasar_title') ?></h3>
      <span class="badge badge-<?= $moyasarConfigured ? 'green' : 'gray' ?>"><?= $moyasarConfigured ? t('admin.integrations.connected') : t('admin.integrations.not_connected') ?></span>
    </div>
    <p class="help-text"><?= t('admin.integrations.moyasar_desc') ?></p>
    <p class="help-text"><strong><?= t('admin.integrations.where_to_get_it') ?></strong> <?= t('admin.integrations.moyasar_get_it_before') ?> <a href="https://moyasar.com" target="_blank" rel="noopener">moyasar.com</a><?= t('admin.integrations.moyasar_get_it_after') ?></p>
    <a href="/admin/settings/payments" class="btn btn-sm btn-outline"><?= t('admin.integrations.configure') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('admin.integrations.bank_transfer_title') ?></h3>
      <span class="badge badge-<?= $bankTransferEnabled ? 'green' : 'gray' ?>"><?= $bankTransferEnabled ? t('common.enabled') : t('common.disabled') ?></span>
    </div>
    <p class="help-text"><?= t('admin.integrations.bank_transfer_desc') ?></p>
    <a href="/admin/settings/payments" class="btn btn-sm btn-outline"><?= t('admin.integrations.configure') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('admin.integrations.whatsapp_title') ?></h3>
      <span class="badge badge-<?= $whatsappConfigured ? 'green' : 'gray' ?>"><?= $whatsappConfigured ? t('admin.integrations.connected') : t('admin.integrations.not_connected') ?></span>
    </div>
    <p class="help-text"><?= t('admin.integrations.whatsapp_desc') ?></p>
    <p class="help-text"><strong><?= t('admin.integrations.where_to_get_it') ?></strong> <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener"><?= t('admin.integrations.meta_for_developers') ?></a><?= t('admin.integrations.whatsapp_get_it_after') ?></p>
    <a href="/admin/settings/features" class="btn btn-sm btn-outline"><?= t('admin.integrations.configure') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('admin.integrations.smtp_title') ?></h3>
      <span class="badge badge-<?= $smtpConfigured ? 'green' : 'gray' ?>"><?= $smtpConfigured ? t('admin.integrations.connected') : t('admin.integrations.not_connected') ?></span>
    </div>
    <p class="help-text"><?= t('admin.integrations.smtp_desc') ?></p>
    <p class="help-text"><strong><?= t('admin.integrations.where_to_get_it') ?></strong> <?= t('admin.integrations.smtp_get_it') ?></p>
    <a href="/admin/settings/email" class="btn btn-sm btn-outline"><?= t('admin.integrations.configure') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('admin.integrations.zatca_title') ?></h3>
      <span class="badge badge-blue"><?= t('admin.integrations.per_company') ?></span>
    </div>
    <p class="help-text"><?= t('admin.integrations.zatca_desc') ?></p>
    <p class="help-text"><strong><?= t('admin.integrations.where_to_get_it') ?></strong> <?= t('admin.integrations.zatca_get_it_before') ?> <a href="https://fatoora.zatca.gov.sa" target="_blank" rel="noopener"><?= t('admin.integrations.zatca_fatoora_portal') ?></a> <?= t('admin.integrations.zatca_get_it_after') ?></p>
    <a href="/admin/companies" class="btn btn-sm btn-outline"><?= t('admin.integrations.go_to_companies') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('admin.integrations.sheets_title') ?></h3>
      <span class="badge badge-blue"><?= t('admin.integrations.per_company') ?></span>
    </div>
    <p class="help-text"><?= t('admin.integrations.sheets_desc') ?></p>
    <p class="help-text"><strong><?= t('admin.integrations.where_to_get_it') ?></strong> <?= t('admin.integrations.sheets_get_it') ?></p>
  </div>

</div>

@endsection
