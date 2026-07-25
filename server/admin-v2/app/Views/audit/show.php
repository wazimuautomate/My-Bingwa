<?php $r = $row; ?>
<div class="page-head">
  <div><h1>Audit entry #<?= (int) $r['id'] ?></h1><div class="sub"><?= e($r['action']) ?> · <?= e(fmt_nairobi($r['created_at'])) ?></div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/audit')) ?>">Back</a></div>
</div>
<div class="grid two">
  <div class="card">
    <div class="card__head"><h3>Details</h3></div>
    <div class="stack" style="gap:8px">
      <div class="between"><span class="muted small">Actor</span><b><?= e($r['actor_name']) ?> · <?= e($r['actor_role']) ?></b></div>
      <div class="between"><span class="muted small">Entity</span><span><?= e($r['entity_type']) ?> <span class="mono"><?= e($r['entity_id']) ?></span></span></div>
      <div class="between"><span class="muted small">Version</span><span><?= $r['release_version'] ? 'v' . (int) $r['release_version'] : '—' ?></span></div>
      <div class="between"><span class="muted small">Result</span><span><?= (int) $r['success'] ? '<span class="status active">OK</span>' : '<span class="status failed">Fail</span>' ?></span></div>
      <div class="between"><span class="muted small">IP</span><span class="mono small"><?= e($r['ip']) ?></span></div>
      <div class="between"><span class="muted small">Time (UTC)</span><span class="small"><?= e($r['created_at']) ?></span></div>
      <?php if ($r['reason']): ?><div><span class="muted small">Reason</span><div><?= e($r['reason']) ?></div></div><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><h3>Change diff</h3><span class="muted small" style="margin-left:auto">sensitive values masked</span></div>
    <?php if (!empty($diff)): ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Field</th><th>From</th><th>To</th></tr></thead>
        <tbody>
        <?php foreach ($diff as $field => $change): ?>
          <tr>
            <td class="mono small"><?= e($field) ?></td>
            <td class="small muted"><?= e(is_scalar($change['from'] ?? null) ? (string) $change['from'] : json_encode($change['from'] ?? null)) ?></td>
            <td class="small"><?= e(is_scalar($change['to'] ?? null) ? (string) $change['to'] : json_encode($change['to'] ?? null)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php else: ?>
      <p class="small muted">No field-level diff recorded for this action.</p>
    <?php endif; ?>
  </div>
</div>
