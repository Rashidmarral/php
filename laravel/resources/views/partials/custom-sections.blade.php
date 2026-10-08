{{-- Admin-created sections for this page (Admin → Custom Sections). @include('partials.custom-sections', ['page' => 'home']) --}}
@php
  $customSections = \App\Models\PageSection::where('page_slug', $page)->where('is_active', true)->orderBy('sort_order')->get();
  $isArCs = app()->getLocale() === 'ar';
@endphp
@foreach ($customSections as $cs)
  @if($cs->section_type === 'feature_grid')
    <section class="section">
      <div class="container">
        @if($cs->title() || $cs->subtitle())
          <div class="section-head">
            @if($cs->title())<h2>{{ $cs->title() }}</h2>@endif
            @if($cs->subtitle())<p style="color:var(--muted)">{{ $cs->subtitle() }}</p>@endif
          </div>
        @endif
        <div class="grid grid-3 reveal-stagger">
          @foreach ($cs->items ?? [] as $item)
            <div class="card feature-card reveal">
              @if(!empty($item['icon']))<div class="icon">{{ $item['icon'] }}</div>@endif
              @if(!empty($item[$isArCs ? 'title_ar' : 'title_en']))<h3>{{ $item[$isArCs ? 'title_ar' : 'title_en'] }}</h3>@endif
              @if(!empty($item[$isArCs ? 'desc_ar' : 'desc_en']))<p>{{ $item[$isArCs ? 'desc_ar' : 'desc_en'] }}</p>@endif
            </div>
          @endforeach
        </div>
      </div>
    </section>
  @elseif($cs->section_type === 'text_block')
    <section class="section">
      <div class="container" style="max-width:760px;">
        <div class="reveal-section reveal">
          @if($cs->title())<h2 style="text-align:center;">{{ $cs->title() }}</h2>@endif
          @if($cs->subtitle())<p style="text-align:center;color:var(--muted);">{{ $cs->subtitle() }}</p>@endif
          @if($cs->body())<div class="page-content" style="margin-top:16px;">{!! nl2br(e($cs->body())) !!}</div>@endif
        </div>
      </div>
    </section>
  @elseif($cs->section_type === 'cta_banner')
    <section class="section" style="border-top:1px solid var(--border);">
      <div class="container reveal-section reveal" style="text-align:center;">
        @if($cs->title())<h2>{{ $cs->title() }}</h2>@endif
        @if($cs->subtitle())<p style="color:var(--muted)">{{ $cs->subtitle() }}</p>@endif
        @if($cs->buttonText() && $cs->button_url)
          <a href="{{ $cs->button_url }}" class="btn btn-primary" style="margin-top:10px;">{{ $cs->buttonText() }}</a>
        @endif
      </div>
    </section>
  @elseif($cs->section_type === 'stat_row')
    <section class="section" style="padding-top:0;">
      <div class="container reveal-section reveal">
        @if($cs->title() || $cs->subtitle())
          <div class="section-head">
            @if($cs->title())<h2>{{ $cs->title() }}</h2>@endif
            @if($cs->subtitle())<p style="color:var(--muted)">{{ $cs->subtitle() }}</p>@endif
          </div>
        @endif
        <div class="stat-row" style="max-width:760px;margin:0 auto;">
          @foreach ($cs->items ?? [] as $item)
            @continue(empty($item['value']))
            <div><strong class="count-up">{{ $item['value'] }}</strong><span>{{ $item[$isArCs ? 'label_ar' : 'label_en'] ?? '' }}</span></div>
          @endforeach
        </div>
      </div>
    </section>
  @endif
@endforeach
