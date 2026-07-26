<?php
/**
 * Preview & publish. Everything below is the REAL working snapshot from
 * PublishingService::buildWorkingSnapshot() — no placeholder data. Publishing sends
 * exactly this to the app via the existing /publish/execute action.
 */
$snap        = $snapshot ?? [];
$offers      = $snap['offers'] ?? [];
$billboards  = $snap['billboards'] ?? [];
$templates   = $snap['templates'] ?? ['delivery' => [], 'lowBalance' => []];
$allTemplates = array_merge($templates['delivery'] ?? [], $templates['lowBalance'] ?? []);
$support     = $snap['support'] ?? [];
$appConfig   = $snap['appConfig'] ?? [];
$version     = $snap['version'] ?? [];
$hasSupport  = ($support['tillNumber'] ?? '') !== '' || ($support['paybillNumber'] ?? '') !== '';
?>
<div class="page-head">
  <div>
    <h1>Preview &amp; publish</h1>
    <div class="sub">The exact working configuration the app will receive. Review it, then publish.</div>
  </div>
  <div class="page-head__actions">
    <span class="chip"><span class="dot"></span>Live: v<?= (int) ($current['version'] ?? 0) ?></span>
    <span class="chip">Next: v<?= (int) ($nextVersion ?? 0) ?></span>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error mb"><div><b>Publishing is blocked.</b><ul style="margin:6px 0 0 16px;text-align:left">
    <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
  </ul></div></div>
<?php endif; ?>
<?php if (!empty($warnings)): ?>
  <div class="alert warning mb"><div><b>Warnings (publishing still allowed):</b><ul style="margin:6px 0 0 16px;text-align:left">
    <?php foreach ($warnings as $wn): ?><li><?= e($wn) ?></li><?php endforeach; ?>
  </ul></div></div>
<?php endif; ?>

<!-- Real counts from the working snapshot -->
<div class="grid cards mb">
  <div class="card"><div class="stat"><span class="stat__label"><?= icon('offers', 16) ?> Offers</span><span class="stat__value"><?= count($offers) ?></span></div></div>
  <div class="card"><div class="stat"><span class="stat__label"><?= icon('billboards', 16) ?> Billboards</span><span class="stat__value"><?= count($billboards) ?></span></div></div>
  <div class="card"><div class="stat"><span class="stat__label"><?= icon('templates', 16) ?> Templates</span><span class="stat__value"><?= count($allTemplates) ?></span></div></div>
  <div class="card"><div class="stat"><span class="stat__label"><?= icon('versions', 16) ?> App version</span><span class="stat__value" style="font-size:20px"><?= e($version['latestVersionName'] ?? '—') ?></span></div></div>
</div>

