<?php
/**
 * Release history — newest first, immutable and append-only.
 *
 * The five most recent releases are listed directly because they are the ones an operator
 * actually reasons about; everything older collapses into a single <details> so the page
 * never becomes a scroll.
 */
$recentLimit = 5;
$recent = array_slice($releases, 0, $recentLimit);
$older  = array_slice($releases, $recentLimit);

/** One history row. Kept here so recent and older rows can never drift apart. */
$renderRow = static function (array $r) use ($currentVersion): string {
    $signed = (string) ($r['signature'] ?? '') !== '';
    $notes  = trim((string) ($r['notes'] ?? ''));
    $uid    = (string) ($r['release_uid'] ?? '');
    $html  = '<tr>';
    $html .= '<td class="nowrap"><b>v' . (int) $r['version'] . '</b>'
           . ((int) $r['version'] === (int) $currentVersion ? ' <span class="tag sms">Live</span>' : '') . '</td>';
    $html .= '<td class="mono small">' . ($uid !== '' ? e($uid) : '—') . '</td>';
    $html .= '<td class="muted nowrap">' . e(fmt_nairobi($r['created_at'])) . '</td>';
    $html .= '<td class="muted">' . e($r['published_by']) . '</td>';
    $html .= '<td>' . ($r['rolled_back_from']
        ? '<span class="tag minutes">Rollback of v' . (int) $r['rolled_back_from'] . '</span>'
        : '<span class="tag sms">Publish</span>') . '</td>';
    $html .= '<td class="text-right">' . (int) ($r['change_count'] ?? 0) . '</td>';
    $html .= '<td>' . ($signed ? '<span class="status active">Signed</span>' : '<span class="status archived">Unsigned</span>') . '</td>';
    $html .= '<td class="muted small">' . ($notes !== '' ? e(mb_strimwidth($notes, 0, 60, '…')) : '—') . '</td>';
    $html .= '<td class="text-right"><a class="btn btn--secondary btn--sm" href="' . e(url('/releases/' . (int) $r['version'])) . '">'
           . icon('eye', 14) . ' View</a></td>';
    return $html . '</tr>';
};

$head = '<thead><tr><th>Version</th><th>Identifier</th><th>Published (Nairobi)</th><th>By</th><th>Type</th>'
      . '<th class="text-right">Changes</th><th>Signature</th><th>Notes</th><th></th></tr></thead>';
?>
<style>
.rh-older { border: 1px solid var(--divider); border-radius: var(--radius-sm); margin-top: 16px; }
.rh-older > summary { padding: 12px 14px; cursor: pointer; font-weight: 600; font-size: 14px; list-style: none; display: flex; align-items: center; gap: 8px; }
.rh-older > summary::-webkit-details-marker { display: none; }
.rh-older > summary:hover { background: var(--grouped); }
.rh-older__body { padding: 0 8px 8px; }
</style>

<div class="page-head">
  <div>
    <h1>Release history</h1>
    <div class="sub">Every published configuration version. Immutable and append-only — a rollback creates a new version, it never edits an old one.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--secondary btn--sm" href="<?= e(url('/preview')) ?>"><?= icon('eye', 16) ?> Preview draft</a>
  </div>
</div>

<div class="card">
  <div class="card__head"><?= icon('versions', 18) ?><h2>Recent releases</h2><span class="spacer"></span>
    <span class="tag muted"><?= count($releases) ?> total</span>
  </div>
  <?php if ($recent): ?>
    <div class="table-wrap">
      <table class="data">
        <?= $head ?>
        <tbody>
          <?php foreach ($recent as $r): ?><?= $renderRow($r) ?><?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><?= icon('publish', 32) ?><h3>No releases yet</h3><p>Publish your first configuration from Review &amp; publish.</p></div>
  <?php endif; ?>

  <?php if ($older): ?>
    <details class="rh-older">
      <summary><?= icon('clock', 16) ?> Older releases (<?= count($older) ?>)</summary>
      <div class="rh-older__body">
        <div class="table-wrap">
          <table class="data">
            <?= $head ?>
            <tbody>
              <?php foreach ($older as $r): ?><?= $renderRow($r) ?><?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </details>
  <?php endif; ?>
</div>
