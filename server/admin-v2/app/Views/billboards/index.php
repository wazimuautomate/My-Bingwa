<div class="page-head">
  <div><h1>Billboard adverts</h1><div class="sub">Simple (offer-linked) or advanced (image) adverts, shown by a transparent scoring model.</div></div>
  <div class="page-head__actions">
    <a class="btn btn--secondary" href="<?= e(url('/billboards/simulator')) ?>"><?= icon('billboards', 18) ?> Why-this simulator</a>
    <a class="btn btn--secondary" href="<?= e(url('/billboards/calendar')) ?>"><?= icon('calendar', 18) ?> Schedule</a>
    <a class="btn" href="<?= e(url('/billboards/new')) ?>"><?= icon('plus', 18) ?> New billboard</a>
  </div>
</div>
<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Name</th><th>Kind</th><th>Headline</th><th>Priority</th><th>Window</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($billboards as $b): ?>
          <tr>
            <td><b><?= e($b['name']) ?></b><?= $b['linked_offer_id'] ? ' <span class="muted small mono">' . e($b['linked_offer_id']) . '</span>' : '' ?></td>
            <td><span class="tag <?= $b['kind'] === 'advanced' ? 'special' : 'sms' ?>"><?= ucfirst($b['kind']) ?></span></td>
            <td class="small"><?= e(mb_strimwidth((string) ($b['headline'] ?: '(generated from offer)'), 0, 40, '…')) ?></td>
            <td><?= (int) $b['priority'] ?></td>
            <td class="small muted"><?= $b['starts_at'] ? e(fmt_nairobi($b['starts_at'], 'd M')) : '—' ?><?= $b['ends_at'] ? ' → ' . e(fmt_nairobi($b['ends_at'], 'd M')) : '' ?></td>
            <td><span class="status <?= e($b['status']) ?>"><?= ucfirst($b['status']) ?></span></td>
            <td>
              <div class="row-actions">
                <a class="btn btn--secondary btn--sm" href="<?= e(url('/billboards/' . (int) $b['id'] . '/edit')) ?>"><?= icon('edit', 14) ?></a>
                <?php if ($b['status'] !== 'active'): ?>
                  <form method="post" action="<?= e(url('/billboards/' . (int) $b['id'] . '/status')) ?>"><?= App\Core\Csrf::field() ?><input type="hidden" name="status" value="active"><button class="btn btn--ghost btn--sm" title="Activate"><?= icon('check', 14) ?></button></form>
                <?php else: ?>
                  <form method="post" action="<?= e(url('/billboards/' . (int) $b['id'] . '/status')) ?>"><?= App\Core\Csrf::field() ?><input type="hidden" name="status" value="paused"><button class="btn btn--ghost btn--sm" title="Pause"><?= icon('close', 14) ?></button></form>
                <?php endif; ?>
                <?php if ($b['status'] === 'draft'): ?>
                  <form method="post" action="<?= e(url('/billboards/' . (int) $b['id'] . '/delete')) ?>" data-confirm="Delete this draft billboard?"><?= App\Core\Csrf::field() ?><button class="btn btn--danger btn--sm" title="Delete"><?= icon('trash', 14) ?></button></form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$billboards): ?><tr><td colspan="7"><div class="empty"><?= icon('billboards', 32) ?><h3>No billboards</h3><p>Create a simple or advanced advert.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
