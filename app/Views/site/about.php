<section class="hero" style="padding-bottom:40px;">
  <div class="container" style="grid-template-columns:1fr;max-width:820px;text-align:center;">
    <div>
      <span class="hero-badge">🇸🇦 <?= t('about.eyebrow') ?></span>
      <h1><?= t('about.title') ?></h1>
      <p class="lead" style="max-width:720px;margin-left:auto;margin-right:auto;"><?= t('about.lead') ?></p>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;padding-bottom:48px;">
  <div class="container">
    <div class="stat-row" style="max-width:760px;margin-left:auto;margin-right:auto;">
      <div><strong><?= t('about.stat1.value') ?></strong><span><?= t('about.stat1.label') ?></span></div>
      <div><strong><?= t('about.stat2.value') ?></strong><span><?= t('about.stat2.label') ?></span></div>
      <div><strong><?= t('about.stat3.value') ?></strong><span><?= t('about.stat3.label') ?></span></div>
      <div><strong><?= t('about.stat4.value') ?></strong><span><?= t('about.stat4.label') ?></span></div>
    </div>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container" style="max-width:820px;">
    <div class="section-head" style="text-align:start;">
      <div class="eyebrow"><?= t('about.story.eyebrow') ?></div>
      <h2><?= t('about.story.title') ?></h2>
    </div>
    <p style="color:var(--muted);font-size:15.5px;line-height:1.75;"><?= t('about.story.p1') ?></p>
    <p style="color:var(--muted);font-size:15.5px;line-height:1.75;margin-top:16px;"><?= t('about.story.p2') ?></p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-2" style="max-width:920px;margin:0 auto;">
      <div class="card feature-card">
        <div class="icon">🎯</div>
        <h3><?= t('about.mission.title') ?></h3>
        <p><?= t('about.mission.desc') ?></p>
      </div>
      <div class="card feature-card">
        <div class="icon">🔭</div>
        <h3><?= t('about.vision.title') ?></h3>
        <p><?= t('about.vision.desc') ?></p>
      </div>
    </div>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= t('about.values.eyebrow') ?></div>
      <h2><?= t('about.values.title') ?></h2>
    </div>
    <div class="grid grid-3">
      <div class="card feature-card"><div class="icon">🇸🇦</div><h3><?= t('about.value1.title') ?></h3><p><?= t('about.value1.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">🧾</div><h3><?= t('about.value2.title') ?></h3><p><?= t('about.value2.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">🏗️</div><h3><?= t('about.value3.title') ?></h3><p><?= t('about.value3.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">🤝</div><h3><?= t('about.value4.title') ?></h3><p><?= t('about.value4.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">🔒</div><h3><?= t('about.value5.title') ?></h3><p><?= t('about.value5.desc') ?></p></div>
      <div class="card feature-card"><div class="icon">🚀</div><h3><?= t('about.value6.title') ?></h3><p><?= t('about.value6.desc') ?></p></div>
    </div>
  </div>
</section>

<section class="section" style="border-top:1px solid var(--border);">
  <div class="container" style="text-align:center;">
    <h2><?= t('about.cta.title') ?></h2>
    <p style="color:var(--muted)"><?= t('about.cta.subtitle') ?></p>
    <div class="hero-actions" style="justify-content:center;margin-top:16px;">
      <a href="/register" class="btn btn-primary"><?= t('hero.cta_primary') ?></a>
      <a href="/quick-estimate" class="btn btn-outline"><?= t('nav.quick_estimate') ?></a>
    </div>
  </div>
</section>
