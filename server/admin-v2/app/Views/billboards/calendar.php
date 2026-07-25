<div class="page-head">
  <div><h1>Billboard schedule</h1><div class="sub">Billboards with a start date (Africa/Nairobi).</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/billboards')) ?>">All billboards</a></div>
</div>
<div class="card">
  <?php if (!empty($items)): ?>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Starts</th><th>Ends</th><th>Name</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($items as $b): ?>
          <tr>
            <td class="small"><?= e(fmt_nairobi($b['starts_at'])) ?></td>
            <td class="small muted"><?= $b['ends_at'] ? e(fmt_nairobi($b['ends_at'])) : '—' ?></td>
            <td><a href="<?= e(url('/billboards/' . (int) $b['id'] . '/edit')) ?>"><?= e($b['name']) ?></a></td>
            <td><span class="status <?= e($b['status']) ?>"><?= ucfirst($b['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty"><?= icon('calendar', 32) ?><h3>Nothing scheduled</h3><p>Add start/end dates to a billboard.</p></div>
  <?php endif; ?>
</div>
