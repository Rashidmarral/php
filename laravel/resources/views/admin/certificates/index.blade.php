@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>Company Certificates</h1>
</div>
<p class="help-text" style="max-width:760px;margin-top:-8px;margin-bottom:20px;">
  Trust badges shown publicly on the marketing site's <a href="{{ url('/about') }}" target="_blank" rel="noopener">About page</a> —
  Commercial Registration, Chamber of Commerce membership, ZATCA compliance, ISO certification, etc.
  This is separate from the CR/VAT documents kept on file under Settings → Legal (those are private records, not public badges).
</p>

<div class="grid grid-2" style="align-items:start;">
  <div class="card">
    <h3>Add a certificate</h3>
    <form method="post" action="/admin/certificates" enctype="multipart/form-data">
      @csrf
      <div class="form-row">
        <div class="form-group"><label>Title (English)</label><input type="text" name="title_en" required placeholder="Commercial Registration"></div>
        <div class="form-group"><label>Title (Arabic)</label><input type="text" name="title_ar" dir="rtl" placeholder="السجل التجاري"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Issued by (English)</label><input type="text" name="issuer_en" placeholder="Ministry of Commerce"></div>
        <div class="form-group"><label>Issued by (Arabic)</label><input type="text" name="issuer_ar" dir="rtl"></div>
      </div>
      <div class="form-group">
        <label>Badge image</label>
        <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
        <p class="help-text">PNG, JPG, WEBP, or SVG, up to 3MB. A clean logo/badge on a transparent or white background looks best.</p>
      </div>
      <div class="form-group"><label>Sort order</label><input type="number" name="sort_order" value="0" style="max-width:120px;"></div>
      <button type="submit" class="btn btn-primary">Add certificate</button>
    </form>
  </div>

  <div class="card">
    <h3>Published badges ({{ $certificates->count() }})</h3>
    @if($certificates->isEmpty())
      <p class="help-text">No certificates added yet — nothing will show on the About page until you add one.</p>
    @else
      <table class="data">
        <thead><tr><th></th><th>Title</th><th>Issuer</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($certificates as $cert)
          <tr>
            <td><img src="{{ $cert->image_path }}" alt="" style="height:32px;max-width:60px;object-fit:contain;"></td>
            <td>{{ $cert->title_en }}</td>
            <td class="help-text">{{ $cert->issuer_en }}</td>
            <td><span class="badge badge-{{ $cert->is_active ? 'green' : 'gray' }}">{{ $cert->is_active ? 'Visible' : 'Hidden' }}</span></td>
            <td style="display:flex;gap:6px;">
              <form method="post" action="/admin/certificates/{{ $cert->id }}/toggle"><?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-light">{{ $cert->is_active ? 'Hide' : 'Show' }}</button>
              </form>
              <form method="post" action="/admin/certificates/{{ $cert->id }}/delete" onsubmit="return confirm('Remove this certificate badge?');"><?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
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
