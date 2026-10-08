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
            <div class="eyebrow">Payment Certificate from {{ $company['name'] ?? '' }}</div>
            <h1 style="margin-top:4px;">Certificate #{{ $certificate['certificate_number'] }}</h1>
            <p class="help-text">Prepared for {{ $client['name'] ?? 'you' }} · {{ $certificate['certificate_date'] }}</p>
          </div>
        </div>
        <span class="badge badge-{{ $certificate['signed_at'] ? 'green' : 'yellow' }}" style="font-size:13px;padding:6px 14px;">{{ $certificate['signed_at'] ? 'Signed' : 'Awaiting signature' }}</span>
      </div>

      @if($certificate['period_from'] || $certificate['period_to'])
        <p class="help-text" style="margin-top:14px;">Period: {{ $certificate['period_from'] }} &ndash; {{ $certificate['period_to'] }}</p>
      @endif

      <table class="data" style="margin-top:24px;">
        <thead><tr><th>Description</th><th>UOM</th><th>Qty this period</th><th>Value this period</th></tr></thead>
        <tbody>
          @foreach ($lines as $line)
            <tr><td>{{ $line['description'] }}</td><td>{{ $line['uom'] }}</td><td>{{ $line['this_period_qty'] }}</td><td>{!! money((float)$line['this_period_value']) !!}</td></tr>
          @endforeach
        </tbody>
      </table>
      <div class="breakdown" style="margin-top:14px;max-width:320px;margin-inline-start:auto;font-size:14px;">
        <div><span>Gross claim</span><span>{!! money((float)$certificate['gross_amount']) !!}</span></div>
        <div><span>Retention ({{ $certificate['retention_percent'] }}%)</span><span>{!! money((float)$certificate['retention_amount']) !!}</span></div>
        @if((float)$certificate['advance_recovery_amount'] > 0)
          <div><span>Advance recovery</span><span>{!! money((float)$certificate['advance_recovery_amount']) !!}</span></div>
        @endif
      </div>
      <div class="total-row" style="margin-top:8px;">Net payable: {!! money((float)$certificate['net_payable']) !!}</div>
    </div>

    @if($certificate['signed_at'])
      <div class="card" style="margin-top:20px;padding:32px;text-align:center;">
        <div style="font-size:36px;">✅</div>
        <h3>Signed off</h3>
        <p class="help-text">Signed by <strong>{{ $certificate['signed_by_name'] }}</strong> on {{ $certificate['signed_at'] }}</p>
        @if(!empty($certificate['signature_data']))
          <img src="{{ $certificate['signature_data'] }}" alt="Signature" style="max-width:320px;border:1px solid var(--border);border-radius:8px;margin-top:10px;background:#fff;">
        @endif
      </div>
    @else
      <div class="card" style="margin-top:20px;padding:32px;">
        <h3>Review &amp; sign</h3>
        <p class="help-text">By signing below you're acknowledging this payment certificate's claimed amount.</p>

        <form method="post" action="{{ url('/ipc/' . $certificate['share_token'] . '/sign') }}" id="sign-form">
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
            <button type="submit" id="sign-accept" class="btn btn-primary">✅ Sign</button>
          </div>
        </form>
      </div>
    @endif
  </div>
</section>

@if(!$certificate['signed_at'])
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
