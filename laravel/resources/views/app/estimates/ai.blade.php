@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <a href="/app/estimates/new" class="help-text"><?= t('user.estimates.back') ?></a>
    <h1 style="margin-top:6px;">✨ <?= t('user.estimates.ai_title') ?></h1>
  </div>
</div>

<?php if (!$aiConfigured): ?>
  <div class="alert" style="background:#fdf3e0;color:var(--warning);border:1px solid #e8c76b;">
    <?= t('user.estimates.ai_no_provider') ?>
  </div>
<?php endif; ?>

<div class="card" style="max-width:760px;">
  <form method="post" action="/app/estimates/ai/generate" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-group">
      <label><?= t('user.estimates.ai_describe_project') ?></label>
      <textarea name="description" id="ai-description" rows="5" placeholder="e.g. Remodel a 20m² kitchen. Remove 15 linear meters of upper cabinets and 14 linear meters of damaged base units. Install new quartz countertops and porcelain flooring."></textarea>
    </div>
    <div class="form-group">
      <label><?= t('user.estimates.ai_photo_label') ?></label>
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
      <p class="help-text" style="margin-top:6px;"><?= t('user.estimates.ai_photo_hint') ?></p>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.generate') ?></button>
  </form>

  <p class="help-text" style="margin-top:20px;margin-bottom:10px;"><?= t('user.estimates.ai_try_prompts') ?></p>
  <div class="grid grid-3" style="gap:10px;">
    <?php
    $prompts = [
        'Kitchen Remodel' => 'Remodel a 20m² kitchen. Remove 15 linear meters of upper cabinets and 14 linear meters of damaged base units. Demo existing countertops and flooring, then install new cabinetry, quartz countertops, and porcelain tile.',
        'Bathroom Remodel' => 'Remodel a 12m² bathroom. Remove cast-iron tub, 45m² of wall tile and 13m² of floor tile. Repipe for a new shower and vanity, then retile floor and walls.',
        'Whole House Build' => 'Construct a 223m² two-story house. Excavate site, pour concrete slab foundation, frame in 2x4 lumber, and complete roofing, MEP rough-in and finishes.',
        'Home Addition' => 'Build a 46m² family room addition to an existing house. Excavate and pour slab-on-grade foundation, frame walls in 2x4, tie into existing roof, and finish interior.',
        'Deck Construction' => 'Build a 28m² backyard deck. Set 6x6 pressure-treated posts in concrete, frame the deck structure, and install composite decking boards with railing.',
        'Landscaping' => 'Landscape a 74m² backyard. Excavate and grade site, lay 37m² of sod on prepared base, install irrigation, and add planting beds and pathways.',
        'Roofing Work' => 'Replace a 186m² asphalt shingle roof. Tear off existing shingles, underlayment and flashing, dispose to dumpster, then install new underlayment and shingles.',
        'Siding Installation' => 'Install 139m² of vinyl siding on a two-story house. Remove existing clapboard, dispose to dumpster, wrap walls with house wrap, and install new siding.',
        'Basement Renovation' => 'Finish a 56m² basement. Frame perimeter walls in 2x4, insulate to R13, and line with drywall — then add flooring and paint.',
    ];
    foreach ($prompts as $label => $prompt):
    ?>
      <button type="button" class="card feature-card prompt-chip" style="text-align:left;cursor:pointer;padding:14px;" data-prompt="<?= htmlspecialchars($prompt, ENT_QUOTES) ?>">
        <h3 style="font-size:14px;margin-bottom:4px;"><?= $label ?></h3>
        <p style="font-size:12.5px;margin:0;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;display:-webkit-box;"><?= htmlspecialchars($prompt) ?></p>
      </button>
    <?php endforeach; ?>
  </div>
</div>

<script>
document.querySelectorAll('.prompt-chip').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.getElementById('ai-description').value = btn.dataset.prompt;
    document.getElementById('ai-description').scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
});
</script>

@endsection
