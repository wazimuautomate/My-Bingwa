<?php
use App\Repositories\PaymentRepository;
?>
<div class="page-head">
  <div>
    <h1><?= e($greeting) ?>, <?= e($firstName) ?></h1>
    <div class="sub">Your My Bingwa control panel.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn" href="<?= e(url('/offers/new')) ?>"><?= icon('plus', 18) ?> Add offer</a>
  </div>
</div>

<!-- summary cards -->
<div class="grid cards mb">
  <div class="card">
    <div class="stat">
      <span class="stat__label">Active offers <span class="stat__icon green"><?= icon('offers', 18) ?></span></span>
      <span class="stat__value"><?= (int) $stats['activeOffers'] ?></span>
      <a class="small" href="<?= e(url('/offers')) ?>">Manage offers</a>
    </div>
  </div>
  <div class="card">
    <div class="stat">
      <span class="stat__label">Scheduled notifications <span class="stat__icon blue"><?= icon('notifications', 18) ?></span></span>
      <span class="stat__value"><?= (int) $stats['scheduledNotifications'] ?></span>
      <a class="small" href="<?= e(url('/notifications')) ?>">View notifications</a>
    </div>
  </div>
  <div class="card">
    <div class="stat">
      <span class="stat__label">Revenue (30 days) <span class="stat__icon green"><?= icon('money', 18) ?></span></span>
      <span class="stat__value"><?= $paymentsAvailable ? e(ksh($stats['revenue'])) : '—' ?></span>
      <span class="small muted"><?= $paymentsAvailable ? 'Confirmed payments' : 'No payments yet' ?></span>
    </div>
  </div>
  <div class="card">
    <div class="stat">
      <span class="stat__label">Successful payments <span class="stat__icon data"><?= icon('check', 18) ?></span></span>
      <span class="stat__value"><?= $paymentsAvailable ? (int) $stats['confirmed'] : '—' ?></span>
      <span class="small muted">Last 30 days</span>
    </div>
  </div>
</div>

<!-- latest payments -->
<div class="card">
  <div class="card__head"><h2>Latest payments</h2><span class="spacer"></span><a class="small" href="<?= e(url('/payments')) ?>">See all</a></div>
  <?php if ($paymentsAvailable && $latestPayments): ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Time</th><th>Payer</th><th>Offer</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($latestPayments as $p): $st = PaymentRepository::displayState($p['status']); ?>
            <tr>
              <td class="muted"><?= e(fmt_nairobi($p['created_at'], 'd M H:i')) ?></td>
              <td class="mono"><?= e(str_mask_phone($p['payer'])) ?></td>
              <td><?= e($p['offer_id']) ?></td>
              <td class="nowrap"><?= e(ksh($p['amount'])) ?></td>
              <td><span class="status <?= $st['class'] ?>"><?= e($st['label']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><?= icon('payments', 32) ?><h3>No payments yet</h3><p>Confirmed and pending payments will appear here.</p></div>
  <?php endif; ?>
</div>
