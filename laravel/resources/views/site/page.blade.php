@extends('layouts.site')

@section('content')
<section class="section">
  <div class="container" style="max-width:820px;">
    <div class="section-head" style="text-align:start;">
      <h1>{{ app()->getLocale() === 'ar' ? $page->title_ar : $page->title_en }}</h1>
    </div>
    <div class="page-content">
      {!! app()->getLocale() === 'ar' ? $page->content_ar : $page->content_en !!}
    </div>
  </div>
</section>
@include('partials.custom-sections', ['page' => $page->slug])
@endsection
