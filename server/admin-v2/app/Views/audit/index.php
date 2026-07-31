<?php
/**
 * Audit log — append-only, read-only.
 *
 * Filterable by module, actor, action, entity, result, release version and date range,
 * plus a free-text search over action / entity id / reason. The CSV export carries the
 * same filters, so an export always matches what is on screen.
 */
$active = array_filter($filters, static fn($v) => (string) $v !== '');
$qs = http_build_query($active);
$pages = (int) ceil($total / max(1, $per));
?>
<style>
.au-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: end; margin-bottom: 14px; }
.au-filters .field > label { font-size: 12px; color: var(--text-2); }
.au-filters input, .au-filters select {
  width: 100%; font-family: inherit; font-size: 13.5px; color: var(--text);
  background: var(--surface); border: 1px solid var(--divider);
  border-radius: var(--radius-sm); padding: 8px 11px;
}
.au-filters .au-actions { display: flex; gap: 8px; align-items: center; }
.au-search { grid-column: 1 / -1; }
</style>

<div class="page-head">
  <div>
    <h1>Audit log</h1>
    <div class="sub"><?= (int) $total ?> entr<?= (int) $total === 1 ? 'y' : 'ies' ?> · append-only, cannot be edited or deleted. Sensitive values were masked when written.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--secondary" href="<?= e(url('/audit-export' . ($qs ? '?' . $qs : ''))) ?>"><?= icon('download', 18) ?> Export CSV</a>
  </div>
</div>

<div class="card">
  <form class="au-filters" method="get" action="<?= e(url('/audit')) ?>">
    <div class="field au-search">
      <label for="f-q">Search</label>
      <input id="f-q" type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Action, entity id or reason">
    </div>
    <div class="field">
      <label for="f-module">Module</label>
      <select id="f-module" name="module">
        <option value="">Any module</option>
        <?php foreach ($modules as $m): ?>
          <option value="<?= e($m) ?>" <?= $filters['module'] === $m ? 'selected' : '' ?>><?= e($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="f-action">Action</label>
      <select id="f-action" name="action">
        <option value="">Any action</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="f-actor">Actor</label>
      <input id="f-actor" type="text" name="actor" value="<?= e($filters['actor']) ?>" placeholder="Name">
    </div>
    <div class="field">
      <label for="f-etype">Entity type</label>
      <select id="f-etype" name="entity_type">
        <option value="">Any entity</option>
        <?php foreach ($entityTypes as $t): ?>
          <option value="<?= e($t) ?>" <?= $filters['entity_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="f-eid">Entity id</label>
      <input id="f-eid" type="text" name="entity_id" value="<?= e($filters['entity_id']) ?>" placeholder="e.g. data_14">
    </div>
    <div class="field">
      <label for="f-success">Result</label>
      <select id="f-success" name="success">
        <option value="">Any result</option>
        <option value="1" <?= $filters['success'] === '1' ? 'selected' : '' ?>>Succeeded</option>
        <option value="0" <?= $filters['success'] === '0' ? 'selected' : '' ?>>Failed</option>
      </select>
    </div>
    <div class="field">
      <label for="f-from">From (date)</label>
      <input id="f-from" type="date" name="from" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
      <label for="f-to">To (date)</label>
      <input id="f-to" type="date" name="to" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field">
      <label for="f-version">Release version</label>
      <input id="f-version" type="number" name="version" min="1" value="<?= e($filters['version']) ?>" placeholder="v">
    </div>
    <div class="au-actions">
      <button class="btn btn--secondary btn--sm"><?= icon('filter', 16) ?> Filter</button>
      <?php if ($qs): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/audit')) ?>">Clear</a><?php endif; ?>
    </div>
  </form>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>When (Nairobi)</th><th>Actor</th><th>Role</th><th>Module</th><th>Action</th>
        <th>Entity</th><th>Ver</th><th>Result</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="muted nowrap"><?= e(fmt_nairobi($r['created_at'])) ?></td>
            <td><?= e($r['actor_name']) ?></td>
            <td class="muted small"><?= e($r['actor_role']) ?></td>
            <td class="small"><?= ($r['module'] ?? '') !== '' ? '<span class="tag muted">' . e($r['module']) . '</span>' : '—' ?></td>
            <td class="small mono"><?= e($r['action']) ?></td>
            <td class="small"><?= e($r['entity_type']) ?><?= (string) $r['entity_id'] !== '' ? ' <span class="mono muted">#' . e($r['entity_id']) . '</span>' : '' ?></td>
            <td><?= $r['release_version'] ? 'v' . (int) $r['release_version'] : '—' ?></td>
            <td><?= (int) $r['success'] ? '<span class="status active">OK</span>' : '<span class="status failed">Fail</span>' ?></td>
            <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/audit/' . (int) $r['id'])) ?>" aria-label="View entry"><?= icon('eye', 14) ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="9"><div class="empty"><?= icon('audit', 32) ?><h3>No audit entries</h3>
            <p><?= $qs ? 'No entry matches these filters.' : 'Actions you take will appear here.' ?></p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="row mt" style="justify-content:center;gap:6px">
      <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
        <a class="btn <?= $p === $page ? '' : 'btn--secondary' ?> btn--sm"
           href="<?= e(url('/audit?' . http_build_query(array_merge($active, ['page' => $p])))) ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
