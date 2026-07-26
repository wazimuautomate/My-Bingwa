<?php
/**
 * Preview & publish. Shows ONLY what has changed since the last publish (the real
 * draft-vs-published diff). Unchanged offers/templates/settings are deliberately NOT
 * listed — they simply stay in their current state. Publishing sends the working
 * configuration to the app via the existing /publish/execute action.
 */
$hasErrors = !empty($errors);
$count = count($pending ?? []);
?>
<div class="page-head">
  <div>
    <h1>Preview &amp; publish</h1>
    <div class="sub">Only the changes you have made since the last publish are listed. Everything else stays as it is.</div>
  </div>
  <div class="page-head__actions">
    <span class="chip"><span class="dot"></span>Live: v<?= (int) ($current['version'] ?? 0) ?></span>
    <span class="chip">Next: v<?= (int) ($nextVersion ?? 0) ?></span>
  </div>
</div>

<?php if ($hasErrors): ?>
  <div class="alert error mb"><div><b>Publishing is blocked.</b><ul style="margin:6px 0 0 16px;text-align:left">
    <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
  </ul></div></div>
<?php endif; ?>
<?php if (!empty($warnings)): ?>
  <div class="alert warning mb"><div><b>Warnings (publishing still allowed):</b><ul style="margin:6px 0 0 16px;text-align:left">
    <?php foreach ($warnings as $wn): ?><li><?= e($wn) ?></li><?php endforeach; ?>
  </ul></div></div>
<?php endif; ?>

<div class="grid two">
  <div class="stack">
    <!-- Pending changes: the ONLY thing shown — the real draft-vs-published diff -->
    <div class="card">
      <div class="card__head"><h2>Changes to publish</h2><span class="spacer"></span><span class="tag muted"><?= $count ?> change<?= $count === 1 ? '' : 's' ?></span></div>
      <?php if ($count > 0): ?>
        <?php foreach ($pending as $it): ?>
          <div class="diff-item">
            <span class="badge <?= e($it['change_type']) ?>"><?= e(ucfirst($it['change_type'])) ?></span>
            <span><span class="muted small"><?= e($it['entity_type']) ?></span> · <?= e($it['summary']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty"><?= icon('check', 32) ?><h3>Nothing to publish</h3><p>The app already matches your current configuration. Change an offer, advert, template or setting and it will appear here.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <!-- Publish -->
    <div class="card">
      <div class="card__head"><h3>Publish</h3></div>
      <?php if (can('publish.execute')): ?>
        <form method="post" action="<?= e(url('/publish/execute')) ?>" data-once
              data-confirm="Publish these changes? The app will download them on its next background sync." data-confirm-title="Publish changes">
          <?= App\Core\Csrf::field() ?>
          <div class="field mb">
            <label>Notes <span class="muted small">(optional)</span></label>
            <textarea name="notes" placeholder="What changed and why"></textarea>
          </div>
          <button class="btn btn--block" type="submit" <?= $hasErrors || $count === 0 ? 'disabled' : '' ?>><?= icon('publish', 18) ?> Publish v<?= (int) ($nextVersion ?? 0) ?></button>
          <?php if ($count === 0): ?><p class="small muted mt">There are no changes to publish.</p><?php endif; ?>
        </form>
      <?php else: ?>
        <div class="alert info">You can preview changes but do not have permission to publish. Ask the Super Admin to publish.</div>
      <?php endif; ?>
      <div class="mt small muted"><p>Each publish gets a version number and date, and pushes only what you changed.</p></div>
      <div class="mt"><a class="btn btn--ghost btn--sm btn--block" href="<?= e(url('/releases')) ?>"><?= icon('versions', 16) ?> View release history</a></div>
    </div>
  </div>
</div>
