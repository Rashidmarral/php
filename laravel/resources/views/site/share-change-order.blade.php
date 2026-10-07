@extends('layouts.site')

@section('content')
<section class="section" style="padding-top:48px;">
  <div class="container" style="max-width:760px;">

    @if (session('flash.error'))
      @foreach ((array) session('flash.error') as $m)
        <div class="alert alert-error">{{ $m }}</div>
      @endforeach
    @endif
    @if (session('flash.success'))
      @foreach ((array) session('flash.success') as $m)
        <div class="alert alert-success">{{ $m }}</div>
      @endforeach
    @endif
    <?php session()->forget(['flash.error', 'flash.success']); ?>

    <div class="card" style="padding:32px;">
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;gap:14px;align-items:center;">
          @if(!empty($companyLogoUrl))
            <img src="{{ $companyLogoUrl }}" alt="{{ $company['name'] ?? '' }}" style="height:48px;width:auto;border-radius:6px;">
          @endif
          <div>
            <div class="eyebrow">Change order from {{ $company['name'] ?? '' }}</div>
            <h1 style="margin-top:4px;">{{ $changeOrder['title'] }}</h1>
            <p class="help-text">Prepared for {{ $client['name'] ?? 'you' }} · {{ $changeOrder['created_at'] }}</p>
          </div>
        </div>
        <span class="badge badge-{{ $changeOrder['signed_at'] ? 'green' : 'yellow' }}" style="font-size:13px;padding:6px 14px;">{{ $changeOrder['signed_at'] ? 'Signed' : 'Awaiting signature' }}</span>
      </div>

      @if(!empty($changeOrder['description']))
        <p class="help-text" style="margin-top:14px;">{{ $changeOrder['description'] }}</p>
      @endif

      @if(count($items))
        <table class="data" style="margin-top:24px;">
          <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Unit price</th><th>Total</th></tr></thead>
          <tbody>
            @foreach ($items as $it)
              <tr><td>{{ $it['description'] }}</td><td>{{ $it['qty'] }}</td><td>{{ $it['unit'] ?: '—' }}</td><td>{!! money((float)$it['unit_price']) !!}</td><td>{!! money((float)$it['total']) !!}</td></tr>
            @endforeach
          </tbody>
        </table>
      @endif

      <div class="total-row" style="margin-top:14px;">Amount: {!! money((float)$changeOrder['amount']) !!}</div>
      @if(!empty($changeOrder['time_impact_days']))
        <p class="help-text" style="margin-top:8px;">Time impact: {{ $changeOrder['time_impact_days'] }} day(s) added to the schedule.</p>
      @endif
    </div>

    @if($changeOrder['signed_at'])
      <div class="card" style="margin-top:20px;padding:32px;text-align:center;">
        <div style="font-size:36px;">✅</div>
        <h3>Signed and accepted</h3>
        <p class="help-text">Signed by <strong>{{ $changeOrder['signed_by_name'] }}</strong> on {{ $changeOrder['signed_at'] }}</p>
        @if(!empty($changeOrder['signature_data']))
          <img src="{{ $changeOrder['signature_data'] }}" alt="Signature" style="max-width:320px;border:1px solid var(--border);border-radius:8px;margin-top:10px;background:#fff;">
        @endif
      </div>
    @else
      <div class="card" style="margin-top:20px;padding:32px;">
        <h3>Review &amp; sign</h3>
        <p class="help-text">By signing below you're approving this change order and its impact on the project's cost{{ !empty($changeOrder['time_impact_days']) ? ' and schedule' : '' }}.</p>

        <form method="post" action="{{ url('/co/' . $changeOrder['share_token'] . '/sign') }}" id="sign-form">
          @csrf
          <input type="hidden" name="signature_data" id="signature-data-input">

          <div class="form-group">
            <label>Your full name</label>
            <input type="text" name="signed_by_name" required placeholder="Type your name">
          </div>

          <div class="form-group">
            <label>Signature</label>
            <canvas id="sig-pad" width="600" height="180" style="border:1px solid var(--border);border-radius:8px;width:100%;max-width:600px;height:180px;touch-action:none;background:#fff;"></canvas>
            <div style="margin-top:6px;">
              <button type="button" id="sig-clear" class="btn btn-sm btn-light">Clear signature</button>
            </div>
          </div>

          <div style="display:flex;gap:10px;margin-top:16px;">
            <button type="submit" id="sign-accept" class="btn btn-primary">✅ Sign &amp; Accept</button>
          </div>
        </form>
      </div>
    @endif
  </div>
</section>

@if(!$changeOrder['signed_at'])
<script>
(function() {
  const canvas = document.getElementById('sig-pad');
  const ctx = canvas.getContext('2d');
  const ratio = canvas.width / canvas.getBoundingClientRect().width || 1;
  let drawing = false;
  let hasSignature = false;

  function pos(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
  }

  canvas.addEventListener('pointerdown', (e) => {
    drawing = true;
    hasSignature = true;
    const p = pos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', (e) => {
    if (!drawing) return;
    const p = pos(e);
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#0a4d42';
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
  });
  ['pointerup', 'pointerleave', 'pointercancel'].forEach(evt => canvas.addEventListener(evt, () => { drawing = false; }));

  document.getElementById('sig-clear').addEventListener('click', () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasSignature = false;
  });

  const form = document.getElementById('sign-form');
  const sigInput = document.getElementById('signature-data-input');

  document.getElementById('sign-accept').addEventListener('click', (e) => {
    if (!hasSignature) {
      e.preventDefault();
      alert('Please draw your signature before submitting.');
      return;
    }
    sigInput.value = canvas.toDataURL('image/png');
  });
})();
</script>
@endif
@endsection
