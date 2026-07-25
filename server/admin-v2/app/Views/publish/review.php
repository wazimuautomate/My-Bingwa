<div class="page-head">
  <div>
    <h1>Review &amp; publish</h1>
    <div class="sub">Compare the working draft with the live app and publish an immutable, signed version.</div>
  </div>
  <div class="page-head__actions">
    <span class="chip"><span class="dot"></span>Live: v<?= (int) ($current['version'] ?? 0) ?></span>
  </div>
</div>

<div class="grid two">
  <div class="stack">
    <?php if (!empty($errors)): ?>
      <div class="alert error"><div><b>Publishing is blocked.</b><ul style="margin:6px 0 0 16px">
        <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
      </ul></div></div>
    <?php endif; ?>
    <?php if (!empty($warnings)): ?>
      <div class="alert warning"><div><b>Warnings (publishing allowed):</b><ul style="margin:6px 0 0 16px">
        <?php foreach ($warnings as $wn): ?><li><?= e($wn) ?></li><?php endforeach; ?>
      </ul></div></div>
    <?php endif; ?>

    <div class="card">
      <div class="card__head"><h2>Draft changes</h2><span class="spacer"></span><span class="tag muted"><?= count($pending) ?> change(s)</span></div>
      <?php if (!empty($pending)): ?>
        <?php foreach ($pending as $it): ?>
          <div class="diff-item">
            <span class="badge <?= e($it['change_type']) ?>"><?= e(ucfirst($it['change_type'])) ?></span>
            <span><span class="muted small"><?= e($it['entity_type']) ?></span> · <?= e($it['summary']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty"><?= icon('check', 32) ?><h3>Nothing to publish</h3><p>The app already matches the working configuration.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h3>Publish</h3></div>
    <?php if (can('publish.execute')): ?>
      <form method="post" action="<?= e(url('/publish/execute')) ?>" data-once data-confirm="Publish these changes to a new immutable version? The app will sync it in the background." data-confirm-title="Publish configuration">
        <?= App\Core\Csrf::field() ?>
        <div class="field mb">
          <label>Release notes <span class="muted small">(optional)</span></label>
          <textarea name="notes" placeholder="What changed and why"></textarea>
        </div>
        <button class="btn btn--block" type="submit" <?= !empty($errors) || empty($pending) ? 'disabled' : '' ?>><?= icon('publish', 18) ?> Publish now</button>
        <?php if (empty($pending)): ?><p class="small muted mt">There are no changes to publish.</p><?php endif; ?>
      </form>
    <?php else: ?>
      <div class="alert info">You can review drafts but do not have permission to publish. Ask a Super Admin or a Publisher to publish.</div>
    <?php endif; ?>
    <div class="mt small muted">
      <p><?= icon('shield', 14) ?> Each publish creates a checksummed<?= \App\Core\Signer::isConfigured() ? ' and signed' : '' ?> snapshot and an audit record. Rollback creates a new later version — old versions are never changed.</p>
      <?php if (!\App\Core\Signer::isConfigured()): ?>
        <div class="alert warning mt"><div>Snapshot signing key is not configured — releases are checksum-only (marked unsigned). See the deployment guide to enable signing.</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>
