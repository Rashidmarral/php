@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>{{ t('admin.certificates.page_title') }}</h1>
</div>
<p class="help-text" style="max-width:760px;margin-top:-8px;margin-bottom:20px;">
  {{ t('admin.certificates.intro_before') }} <a href="{{ url('/about') }}" target="_blank" rel="noopener">{{ t('admin.certificates.about_page_link') }}</a> —
  {{ t('admin.certificates.intro_after') }}
</p>

<div class="grid grid-2" style="align-items:start;">
  <div class="card">
    <h3>{{ t('admin.certificates.add_certificate') }}</h3>
    <form method="post" action="/admin/certificates" enctype="multipart/form-data">
      @csrf
      <div class="form-row">
        <div class="form-group"><label>{{ t('admin.certificates.title_en') }}</label><input type="text" name="title_en" required placeholder="Commercial Registration"></div>
        <div class="form-group"><label>{{ t('admin.certificates.title_ar') }}</label><input type="text" name="title_ar" dir="rtl" placeholder="السجل التجاري"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>{{ t('admin.certificates.issued_by_en') }}</label><input type="text" name="issuer_en" placeholder="Ministry of Commerce"></div>
        <div class="form-group"><label>{{ t('admin.certificates.issued_by_ar') }}</label><input type="text" name="issuer_ar" dir="rtl"></div>
      </div>
      <div class="form-group">
        <label>{{ t('admin.certificates.badge_image') }}</label>
        <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
        <p class="help-text">{{ t('admin.certificates.badge_image_hint') }}</p>
      </div>
      <div class="form-group"><label>{{ t('common.sort_order') }}</label><input type="number" name="sort_order" value="0" style="max-width:120px;"></div>
      <button type="submit" class="btn btn-primary">{{ t('admin.certificates.add_certificate') }}</button>
    </form>
  </div>

  <div class="card">
    <h3>{{ t('admin.certificates.published_badges', ['count' => $certificates->count()]) }}</h3>
    @if($certificates->isEmpty())
      <p class="help-text">{{ t('admin.certificates.none_yet') }}</p>
    @else
      <table class="data">
        <thead><tr><th></th><th>{{ t('common.title') }}</th><th>{{ t('admin.certificates.issuer') }}</th><th>{{ t('common.status') }}</th><th></th></tr></thead>
        <tbody>
        @foreach ($certificates as $cert)
          <tr>
            <td><img src="{{ $cert->image_path }}" alt="" style="height:32px;max-width:60px;object-fit:contain;"></td>
            <td>{{ $cert->title_en }}</td>
            <td class="help-text">{{ $cert->issuer_en }}</td>
            <td><span class="badge badge-{{ $cert->is_active ? 'green' : 'gray' }}">{{ $cert->is_active ? t('admin.certificates.visible') : t('admin.certificates.hidden') }}</span></td>
            <td style="display:flex;gap:6px;">
              <form method="post" action="/admin/certificates/{{ $cert->id }}/toggle"><?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-light">{{ $cert->is_active ? t('admin.certificates.hide') : t('admin.certificates.show') }}</button>
              </form>
              <form method="post" action="/admin/certificates/{{ $cert->id }}/delete" onsubmit="return confirm('{{ t('admin.certificates.remove_confirm') }}');"><?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger">{{ t('common.remove') }}</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
@endsection
