<?php
/**
 * One audit entry, rendered for a human: field, from, to.
 *
 * The raw before/after JSON is still available in a collapsed <details> for anyone who
 * needs the exact record. Secrets were masked at write time by App\Core\Audit::mask and
 * nothing here unmasks them; values stored under a subscriber-number field are masked
 * again for display, so a full phone number is never printed.
 */
$r = $row;
?>
<style>
.ad-raw { border: 1px solid var(--divider); border-radius: var(--radius-sm); margin-top: 14px; }
.ad-raw > summary { padding: 10px 12px; cursor: pointer; font-size: 13px; font-weight: 600; list-style: none; display: flex; align-items: center; gap: 8px; }
.ad-raw > summary::-webkit-details-marker { display: none; }
.ad-raw > summary:hover { background: var(--grouped); }
.ad-raw pre { margin: 0; padding: 12px; overflow-x: auto; font-family: ui-monospace, "SFMono-Regular", Menlo, monospace; font-size: 12px; line-height: 1.5; border-top: 1px dashed var(--divider); }
.ad-meta { display: flex; flex-direction: column; gap: 8px; }
</style>

<div class="page-head">
  <div>
    <h1>Audit entry #<?= (int) $r['id'] ?></h1>
    <div class="sub"><span class="mono"><?= e($r['action']) ?></span> · <?= e(fmt_nairobi($r['created_at'])) ?> (Nairobi)</div>
  </div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/audit')) ?>">Back to audit log</a></div>
</div>

<div class="grid two">
  <div class="card">
    <div class="card__head"><?= icon('audit', 18) ?><h3>Change diff</h3><span class="spacer"></span>
      <span class="muted small">sensitive values masked</span>
    </div>
    <?php if (!empty($diff)): ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Field</th><th>From</th><th>To</th></tr></thead>
        <tbody>
        <?php foreach ($diff as $field => $change): ?>
          <tr>
            <td class="small"><b><?= e((string) $field) ?></b></td>
            <td class="small muted"><?= e(\App\Controllers\AuditController::displayValue((string) $field, $change['from'] ?? null)) ?></td>
            <td class="small"><?= e(\App\Controllers\AuditController::displayValue((string) $field, $change['to'] ?? null)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php else: ?>
      <p class="small muted">No field-level diff was recorded for this action. It may be a read, a login or a create/delete without a before/after pair.</p>
    <?php endif; ?>

    <?php if (!empty($before) || !empty($after)): ?>
      <details class="ad-raw">
        <summary><?= icon('chevron', 14) ?> Raw record (JSON)</summary>
        <pre><?= e(json_encode(['before' => $before ?: null, 'after' => $after ?: null], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
      </details>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card__head"><?= icon('user', 18) ?><h3>Details</h3></div>
    <div class="ad-meta">
      <div class="between"><span class="muted small">Actor</span><b><?= e($r['actor_name']) ?> · <?= e($r['actor_role']) ?></b></div>
      <div class="between"><span class="muted small">Module</span><span><?= ($r['module'] ?? '') !== '' ? '<span class="tag muted">' . e($r['module']) . '</span>' : '—' ?></span></div>
      <div class="between"><span class="muted small">Action</span><span class="mono small"><?= e($r['action']) ?></span></div>
      <div class="between"><span class="muted small">Entity</span><span><?= e($r['entity_type']) ?> <span class="mono"><?= e($r['entity_id']) ?></span></span></div>
      <div class="between"><span class="muted small">Release version</span><span><?= $r['release_version'] ? 'v' . (int) $r['release_version'] : '—' ?></span></div>
      <div class="between"><span class="muted small">Result</span><span><?= (int) $r['success'] ? '<span class="status active">OK</span>' : '<span class="status failed">Fail</span>' ?></span></div>
      <div class="between"><span class="muted small">IP</span><span class="mono small"><?= e($r['ip']) ?></span></div>
      <div class="between"><span class="muted small">Time (UTC)</span><span class="small"><?= e($r['created_at']) ?></span></div>
      <?php if ((string) ($r['reason'] ?? '') !== ''): ?>
        <div><span class="muted small">Reason</span><div class="small"><?= nl2br(e($r['reason'])) ?></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>
