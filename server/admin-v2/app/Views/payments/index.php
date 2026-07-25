<?php
use App\Repositories\PaymentRepository;
$qs = http_build_query(array_filter($filters));
$pages = (int) ceil($total / $per);
// Friendly labels for the reconciliation detail modal. Any column not listed falls back
// to a humanised version of its own name, so nothing is ever hidden.
$payLabels = [
    'id' => 'Payment ID', 'payer' => 'Payer (M-Pesa number)', 'recipient' => 'Bundle recipient',
    'amount' => 'Amount', 'offer_id' => 'Offer', 'status' => 'Status', 'method' => 'Method',
    'mpesa_receipt' => 'M-Pesa receipt', 'client_request_id' => 'Idempotency key',
    'checkout_request_id' => 'Checkout request ID', 'merchant_request_id' => 'Merchant request ID',
    'order_reference' => 'Order reference', 'result_code' => 'Result code', 'result_desc' => 'Result description',
    'created_at' => 'Created (Nairobi)', 'updated_at' => 'Updated (Nairobi)',
];
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
            <td>
              <div class="dropdown">
                <button class="btn btn--ghost btn--sm" data-dropdown="pay-menu-<?= (int) $p['id'] ?>" aria-haspopup="true" aria-label="Payment actions"><?= icon('more', 16) ?></button>
                <div class="dropdown-menu dropdown-menu--fixed" id="pay-menu-<?= (int) $p['id'] ?>">
                  <button type="button" data-modal-open="#pay-modal-<?= (int) $p['id'] ?>"><?= icon('eye', 18) ?> View details</button>
                  <a href="<?= e(url('/payments/' . (int) $p['id'])) ?>"><?= icon('external', 18) ?> Open full page</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8"><div class="empty"><?= icon('payments', 28) ?><h3>No payments match</h3></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php foreach ($rows as $p): $st = PaymentRepository::displayState($p['status']); ?>
    <div class="modal-backdrop" data-modal id="pay-modal-<?= (int) $p['id'] ?>" role="dialog" aria-modal="true" aria-label="Payment <?= (int) $p['id'] ?> details">
      <div class="modal modal--lg">
        <div class="card__head"><h3>Payment #<?= (int) $p['id'] ?></h3><span class="spacer"></span><span class="status <?= $st['class'] ?>"><?= e($st['label']) ?></span></div>
        <p class="small muted mb">Operator reconciliation view — full, unmasked details.</p>
        <div class="stack" style="gap:8px">
          <?php foreach ($p as $k => $v):
            $lab = $payLabels[$k] ?? ucwords(str_replace('_', ' ', (string) $k));
            if ($k === 'created_at' || $k === 'updated_at') { $disp = fmt_nairobi($v); }
            elseif ($k === 'amount') { $disp = ksh($v); }
            elseif ($k === 'status') { $disp = $v . ' — ' . $st['label']; }
            else { $disp = ($v === null || $v === '') ? '—' : (string) $v; }
          ?>
            <div class="between"><span class="muted small"><?= e($lab) ?></span><span class="mono small" style="word-break:break-all"><?= e($disp) ?></span></div>
          <?php endforeach; ?>
        </div>
        <div class="modal__actions">
          <a class="btn btn--secondary" href="<?= e(url('/payments/' . (int) $p['id'])) ?>">Open full page</a>
          <button class="btn" type="button" data-modal-close>Close</button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if ($pages > 1): ?>
    <div class="row mt" style="justify-content:center;gap:6px">
      <?php for ($pp = max(1, $page - 2); $pp <= min($pages, $page + 2); $pp++): ?>
        <a class="btn <?= $pp === $page ? '' : 'btn--secondary' ?> btn--sm" href="<?= e(url('/payments?' . http_build_query(array_merge($filters, ['page' => $pp])))) ?>"><?= $pp ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
