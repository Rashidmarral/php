@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>{{ $config['label'] }}</h1>
  <div style="display:flex;gap:8px;">
    <a href="{{ $config['url'] }}" target="_blank" rel="noopener" class="btn btn-light btn-sm">{{ t('admin.content.view_live_page') }}</a>
    <a href="/admin/content" class="btn btn-light btn-sm">← {{ t('admin.content.all_pages') }}</a>
  </div>
</div>

<form method="post" action="/admin/content/{{ $page }}">
  @csrf
  @foreach ($fields as $f)
    <div class="card" style="margin-bottom:14px;">
      <label style="margin-bottom:10px;">{{ $f['label'] }}</label>
      <div class="form-row">
        <div class="form-group" style="margin:0;">
          <label class="help-text" style="font-weight:600;">{{ t('common.english') }}</label>
          @if($f['multiline'])
            <textarea name="en[{{ $f['key'] }}]" rows="3">{{ $f['en_value'] }}</textarea>
          @else
            <input type="text" name="en[{{ $f['key'] }}]" value="{{ $f['en_value'] }}">
          @endif
        </div>
        <div class="form-group" style="margin:0;">
          <label class="help-text" style="font-weight:600;">{{ t('common.arabic') }}</label>
          @if($f['multiline'])
            <textarea name="ar[{{ $f['key'] }}]" rows="3" dir="rtl">{{ $f['ar_value'] }}</textarea>
          @else
            <input type="text" name="ar[{{ $f['key'] }}]" value="{{ $f['ar_value'] }}" dir="rtl">
          @endif
        </div>
      </div>
    </div>
  @endforeach

  <button type="submit" class="btn btn-primary" style="position:sticky;bottom:16px;">{{ t('admin.content.save_all_changes') }}</button>
</form>
@endsection
