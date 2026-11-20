<?php use App\Core\Lang; use App\Core\View; ?>
<section class="section">
  <div class="container" style="max-width:820px;">
    <div class="section-head" style="text-align:start;">
      <h1><?= View::e(Lang::locale() === 'ar' ? $page['title_ar'] : $page['title_en']) ?></h1>
    </div>
    <div class="page-content">
      <?= Lang::locale() === 'ar' ? $page['content_ar'] : $page['content_en'] ?>
    </div>
  </div>
</section>
