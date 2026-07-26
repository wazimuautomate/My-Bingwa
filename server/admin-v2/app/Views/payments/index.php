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
  <?php if ($canDelete): ?>
    <?php // Bulk-delete toolbar. The checkboxes below live in the table but are associated
          // with THIS form via the HTML5 form="pay-bulk-form" attribute — that keeps the
          // per-row delete forms (inside table cells) from being nested inside it, which
          // would be invalid HTML. app.js's data-confirm handler drives the confirmation. ?>
    <form id="pay-bulk-form" class="between mb" method="post" action="<?= e(url('/payments/delete-bulk')) ?>" data-confirm="Delete the selected payment records? This cannot be undone.">
      <?= App\Core\Csrf::field() ?>
      <span class="muted small" data-bulk-count>No records selected</span>
      <button type="submit" class="btn btn--danger btn--sm" data-bulk-delete><?= icon('trash', 16) ?> Delete selected</button>
    </form>
  <?php endif; ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><?php if ($canDelete): ?><th class="nowrap"><input type="checkbox" form="pay-bulk-form" data-check-all aria-label="Select all payment records on this page"></th><?php endif; ?><th>Time</th><th>Payer</th><th>Recipient</th><th>Offer</th><th>Amount</th><th>Receipt</th><th>State</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $p): $st = PaymentRepository::displayState($p['status']); ?>
          <tr>
            <?php if ($canDelete): ?><td class="nowrap"><input type="checkbox" form="pay-bulk-form" name="ids[]" value="<?= (int) $p['id'] ?>" data-row-check aria-label="Select payment <?= (int) $p['id'] ?>"></td><?php endif; ?>
            <td class="muted nowrap"><?= e(fmt_nairobi($p['created_at'], 'd M H:i')) ?></td>
            <td class="mono"><?= e($p['payer'] ?: '—') ?></td>
            <td class="mono"><?= e(($p['recipient'] ?: $p['payer']) ?: '—') ?></td>
            <td><?= e($p['offer_id']) ?></td>
            <td class="nowrap"><?= e(ksh($p['amount'])) ?></td>
            <td class="mono small"><?= e($p['mpesa_receipt'] ?: '—') ?></td>
            <td><span class="status <?= $st['class'] ?>"><?= e($st['label']) ?></span></td>
            <td>
              <div class="dropdown">
                <button class="btn btn--ghost btn--sm" data-dropdown="pay-menu-<?= (int) $p['id'] ?>" aria-haspopup="true" aria-label="Payment actions"><?= icon('more', 16) ?></button>
                <div class="dropdown-menu dropdown-menu--fixed" id="pay-menu-<?= (int) $p['id'] ?>">
                  <button type="button" data-modal-open="#pay-modal-<?= (int) $p['id'] ?>"><?= icon('eye', 18) ?> View details</button>
                  <a href="<?= e(url('/payments/' . (int) $p['id'])) ?>"><?= icon('external', 18) ?> Open full page</a>
                  <?php if (!empty($canDelete)): ?>
                    <div class="divider"></div>
                    <form method="post" action="<?= e(url('/payments/' . (int) $p['id'] . '/delete')) ?>" data-confirm="Delete this payment record? This cannot be undone."><?= App\Core\Csrf::field() ?><button type="submit" class="danger"><?= icon('trash', 18) ?> Delete record</button></form>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="<?= $canDelete ? 9 : 8 ?>"><div class="empty"><?= icon('payments', 28) ?><h3>No payments match</h3></div></td></tr><?php endif; ?>
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
