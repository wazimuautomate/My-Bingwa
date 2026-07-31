<?php
use App\Services\BillboardService;

$alwaysOn = $alwaysOn ?? [];
?>
<style>
  .bb-cal-note { margin-bottom: 16px; }
  .bb-when { font-variant-numeric: tabular-nums; }
</style>

<div class="page-head">
  <div>
    <h1>Billboard schedule</h1>
    <div class="sub">All times are Africa/Nairobi. The state is worked out from the dates every time this page loads.</div>
  </div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/billboards')) ?>">All billboards</a></div>
</div>

<div class="alert info bb-cal-note">
  <?= icon('clock', 18) ?>
  <div>An advert with a start date goes live by itself at that moment and stops by itself at the end date. Nobody has to switch it on. <b>Simple adverts ignore dates on purpose</b> — they are always on so an offer-linked advert can never quietly expire.</div>
</div>

<div class="card">
  <div class="card__head"><h3>Scheduled adverts</h3><span class="muted small"><?= (int) count($items) ?> with a window</span></div>
  <?php if (!empty($items)): ?>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Starts</th><th>Ends</th><th>Advert</th><th class="nowrap">Order</th><th>State</th></tr></thead>
      <tbody>
        <?php foreach ($items as $b): ?>
          <?php $state = (string) ($b['effective_state'] ?? 'draft'); ?>
          <tr>
            <td class="small bb-when"><?= $b['starts_at'] ? e(fmt_nairobi($b['starts_at'])) : '—' ?></td>
            <td class="small muted bb-when"><?= $b['ends_at'] ? e(fmt_nairobi($b['ends_at'])) : '—' ?></td>
            <td><a href="<?= e(url('/billboards/' . (int) $b['id'] . '/edit')) ?>"><?= e($b['name']) ?></a></td>
            <td class="nowrap"><?= (int) $b['display_order'] ?> <span class="muted small">/ p<?= (int) $b['priority'] ?></span></td>
            <td><span class="status <?= e(BillboardService::stateClass($state)) ?>"><?= e(BillboardService::stateLabel($state)) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty"><?= icon('calendar', 32) ?><h3>Nothing scheduled</h3><p>Give an advanced advert a start or end date to see it here.</p></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card__head"><h3>Always on</h3><span class="muted small"><?= (int) count($alwaysOn) ?> without a window</span></div>
  <?php if (!empty($alwaysOn)): ?>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Advert</th><th>Kind</th><th class="nowrap">Order</th><th>Why always on</th><th>State</th></tr></thead>
      <tbody>
        <?php foreach ($alwaysOn as $b): ?>
          <?php $state = (string) ($b['effective_state'] ?? 'draft'); ?>
          <tr>
            <td><a href="<?= e(url('/billboards/' . (int) $b['id'] . '/edit')) ?>"><?= e($b['name']) ?></a></td>
            <td><span class="tag <?= $b['kind'] === 'advanced' ? 'special' : 'sms' ?>"><?= e(ucfirst((string) $b['kind'])) ?></span></td>
            <td class="nowrap"><?= (int) $b['display_order'] ?> <span class="muted small">/ p<?= (int) $b['priority'] ?></span></td>
            <td class="small muted"><?= $b['kind'] === 'advanced' ? 'No start or end date set.' : 'Simple adverts ignore dates by design.' ?></td>
            <td><span class="status <?= e(BillboardService::stateClass($state)) ?>"><?= e(BillboardService::stateLabel($state)) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty"><?= icon('billboards', 32) ?><h3>None</h3><p>Every advert here has a schedule.</p></div>
  <?php endif; ?>
</div>
