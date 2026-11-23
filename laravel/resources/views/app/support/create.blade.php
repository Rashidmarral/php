@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1>{{ t('user.support.new_ticket') }}</h1>
  <a href="/app/support" class="btn btn-light btn-sm">← {{ t('common.back') }}</a>
</div>

<div class="card" style="max-width:640px;">
  <form method="post" action="/app/support" enctype="multipart/form-data">
    @csrf
    <div class="form-group"><label>{{ t('user.support.subject') }}</label><input type="text" name="subject" required></div>
    <div class="form-row">
      <div class="form-group">
        <label>{{ t('user.support.category') }}</label>
        <select name="category">
          @foreach ($categories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
      </div>
      <div class="form-group">
        <label>{{ t('user.support.priority') }}</label>
        <select name="priority">
          @foreach ($priorities as $key => $label)<option value="{{ $key }}" {{ $key === 'normal' ? 'selected' : '' }}>{{ $label }}</option>@endforeach
        </select>
      </div>
    </div>
    <div class="form-group"><label>{{ t('user.support.message') }}</label><textarea name="message" rows="6" required placeholder="{{ t('user.support.message_hint') }}"></textarea></div>
    <div class="form-group"><label>{{ t('user.support.attachment') }}</label><input type="file" name="attachment" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
    <button type="submit" class="btn btn-primary">{{ t('user.support.submit') }}</button>
  </form>
</div>
@endsection
