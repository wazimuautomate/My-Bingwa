<?php $qs = http_build_query(array_filter($filters)); $pages = (int) ceil($total / $per); ?>
<div class="page-head">
  <div><h1>Audit log</h1><div class="sub"><?= (int) $total ?> entries · append-only, cannot be edited.</div></div>
  <div class="page-head__actions"><a class="btn btn--secondary" href="<?= e(url('/audit-export' . ($qs ? '?' . $qs : ''))) ?>"><?= icon('download', 18) ?> Export CSV</a></div>
</div>
<div class="card">
  <form class="filters" method="get" action="<?= e(url('/audit')) ?>">
    <div class="field"><input type="text" name="actor" value="<?= e($filters['actor']) ?>" placeholder="Actor"></div>
    <div class="field"><select name="action"><option value="">Any action</option>
      <?php foreach ($actions as $a): ?><option value="<?= e($a['action']) ?>" <?= $filters['action'] === $a['action'] ? 'selected' : '' ?>><?= e($a['action']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><input type="text" name="entity" value="<?= e($filters['entity']) ?>" placeholder="Entity type or id"></div>
    <div class="field"><input type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="field"><input type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    <div class="field"><input type="number" name="version" value="<?= e($filters['version']) ?>" placeholder="Version" style="width:90px"></div>
    <button class="btn btn--secondary btn--sm"><?= icon('filter', 16) ?> Filter</button>
    <?php if ($qs): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/audit')) ?>">Clear</a><?php endif; ?>
  </form>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>When (Nairobi)</th><th>Actor</th><th>Role</th><th>Action</th><th>Entity</th><th>Ver</th><th>Result</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="muted nowrap"><?= e(fmt_nairobi($r['created_at'])) ?></td>
            <td><?= e($r['actor_name']) ?></td>
            <td class="muted small"><?= e($r['actor_role']) ?></td>
            <td><span class="tag muted"><?= e($r['action']) ?></span></td>
            <td class="small"><?= e($r['entity_type']) ?><?= $r['entity_id'] !== '' ? ' <span class="mono muted">#' . e($r['entity_id']) . '</span>' : '' ?></td>
            <td><?= $r['release_version'] ? 'v' . (int) $r['release_version'] : '—' ?></td>
            <td><?= (int) $r['success'] ? '<span class="status active">OK</span>' : '<span class="status failed">Fail</span>' ?></td>
            <td><a class="btn btn--ghost btn--sm" href="<?= e(url('/audit/' . (int) $r['id'])) ?>"><?= icon('eye', 14) ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8"><div class="empty"><?= icon('audit', 32) ?><h3>No audit entries</h3><p>Actions you take will appear here.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="row mt" style="justify-content:center;gap:6px">
      <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
        <a class="btn <?= $p === $page ? '' : 'btn--secondary' ?> btn--sm" href="<?= e(url('/audit?' . http_build_query(array_merge($filters, ['page' => $p])))) ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
