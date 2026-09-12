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
            <div class="eyebrow">Estimate from {{ $company['name'] ?? '' }}</div>
            <h1 style="margin-top:4px;">{{ $estimate['title'] }}</h1>
            <p class="help-text">Prepared for {{ $client['name'] ?? 'you' }} · {{ $estimate['created_at'] }}</p>
          </div>
        </div>
        @if($isExpired)
          <span class="badge badge-red" style="font-size:13px;padding:6px 14px;">Expired</span>
        @else
          <span class="badge badge-{{ $estimate['status']==='accepted'?'green':($estimate['status']==='declined'?'red':'yellow') }}" style="font-size:13px;padding:6px 14px;">{{ ucfirst($estimate['status']) }}</span>
        @endif
      </div>

      <table class="data" style="margin-top:24px;">
        <thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>
        <tbody>
          @foreach ($items as $it)
            @if($it['showSection'])
              <tr style="background:#fafcfb;"><td colspan="4"><strong>{{ $it['sectionTitle'] }}</strong></td></tr>
            @endif
            <tr><td>{{ $it['description'] }}</td><td>{{ $it['qty'] }}</td><td>{!! money((float)$it['unit_price']) !!}</td><td>{!! money((float)$it['total']) !!}</td></tr>
          @endforeach
        </tbody>
      </table>
      <div class="breakdown" style="margin-top:14px;max-width:320px;margin-inline-start:auto;font-size:14px;">
        <div><span>Subtotal</span><span>{!! money($subtotal) !!}</span></div>
        @if($vatAmount > 0 || !empty($estimate['tax_rate_id']))
          <div><span>VAT ({{ $vatRate }}%)</span><span>{!! money($vatAmount) !!}</span></div>
        @endif
      </div>
      <div class="total-row" style="margin-top:8px;">Total: {!! money($total) !!}</div>

      <a href="{{ url('/e/' . $estimate['share_token'] . '/pdf') }}" target="_blank" class="btn btn-outline" style="margin-top:16px;">⬇ Download PDF</a>
    </div>

    @if($estimate['status'] === 'accepted')
      <div class="card" style="margin-top:20px;padding:32px;text-align:center;">
        <div style="font-size:36px;">✅</div>
        <h3>Signed and accepted</h3>
        <p class="help-text">Signed by <strong>{{ $estimate['signed_by_name'] }}</strong> on {{ $estimate['signed_at'] }}</p>
        @if(!empty($estimate['signature_data']))
          <img src="{{ $estimate['signature_data'] }}" alt="Signature" style="max-width:320px;border:1px solid var(--border);border-radius:8px;margin-top:10px;background:#fff;">
        @endif
      </div>
    @elseif($estimate['status'] === 'declined')
      <div class="card" style="margin-top:20px;padding:32px;text-align:center;">
        <div style="font-size:36px;">❌</div>
        <h3>Declined</h3>
        <p class="help-text">This estimate was declined. Contact {{ $company['name'] ?? 'the contractor' }} if this was a mistake.</p>
      </div>
    @elseif($isExpired)
      <div class="card" style="margin-top:20px;padding:32px;text-align:center;">
        <div style="font-size:36px;">⏰</div>
        <h3>This estimate has expired</h3>
        <p class="help-text">Its validity period has passed and it can no longer be signed. Contact {{ $company['name'] ?? 'the contractor' }} for an updated quote.</p>
      </div>
    @else
      <div class="card" style="margin-top:20px;padding:32px;">
        <h3>Review &amp; sign</h3>
        <p class="help-text">By signing below you're approving this estimate as the basis for the project.</p>

        <form method="post" action="{{ url('/e/' . $estimate['share_token'] . '/sign') }}" id="sign-form">
          @csrf
          <input type="hidden" name="decision" id="decision-input" value="accept">
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
            <button type="submit" id="sign-decline" class="btn btn-light" formnovalidate>Decline</button>
          </div>
        </form>
      </div>
    @endif
  </div>
</section>

@if(!in_array($estimate['status'], ['accepted', 'declined'], true) && !$isExpired)
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
  const decisionInput = document.getElementById('decision-input');
  const sigInput = document.getElementById('signature-data-input');

  document.getElementById('sign-accept').addEventListener('click', (e) => {
    if (!hasSignature) {
      e.preventDefault();
      alert('Please draw your signature before submitting.');
      return;
    }
    decisionInput.value = 'accept';
    sigInput.value = canvas.toDataURL('image/png');
  });
  document.getElementById('sign-decline').addEventListener('click', () => {
    decisionInput.value = 'decline';
  });
})();
</script>
@endif
@endsection
