{{-- Optional admin-uploaded hero background image/video for a marketing page. Include inside a `.hero` section as its first element: @include('partials.hero-media', ['page' => 'home']) --}}
@php
  $heroImage = \App\Models\Setting::get("hero_image_{$page}", '');
  $heroVideo = \App\Models\Setting::get("hero_video_{$page}", '');
@endphp
@if($heroVideo)
  <div class="hero-bg-media">
    @if(str_ends_with(parse_url($heroVideo, PHP_URL_PATH) ?? '', '.mp4'))
      <video src="{{ $heroVideo }}" autoplay muted loop playsinline></video>
    @else
      <iframe src="{{ $heroVideo }}" allow="autoplay; encrypted-media" allowfullscreen title="Background video"></iframe>
    @endif
  </div>
@elseif($heroImage)
  <div class="hero-bg-media"><img src="{{ $heroImage }}" alt=""></div>
@endif
