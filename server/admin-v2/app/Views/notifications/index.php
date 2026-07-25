<div class="page-head">
  <div><h1>Notifications</h1><div class="sub">Campaigns are optional, capped and quiet-hours aware. Delivery figures are never fabricated.</div></div>
  <div class="page-head__actions">
    <a class="btn btn--secondary" href="<?= e(url('/notifications/calendar')) ?>"><?= icon('calendar', 18) ?> Schedule</a>
    <a class="btn" href="<?= e(url('/notifications/new')) ?>"><?= icon('plus', 18) ?> New campaign</a>
  </div>
</div>
<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Name</th><th>Type</th><th>Title</th><th>Schedule</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($campaigns as $c): ?>
          <tr>
            <td><b><?= e($c['name']) ?></b></td>
            <td class="small muted"><?= e($types[$c['type']] ?? $c['type']) ?></td>
            <td class="small"><?= e(mb_strimwidth((string) $c['title'], 0, 40, '…')) ?></td>
            <td class="small muted"><?= $c['scheduled_at'] ? e(fmt_nairobi($c['scheduled_at'])) : '—' ?></td>
            <td><span class="status <?= e($c['status']) ?>"><?= ucfirst($c['status']) ?></span></td>
            <td>
              <div class="row-actions">
                <a class="btn btn--secondary btn--sm" href="<?= e(url('/notifications/' . (int) $c['id'] . '/edit')) ?>"><?= icon('edit', 14) ?></a>
                <form method="post" action="<?= e(url('/notifications/' . (int) $c['id'] . '/test')) ?>"><?= App\Core\Csrf::field() ?><button class="btn btn--ghost btn--sm" title="Test send"><?= icon('bell', 14) ?></button></form>
                <?php if ($c['status'] === 'scheduled'): ?>
                  <form method="post" action="<?= e(url('/notifications/' . (int) $c['id'] . '/cancel')) ?>" data-confirm="Cancel this scheduled campaign? Already-sent notifications cannot be recalled."><?= App\Core\Csrf::field() ?><button class="btn btn--danger btn--sm" title="Cancel"><?= icon('close', 14) ?></button></form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$campaigns): ?><tr><td colspan="6"><div class="empty"><?= icon('notifications', 32) ?><h3>No campaigns</h3><p>Create one to reach customers responsibly.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
