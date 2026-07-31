<?php
/**
 * Preview — release-oriented, change-only.
 *
 * Top: which version is live, which version the draft becomes, how much is pending.
 * Middle: one line per synchronisable resource with its version, and "will become vN"
 * only for the resources that genuinely move.
 * Bottom: the changes themselves, grouped by module and COLLAPSED, so the page stays
 * short no matter how large the catalogue is.
 *
 * Nothing on this page is inferred from a timestamp. If an item's values did not change
 * it is not here at all.
 */

require_once __DIR__ . '/_changes.php';

$s = $summary;
$hasErrors    = !empty($errors);
$count        = (int) $s['pendingCount'];
$live         = (int) $s['liveVersion'];
$draft        = (int) $s['draftVersion'];
$filter       = (string) ($moduleFilter ?? '');
$filterLabel  = $filter !== '' ? \App\Services\ChangeDetector::moduleLabel($filter) : '';
$totalInView  = 0;
foreach ($groups as $g) { $totalInView += (int) $g['count']; }
?>
<?= mb_change_group_styles() ?>
<style>
.pv-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 18px; }
.pv-summary .stat__value { font-size: 23px; }
.pv-note { font-size: 12.5px; color: var(--text-2); margin-top: 10px; }
</style>

<div class="page-head">
  <div>
    <h1>Preview &amp; publish</h1>
    <div class="sub">
      <?php if ($filter !== ''): ?>
        Showing only <?= e($filterLabel) ?>. Nothing else on this page is affected.
      <?php else: ?>
        Only values that actually changed since the last publish are listed. Everything else stays exactly as it is.
      <?php endif; ?>
    </div>
  </div>
  <div class="page-head__actions">
    <?php if ($filter !== ''): ?>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/preview')) ?>"><?= icon('close', 16) ?> Show everything</a>
    <?php endif; ?>
    <a class="btn btn--secondary btn--sm" href="<?= e(url('/releases')) ?>"><?= icon('versions', 16) ?> Release history</a>
    <?php if (can('publish.execute')): ?>
      <a class="btn" href="<?= e(url('/publish')) ?>"><?= icon('publish', 18) ?> Review &amp; publish</a>
    <?php endif; ?>
  </div>
</div>

<?php /* After a server upgrade, whole sections exist that the live release never had. Those
         are genuine changes, but an operator who edited nothing needs telling why. */ ?>
<?php if (!empty($firstPublish['isUpgrade'])): ?>
  <div class="alert info mb">
    <div>
      <b>First publish since the server was upgraded.</b>
      <p style="margin:6px 0 0">
        You have not changed anything &mdash; these are new capabilities the app has never
        received before, so they count as changes until you publish them once.
      </p>
      <ul style="margin:6px 0 0 16px;text-align:left">
        <?php foreach ($firstPublish['modules'] as $m): ?>
          <li><b><?= e($m['label']) ?></b> &mdash; <?= (int) $m['count'] ?> new
            <?= (int) $m['count'] === 1 ? 'item' : 'items' ?></li>
        <?php endforeach; ?>
      </ul>
      <p style="margin:6px 0 0">
        Publish once and this page goes back to listing only what you actually edit.
      </p>
    </div>
  </div>
<?php endif; ?>

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

<!-- 1. Release summary -->
<div class="card mb">
  <div class="card__head"><?= icon('layers', 18) ?><h2>Release status</h2><span class="spacer"></span>
    <span class="chip"><span class="dot"></span>Live v<?= $live ?></span>
  </div>
  <div class="pv-summary">
    <div class="stat"><span class="stat__label">Live version</span><span class="stat__value">v<?= $live ?></span></div>
    <div class="stat"><span class="stat__label">Draft version</span><span class="stat__value">v<?= $draft ?></span></div>
    <div class="stat"><span class="stat__label">Pending changes</span><span class="stat__value"><?= $count ?></span></div>
    <div class="stat">
      <span class="stat__label">Last published</span>
      <span class="stat__value" style="font-size:16px"><?= e(fmt_nairobi($s['lastPublishedAt'])) ?></span>
    </div>
    <div class="stat">
      <span class="stat__label">Published by</span>
      <span class="stat__value" style="font-size:16px"><?= e($s['publishedBy'] ?: '—') ?></span>
    </div>
    <div class="stat">
      <span class="stat__label">Signature</span>
      <span class="stat__value" style="font-size:16px">
        <?= $s['signed'] ? '<span class="status active">Signed</span>' : '<span class="status archived">Unsigned</span>' ?>
      </span>
    </div>
  </div>
  <?php if ((string) $s['releaseUid'] !== ''): ?>
    <div class="pv-note">Live release identifier <span class="mono"><?= e($s['releaseUid']) ?></span> · times shown in Africa/Nairobi.</div>
  <?php else: ?>
    <div class="pv-note">Times shown in Africa/Nairobi.</div>
  <?php endif; ?>
</div>

<!-- 2. Per-resource versions -->
<div class="card mb">
  <div class="card__head"><?= icon('sync', 18) ?><h3>What each resource is on</h3><span class="spacer"></span>
    <span class="muted small">A device only re-downloads a resource whose version moved.</span>
  </div>
  <div class="res-strip">
    <?php foreach ($resources as $r): ?>
      <span class="res-chip<?= $r['pending'] > 0 ? ' is-moving' : '' ?>">
        <span><?= e($r['label']) ?></span>
        <b>v<?= (int) $r['version'] ?></b>
        <?php if ($r['willBe'] !== null): ?>
          <span class="muted">&rarr; will become v<?= (int) $r['willBe'] ?></span>
        <?php endif; ?>
      </span>
    <?php endforeach; ?>
  </div>
</div>

<!-- 3. The changes -->
<?php if ($count === 0): ?>
  <div class="card">
    <div class="empty">
      <?= icon('check', 32) ?>
      <h3>Nothing to publish</h3>
      <p>The live configuration matches the draft. Change an offer, advert, notification, SMS rule or setting and it will appear here.</p>
    </div>
  </div>
<?php elseif ($groups === []): ?>
  <div class="card">
    <div class="empty">
      <?= icon('check', 32) ?>
      <h3>No changes in <?= e($filterLabel !== '' ? $filterLabel : 'this module') ?></h3>
      <p>There are <?= $count ?> pending change<?= $count === 1 ? '' : 's' ?> in other modules.</p>
      <p class="mt"><a class="btn btn--secondary btn--sm" href="<?= e(url('/preview')) ?>">Show everything</a></p>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card__head"><?= icon('publish', 18) ?><h3>Changes to publish</h3><span class="spacer"></span>
      <span class="tag muted"><?= (int) $totalInView ?> change<?= (int) $totalInView === 1 ? '' : 's' ?></span>
    </div>
    <p class="small muted mb">Open a group to see the exact values that will change.</p>
    <?= mb_change_groups($groups, ['open' => $filter !== '', 'moduleLinks' => $filter === '']) ?>
    <?php if (can('publish.execute')): ?>
      <div class="mt"><a class="btn" href="<?= e(url('/publish')) ?>"><?= icon('publish', 18) ?> Review &amp; publish v<?= $draft ?></a></div>
    <?php else: ?>
      <div class="alert info mt">You can preview changes but do not have permission to publish. Ask the Super Admin to publish.</div>
    <?php endif; ?>
  </div>
<?php endif; ?>
