<?php
use App\Repositories\PaymentRepository;
$qs = http_build_query(array_filter($filters));
$pages = (int) ceil($total / $per);
?>
<div class="page-head">
  <div><h1>Payments</h1><div class="sub">Real payment records (read-only). Fulfilment is tracked separately from payment.</div></div>
  <?php if ($canReveal): ?><div class="page-head__actions"><a class="btn btn--secondary" href="<?= e(url('/payments-export' . ($qs ? '?' . $qs : ''))) ?>"><?= icon('download', 18) ?> Export CSV</a></div><?php endif; ?>
</div>

<?php if (!$available): ?>
  <div class="card"><div class="empty"><?= icon('payments', 32) ?><h3>Not available yet</h3><p>The payments table has no records yet, or has not been created by the payment API.</p></div></div>
<?php else: ?>
<div class="card">
  <form class="filters" method="get" action="<?= e(url('/payments')) ?>">
    <div class="field"><input type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="field"><input type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    <div class="field"><select name="state"><option value="">Any state</option>
      <?php foreach (['PAYMENT_CONFIRMED', 'PAYMENT_REQUESTED', 'PAYMENT_FAILED', 'CANCELLED', 'TIMED_OUT'] as $s): ?>
        <option value="<?= $s ?>" <?= $filters['state'] === $s ? 'selected' : '' ?>><?= e(PaymentRepository::displayState($s)['label']) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="field"><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Offer or receipt"></div>
    <div class="field"><input type="number" name="min" value="<?= e($filters['min']) ?>" placeholder="Min" style="width:90px"></div>
    <div class="field"><input type="number" name="max" value="<?= e($filters['max']) ?>" placeholder="Max" style="width:90px"></div>
    <button class="btn btn--secondary btn--sm"><?= icon('filter', 16) ?> Filter</button>
    <?php if ($qs): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/payments')) ?>">Clear</a><?php endif; ?>
  </form>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Time</th><th>Payer</th><th>Recipient</th><th>Offer</th><th>Amount</th><th>Receipt</th><th>State</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $p): $st = PaymentRepository::displayState($p['status']); ?>
          <tr>
            <td class="muted nowrap"><?= e(fmt_nairobi($p['created_at'], 'd M H:i')) ?></td>
            <td class="mono"><?= e(str_mask_phone($p['payer'])) ?></td>
            <td class="mono"><?= e(str_mask_phone($p['recipient'])) ?></td>
            <td><?= e($p['offer_id']) ?></td>
            <td class="nowrap"><?= e(ksh($p['amount'])) ?></td>
            <td class="mono small"><?= e(str_mask_receipt($p['mpesa_receipt'])) ?></td>
            <td><span class="status <?= $st['class'] ?>"><?= e($st['label']) ?></span></td>
            <td><a class="btn btn--ghost btn--sm" href="<?= e(url('/payments/' . (int) $p['id'])) ?>"><?= icon('eye', 14) ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8"><div class="empty"><?= icon('payments', 28) ?><h3>No payments match</h3></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="row mt" style="justify-content:center;gap:6px">
      <?php for ($pp = max(1, $page - 2); $pp <= min($pages, $page + 2); $pp++): ?>
        <a class="btn <?= $pp === $page ? '' : 'btn--secondary' ?> btn--sm" href="<?= e(url('/payments?' . http_build_query(array_merge($filters, ['page' => $pp])))) ?>"><?= $pp ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
