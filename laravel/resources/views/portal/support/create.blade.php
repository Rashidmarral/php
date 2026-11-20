@extends('layouts.portal')

@section('content')
<div class="page-head">
  <h1>New support request</h1>
  <a href="/portal/support" class="btn btn-light btn-sm">← Back</a>
</div>

<div class="card" style="max-width:640px;">
  <form method="post" action="/portal/support" enctype="multipart/form-data">
    @csrf
    <div class="form-group"><label>Subject</label><input type="text" name="subject" required></div>
    <div class="form-group">
      <label>Category</label>
      <select name="category">
        @foreach ($categories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
      </select>
    </div>
    <div class="form-group"><label>Describe the issue</label><textarea name="message" rows="6" required></textarea></div>
    <div class="form-group"><label>Attach a photo or file (optional)</label><input type="file" name="attachment" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
    <button type="submit" class="btn btn-primary">Submit</button>
  </form>
</div>
@endsection
