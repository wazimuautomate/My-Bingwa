<?php $snap = $snapshot; ?>
<div class="page-head">
  <div>
    <h1>Release v<?= (int) $release['version'] ?> <?= $isCurrent ? '<span class="tag sms">Current</span>' : '' ?></h1>
    <div class="sub">Published <?= e(fmt_nairobi($release['created_at'])) ?> by <?= e($release['published_by']) ?><?= $release['rolled_back_from'] ? ' · rollback of v' . (int) $release['rolled_back_from'] : '' ?></div>
  </div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/releases')) ?>">Back</a></div>
</div>

<div class="grid two">
  <div class="card">
    <div class="card__head"><h3>Changes in this version</h3></div>
    <?php if (!empty($items)): ?>
      <?php foreach ($items as $it): ?>
        <div class="diff-item">
          <span class="badge <?= e($it['change_type']) ?>"><?= e(ucfirst($it['change_type'])) ?></span>
          <span><span class="muted small"><?= e($it['entity_type']) ?></span> · <?= e($it['summary']) ?></span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="small muted">This was the first published version (baseline), or no per-item changes were recorded.</p>
    <?php endif; ?>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card__head"><h3>Integrity</h3></div>
      <div class="stack" style="gap:8px">
        <div class="between"><span class="muted small">Version</span><b>v<?= (int) $release['version'] ?></b></div>
        <div class="between"><span class="muted small">Schema</span><b><?= (int) $release['schema_version'] ?></b></div>
        <div class="between"><span class="muted small">Signature</span><span><?= ($release['signature'] ?? '') ? '<span class="status active">' . e($release['signature_algo']) . '</span>' : '<span class="status draft">Unsigned</span>' ?></span></div>
        <div class="field"><label class="small">SHA-256 checksum</label><input class="mono" type="text" value="<?= e($release['checksum']) ?>" readonly></div>
        <div class="row">
          <span class="tag muted">Offers: <?= count($snap['offers'] ?? []) ?></span>
          <span class="tag muted">Billboards: <?= count($snap['billboards'] ?? []) ?></span>
          <span class="tag muted">Templates: <?= count(array_merge($snap['templates']['delivery'] ?? [], $snap['templates']['lowBalance'] ?? [])) ?></span>
        </div>
      </div>
    </div>

    <?php if (can('rollback.execute') && !$isCurrent): ?>
      <div class="card">
        <div class="card__head"><?= icon('rollback', 18) ?><h3>Roll back to this version</h3></div>
        <p class="small muted">Copies this version's contents into the working draft and publishes it as a new, later version. Old versions are never modified.</p>
        <form method="post" action="<?= e(url('/releases/' . (int) $release['version'] . '/rollback')) ?>" data-once
              data-confirm="Roll back to v<?= (int) $release['version'] ?>? This creates a NEW version with these contents." data-confirm-title="Confirm rollback" data-reauth>
          <?= App\Core\Csrf::field() ?>
          <div class="field mb"><label>Reason (required)</label><input type="text" name="reason" required placeholder="Why are you rolling back?"></div>
          <button class="btn btn--warn btn--block" type="submit"><?= icon('rollback', 18) ?> Roll back</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
