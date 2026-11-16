@extends('layouts.app')

@section('content')
<div class="page-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
  <h1><?= t('user.notifications.title') ?></h1>
  <?php if ($notifications->contains(fn ($n) => !$n->read_at)): ?>
    <form method="post" action="/app/notifications/mark-all-read" style="margin:0;">
      @csrf
      <button type="submit" class="btn btn-light btn-sm"><?= t('user.notifications.mark_all_read') ?></button>
    </form>
  <?php endif; ?>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('user.notifications.hint') ?></p>

<div class="card" style="padding:0;">
  <?php foreach ($notifications as $n): ?>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border);<?= $n->read_at ? '' : 'background:var(--brand-light);' ?>">
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:8px;">
          <?php if (!$n->read_at): ?><span style="width:8px;height:8px;border-radius:50%;background:var(--danger);display:inline-block;flex-shrink:0;"></span><?php endif; ?>
          <?php if ($n->link): ?>
            <a href="<?= e($n->link) ?>" style="font-weight:600;"><?= e($n->title) ?></a>
          <?php else: ?>
            <span style="font-weight:600;"><?= e($n->title) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($n->message): ?>
          <p class="help-text" style="margin:4px 0 0;"><?= e($n->message) ?></p>
        <?php endif; ?>
        <p class="help-text" style="margin:4px 0 0;white-space:nowrap;"><?= e($n->created_at?->diffForHumans()) ?></p>
      </div>
      <?php if (!$n->read_at): ?>
        <form method="post" action="/app/notifications/<?= $n->id ?>/read" style="margin:0;flex-shrink:0;">
          @csrf
          <button type="submit" class="btn btn-sm btn-light"><?= t('user.notifications.mark_read') ?></button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($notifications->isEmpty()): ?>
    <p class="help-text" style="text-align:center;padding:30px;margin:0;"><?= t('user.notifications.no_notifications') ?></p>
  <?php endif; ?>
</div>
@include('app.partials.pagination', ['paginator' => $notifications])

@endsection
