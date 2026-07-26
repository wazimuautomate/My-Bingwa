<?php
use App\Repositories\PaymentRepository;
$st = PaymentRepository::displayState($p['status']);
?>
<div class="page-head">
  <div><h1>Payment #<?= (int) $p['id'] ?></h1><div class="sub"><?= e($p['offer_id']) ?> · <?= e(ksh($p['amount'])) ?> · <?= e(fmt_nairobi($p['created_at'])) ?></div></div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= e(url('/payments')) ?>">Back</a>
    <?php if (!empty($canDelete)): ?>
      <form method="post" action="<?= e(url('/payments/' . (int) $p['id'] . '/delete')) ?>" data-confirm="Delete this payment record? This cannot be undone."><?= App\Core\Csrf::field() ?><button class="btn btn--danger" type="submit"><?= icon('trash', 16) ?> Delete record</button></form>
    <?php endif; ?>
  </div>
</div>
<div class="grid two">
  <div class="card">
    <div class="card__head"><h3>Payment</h3><span class="spacer"></span><span class="status <?= $st['class'] ?>"><?= e($st['label']) ?></span></div>
    <div class="stack" style="gap:8px">
      <div class="between"><span class="muted small">Payer (M-Pesa number)</span><span class="mono"><?= e($p['payer'] ?: '—') ?></span></div>
      <div class="between"><span class="muted small">Bundle recipient</span><span class="mono"><?= e(($p['recipient'] ?: $p['payer']) ?: '—') ?></span></div>
      <div class="between"><span class="muted small">Amount</span><b><?= e(ksh($p['amount'])) ?></b></div>
      <div class="between"><span class="muted small">M-Pesa receipt</span>
        <span class="mono"><?= e($p['mpesa_receipt'] ?: '—') ?></span></div>
      <div class="between"><span class="muted small">Fulfilment / delivery</span><span class="tag muted">Tracked separately (v1)</span></div>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><h3>Order &amp; timeline</h3></div>
    <div class="stack" style="gap:8px">
      <div class="between"><span class="muted small">Idempotency key</span><span class="mono small"><?= e($p['client_request_id']) ?></span></div>
      <div class="between"><span class="muted small">Checkout reference</span><span class="mono small"><?= e($p['checkout_request_id'] ?: '—') ?></span></div>
      <div class="between"><span class="muted small">Result code</span><span class="mono small"><?= e($p['result_code'] ?: '—') ?></span></div>
      <div><span class="muted small">Result description</span><div class="small"><?= e($p['result_desc'] ?: '—') ?></div></div>
      <div class="between"><span class="muted small">Created (Nairobi)</span><span class="small"><?= e(fmt_nairobi($p['created_at'])) ?></span></div>
      <div class="between"><span class="muted small">Updated (Nairobi)</span><span class="small"><?= e(fmt_nairobi($p['updated_at'])) ?></span></div>
    </div>
    <div class="alert info mt small"><div>App version/source is not recorded on this legacy payment row — <span class="muted">Not available yet</span>.</div></div>
  </div>
</div>
