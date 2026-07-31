<?php
/**
 * One payment record — the reconciliation detail, plus which bundle it bought and how
 * that bundle has performed overall. Identifiers stay unmasked here on purpose.
 */
use App\Repositories\PaymentRepository;

$st = PaymentRepository::displayState($p['status']);
$tagClass = ['DATA' => 'data', 'SMS' => 'sms', 'MINUTES' => 'minutes', 'SPECIAL' => 'special'][$offerCategory] ?? 'muted';
$catLabel = ['DATA' => 'Data', 'SMS' => 'SMS', 'MINUTES' => 'Minutes', 'SPECIAL' => 'Special', 'OTHER' => 'Other'][$offerCategory] ?? 'Other';
$offerName = $offer['name'] ?? '';
$boughtFor = $buyerKind === 'other' ? 'Bought for another number' : 'Bought for themselves';
?>
<style>
.pay-perf { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; }
.pay-perf .stat__value { font-size: 22px; }
</style>

<div class="page-head">
  <div>
    <h1>Payment #<?= (int) $p['id'] ?></h1>
    <div class="sub">
      <?= $offerName !== '' ? e($offerName) : e($p['offer_id']) ?> ·
      <?= e(ksh($p['amount'])) ?> · <?= e(PaymentRepository::nairobiTime($p['created_at'])) ?>
    </div>
  </div>
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
      <div class="between"><span class="muted small">Who it was for</span><span class="small"><?= e($boughtFor) ?></span></div>
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
      <div class="between"><span class="muted small">Created (Nairobi)</span><span class="small"><?= e(PaymentRepository::nairobiTime($p['created_at'])) ?></span></div>
      <div class="between"><span class="muted small">Updated (Nairobi)</span><span class="small"><?= e(PaymentRepository::nairobiTime($p['updated_at'])) ?></span></div>
    </div>
    <div class="alert info mt small"><div>App version/source is not recorded on this legacy payment row — <span class="muted">Not available yet</span>.</div></div>
  </div>
</div>

<div class="card mt">
  <div class="card__head">
    <div>
      <h3>The bundle</h3>
      <div class="sub">What this payment bought, and how that bundle has performed across every payment recorded.</div>
    </div>
    <span class="spacer"></span>
    <a class="btn btn--secondary btn--sm" href="<?= e(url('/payments?q=' . rawurlencode((string) $p['offer_id']))) ?>"><?= icon('search', 16) ?> All payments for this bundle</a>
  </div>
  <div class="stack mb" style="gap:8px">
    <div class="between"><span class="muted small">Bundle</span>
      <span><?= $offerName !== '' ? e($offerName) : '<span class="muted">No longer in the catalogue</span>' ?></span></div>
    <div class="between"><span class="muted small">Offer id</span><span class="mono"><?= e($p['offer_id']) ?></span></div>
    <div class="between"><span class="muted small">Category</span>
      <span><a class="tag <?= e($tagClass) ?>" href="<?= e(url('/payments?category=' . rawurlencode($offerCategory))) ?>"><?= e($catLabel) ?></a></span></div>
    <div class="between"><span class="muted small">Catalogue price</span>
      <span><?= isset($offer['price']) && (int) $offer['price'] > 0 ? e(ksh($offer['price'])) : '<span class="muted">—</span>' ?></span></div>
  </div>

  <?php if ($performance): ?>
    <div class="pay-perf">
      <div class="stat">
        <div class="stat__label">Confirmed sales</div>
        <div class="stat__value"><?= (int) $performance['sales'] ?></div>
      </div>
      <div class="stat">
        <div class="stat__label">Money earned</div>
        <div class="stat__value"><?= e(ksh($performance['revenue'])) ?></div>
      </div>
      <div class="stat">
        <div class="stat__label">Payment attempts</div>
        <div class="stat__value"><?= (int) $performance['attempts'] ?></div>
      </div>
      <div class="stat">
        <div class="stat__label">Attempts completed</div>
        <div class="stat__value"><?= (int) $performance['conversion'] ?>%</div>
      </div>
    </div>
  <?php else: ?>
    <p class="small muted">Performance figures appear once payments for this bundle are recorded.</p>
  <?php endif; ?>
</div>
