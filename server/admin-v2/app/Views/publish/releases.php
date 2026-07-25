<div class="page-head">
  <div><h1>Release history</h1><div class="sub">Every published configuration version. Immutable and append-only.</div></div>
</div>
<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Version</th><th>Published</th><th>By</th><th>Type</th><th>Notes</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($releases as $r): ?>
          <tr>
            <td><b>v<?= (int) $r['version'] ?></b></td>
            <td class="muted"><?= e(fmt_nairobi($r['created_at'])) ?></td>
            <td class="muted"><?= e($r['published_by']) ?></td>
            <td><?= $r['rolled_back_from'] ? '<span class="tag minutes">Rollback of v' . (int) $r['rolled_back_from'] . '</span>' : '<span class="tag sms">Publish</span>' ?></td>
            <td class="muted small"><?= e(mb_strimwidth((string) $r['notes'], 0, 40, '…')) ?></td>
            <td><a class="btn btn--secondary btn--sm" href="<?= e(url('/releases/' . (int) $r['version'])) ?>"><?= icon('eye', 14) ?> View</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$releases): ?>
          <tr><td colspan="6"><div class="empty"><?= icon('publish', 32) ?><h3>No releases yet</h3><p>Publish your first configuration from Review &amp; publish.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