<div class="grid two">
  <div class="stack">
    <!-- Pending changes (real draft-vs-published diff) -->
    <div class="card">
      <div class="card__head"><h2>Pending changes</h2><span class="spacer"></span><span class="tag muted"><?= count($pending) ?> change(s)</span></div>
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

    <!-- Offers -->
    <div class="card">
      <div class="card__head"><h2>Offers</h2><span class="spacer"></span><span class="tag muted"><?= count($offers) ?></span></div>
      <?php if (!empty($offers)): ?>
        <div class="table-wrap"><table class="data">
          <thead><tr><th>Category</th><th>Name</th><th>Price</th><th>Validity</th><th>Offline</th></tr></thead>
          <tbody>
            <?php foreach ($offers as $o): ?>
              <tr>
                <td><span class="tag <?= e(strtolower((string) $o['category'])) ?>"><?= e($o['category']) ?></span></td>
                <td><?= e($o['name']) ?></td>
                <td class="nowrap"><?= e(ksh($o['price'])) ?></td>
                <td class="muted"><?= e($o['validity'] ?: '—') ?></td>
                <td><?= !empty($o['offlineEligible']) ? '<span class="status active">Yes</span>' : '<span class="muted">No</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php else: ?>
        <p class="small muted">No active offers will be published.</p>
      <?php endif; ?>
    </div>

    <!-- Billboards -->
    <div class="card">
      <div class="card__head"><h2>Billboard adverts</h2><span class="spacer"></span><span class="tag muted"><?= count($billboards) ?></span></div>
      <?php if (!empty($billboards)): ?>
        <?php foreach ($billboards as $b): ?>
          <div class="diff-item" style="flex-direction:column;align-items:flex-start;gap:2px">
            <div><span class="tag muted"><?= e($b['kind']) ?></span> <b><?= e($b['headline'] ?: ('Billboard #' . $b['id'])) ?></b> <span class="muted small">priority <?= (int) $b['priority'] ?></span></div>
            <?php if (($b['body'] ?? '') !== ''): ?><div class="muted small"><?= e($b['body']) ?></div><?php endif; ?>
            <?php if (($b['ctaLabel'] ?? '') !== ''): ?><div class="small">CTA: <?= e($b['ctaLabel']) ?><?= ($b['linkedOfferId'] ?? '') !== '' ? ' → offer ' . e($b['linkedOfferId']) : '' ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="small muted">No adverts will be published.</p>
      <?php endif; ?>
    </div>

    <!-- Message templates -->
    <div class="card">
      <div class="card__head"><h2>Message templates</h2><span class="spacer"></span><span class="tag muted"><?= count($allTemplates) ?></span></div>
      <?php if (!empty($allTemplates)): ?>
        <div class="table-wrap"><table class="data">
          <thead><tr><th>Key</th><th>Purpose</th><th>Category</th><th>Priority</th></tr></thead>
          <tbody>
            <?php foreach ($allTemplates as $t): ?>
              <tr>
                <td class="mono"><?= e($t['id']) ?></td>
                <td><?= e($t['purpose'] ?? '—') ?></td>
                <td class="muted"><?= e($t['category'] ?? '—') ?></td>
                <td><?= (int) ($t['priority'] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php else: ?>
        <p class="small muted">No templates will be published.</p>
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
          <button class="btn btn--block" type="submit" <?= !empty($errors) || empty($pending) ? 'disabled' : '' ?>><?= icon('publish', 18) ?> Publish v<?= (int) ($nextVersion ?? 0) ?></button>
          <?php if (empty($pending)): ?><p class="small muted mt">There are no changes to publish.</p><?php endif; ?>
        </form>
      <?php else: ?>
        <div class="alert info">You can preview changes but do not have permission to publish. Ask the Super Admin to publish.</div>
      <?php endif; ?>
      <div class="mt small muted"><p>Each publish gets a version number and date. Publishing pushes exactly the configuration shown on this page.</p></div>
      <div class="mt"><a class="btn btn--ghost btn--sm btn--block" href="<?= e(url('/releases')) ?>"><?= icon('versions', 16) ?> View release history</a></div>
    </div>

    <!-- Support & payment -->
    <div class="card">
      <div class="card__head"><h3>Support &amp; payment</h3><?php if (!$hasSupport): ?><span class="spacer"></span><span class="tag minutes">Offline disabled</span><?php endif; ?></div>
      <div class="stack" style="gap:8px">
        <div class="between"><span class="muted small">Till (buy for me)</span><b><?= e($support['tillNumber'] ?: '—') ?></b></div>
        <div class="between"><span class="muted small">Paybill (another number)</span><b><?= e($support['paybillNumber'] ?: '—') ?></b></div>
        <div class="between"><span class="muted small">Support number</span><b><?= e($support['supportNumber'] ?: '—') ?></b></div>
        <div class="between"><span class="muted small">WhatsApp</span><b><?= e($support['supportWhatsapp'] ?: '—') ?></b></div>
        <div class="between"><span class="muted small">Working hours</span><b><?= e($support['workingHours'] ?: '—') ?></b></div>
      </div>
    </div>

    <!-- App configuration -->
    <div class="card">
      <div class="card__head"><h3>App configuration</h3></div>
      <div class="stack" style="gap:8px">
        <div class="between"><span class="muted small">Maintenance mode</span><b><?= !empty($appConfig['maintenanceMode']) ? 'On' : 'Off' ?></b></div>
        <div class="between"><span class="muted small">Sync interval</span><b><?= (int) ($appConfig['syncIntervalMinutes'] ?? 0) ?> min</b></div>
        <?php if (($appConfig['generalSupportMessage'] ?? '') !== ''): ?>
          <div class="field"><label class="small">Support message</label><div class="small muted" style="text-align:left"><?= e($appConfig['generalSupportMessage']) ?></div></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Version / update rule -->
    <div class="card">
      <div class="card__head"><h3>Update rule</h3></div>
      <div class="stack" style="gap:8px">
        <div class="between"><span class="muted small">Latest</span><b>v<?= e($version['latestVersionName'] ?? '—') ?> <span class="muted">(code <?= (int) ($version['latestVersionCode'] ?? 0) ?>)</span></b></div>
        <div class="between"><span class="muted small">Minimum supported</span><b><?= (int) ($version['minSupportedVersionCode'] ?? 0) ?></b></div>
        <div class="between"><span class="muted small">Forced update</span><b><?= !empty($version['mandatory']) ? '<span class="tag minutes">Forced</span>' : 'Optional' ?></b></div>
        <div class="between"><span class="muted small">Update source</span><b><?= ($version['updateSource'] ?? '') === 'play' ? 'Play Store' : 'Direct APK (GitHub)' ?></b></div>
      </div>
    </div>
  </div>
</div>
