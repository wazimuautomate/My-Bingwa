<?php
use App\Repositories\PaymentRepository;
use App\Services\PublishingService;

$catColors = ['DATA' => '--chart-2', 'SMS' => '--chart-1', 'MINUTES' => '--chart-3', 'SPECIAL' => '--chart-4'];
$catTotal = array_sum($categories) ?: 1;
$chartSpec = json_encode(['type' => 'line', 'labels' => $revenueSeries['labels'], 'values' => $revenueSeries['values'], 'prefix' => 'KSh ']);
$draftCount = (int) ($publishStatus['draftCount'] ?? 0);
?>
<div class="page-head">
  <div>
    <h1><?= e($greeting) ?>, <?= e($firstName) ?></h1>
    <div class="sub"><?= e($publishStatus['environment'] ?? 'Production') ?> · published config <b>v<?= (int) ($publishStatus['version'] ?? 0) ?></b>
      · last publish <?= e(fmt_nairobi($publishStatus['lastPublishAt'] ?? null)) ?></div>
  </div>
  <div class="page-head__actions">
    <div class="seg" role="tablist">
      <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $p => $lab): ?>
        <a class="<?= $period === $p ? 'is-active' : '' ?>" href="<?= e(url('/dashboard?period=' . $p)) ?>"><?= $lab ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($draftCount > 0): ?>
      <a class="btn btn--warn" href="<?= e(url('/publish')) ?>"><?= icon('publish', 18) ?> Review draft (<?= $draftCount ?>)</a>
    <?php else: ?>
      <a class="btn" href="<?= e(url('/offers/new')) ?>"><?= icon('plus', 18) ?> Create draft</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($warnings)): ?>
  <div class="card mb" style="border-left:4px solid var(--orange)">
    <div class="card__head"><?= icon('warning', 18) ?><h3>Needs attention</h3></div>
    <div class="stack" style="gap:8px">
      <?php foreach ($warnings as $wn): ?>
        <div class="between">
          <div class="row" style="gap:8px"><span class="status <?= $wn['level'] === 'error' ? 'failed' : 'draft' ?>"></span><span><?= e($wn['text']) ?></span></div>
          <a class="btn btn--secondary btn--sm" href="<?= e(url($wn['link'])) ?>">Fix</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- summary cards -->
<div class="grid cards mb">
  <div class="card">
    <div class="stat">
      <span class="stat__label">Active offers <span class="stat__icon green"><?= icon('offers', 18) ?></span></span>
      <span class="stat__value"><?= (int) $stats['activeOffers'] ?></span>
      <a class="small" href="<?= e(url('/offers')) ?>">Manage catalogue</a>
    </div>
  </div>
  <div class="card">
    <div class="stat">
      <span class="stat__label">Scheduled campaigns <span class="stat__icon blue"><?= icon('notifications', 18) ?></span></span>
      <span class="stat__value"><?= (int) $stats['scheduledNotifications'] ?></span>
      <a class="small" href="<?= e(url('/notifications')) ?>">View campaigns</a>
    </div>
  </div>
  <div class="card">
    <div class="stat">
      <span class="stat__label">Revenue · <?= $period ?>d <span class="stat__icon green"><?= icon('money', 18) ?></span></span>
      <span class="stat__value"><?= $paymentsAvailable ? e(ksh($stats['revenue'])) : '—' ?></span>
      <span class="small muted"><?= $paymentsAvailable ? 'Confirmed payments' : 'Not available yet' ?></span>
    </div>
  </div>
  <div class="card">
    <div class="stat">
      <span class="stat__label">Successful payments <span class="stat__icon data"><?= icon('check', 18) ?></span></span>
      <span class="stat__value"><?= $paymentsAvailable ? (int) $stats['confirmed'] : '—' ?></span>
      <span class="small muted">Last <?= $period ?> days</span>
    </div>
  </div>
  <div class="card">
    <div class="stat">
      <span class="stat__label">App sync <span class="stat__icon orange"><?= icon('sync', 18) ?></span></span>
      <span class="stat__value" style="font-size:20px"><?= $sync['signed'] ? 'Signed' : 'Unsigned' ?></span>
      <span class="small muted">v<?= (int) $sync['current'] ?> · <?= $sync['lastSuccess'] ? 'last sync ' . e(fmt_nairobi($sync['lastSuccess'], 'd M H:i')) : 'no syncs yet' ?></span>
    </div>
  </div>
</div>

<!-- trend + category -->
<div class="grid two mb">
  <div class="card card--lg">
    <div class="card__head">
      <h2>Revenue trend</h2><span class="spacer"></span>
      <span class="tag muted">Confirmed payments</span>
    </div>
    <?php if ($paymentsAvailable): ?>
      <div data-chart="<?= e($chartSpec) ?>" style="min-height:220px"></div>
    <?php else: ?>
      <div class="empty"><?= icon('money', 32) ?><h3>Not available yet</h3><p>Revenue appears once the payment table has confirmed records.</p></div>
    <?php endif; ?>
  </div>
  <div class="card card--lg">
    <div class="card__head"><h2>Purchases by category</h2></div>
    <?php if ($paymentsAvailable && $catTotal > 1): ?>
      <?php foreach ($categories as $cat => $count): ?>
        <div class="cat-row">
          <span class="tag <?= strtolower($cat) ?>"><?= e($cat) ?></span>
          <span class="bar"><span style="width:<?= round(($count / $catTotal) * 100) ?>%;background:var(<?= $catColors[$cat] ?>)"></span></span>
          <b><?= (int) $count ?></b>
        </div>
      <?php endforeach; ?>
      <p class="small muted mt">Last <?= $period ?> days · <?= array_sum($categories) ?> confirmed purchases.</p>
    <?php else: ?>
      <div class="empty"><?= icon('offers', 32) ?><h3>Not available yet</h3><p>Category mix shows after confirmed purchases exist.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- payments + right rail -->
<div class="grid two mb">
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

  <div class="stack">
    <div class="card">
      <div class="card__head"><h3>Draft changes</h3><span class="spacer"></span>
        <?php if ($draftCount > 0): ?><a class="small" href="<?= e(url('/publish')) ?>">Review</a><?php endif; ?>
      </div>
      <?php if (!empty($pending)): ?>
        <?php foreach ($pending as $it): ?>
          <div class="diff-item">
            <span class="badge <?= e($it['change_type']) ?>"><?= e(ucfirst($it['change_type'])) ?></span>
            <span><?= e($it['summary']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="small muted"><?= icon('check', 14) ?> Everything is published. No pending changes.</p>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card__head"><h3>Upcoming campaigns</h3></div>
      <?php if (!empty($upcoming)): ?>
        <?php foreach ($upcoming as $c): ?>
          <div class="between" style="padding:7px 0;border-bottom:1px dashed var(--divider)">
            <div><b class="small"><?= e($c['title'] ?: $c['name']) ?></b><div class="small muted"><?= e(fmt_nairobi($c['scheduled_at'])) ?></div></div>
            <span class="tag muted"><?= e($c['type']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="small muted">No campaigns scheduled.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- releases + audit + sync health -->
<div class="grid two">
  <div class="card">
    <div class="card__head"><h3>Recent publishes &amp; rollbacks</h3><span class="spacer"></span><a class="small" href="<?= e(url('/releases')) ?>">History</a></div>
    <?php if (!empty($recentReleases)): ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Version</th><th>By</th><th>When</th><th>Type</th></tr></thead>
        <tbody>
        <?php foreach ($recentReleases as $r): ?>
          <tr>
            <td><b>v<?= (int) $r['version'] ?></b> <?= ($r['signature'] ?? '') ? icon('shield', 13) : '' ?></td>
            <td class="muted"><?= e($r['published_by']) ?></td>
            <td class="muted"><?= e(fmt_nairobi($r['created_at'], 'd M H:i')) ?></td>
            <td><?= $r['rolled_back_from'] ? '<span class="tag minutes">Rollback of v' . (int) $r['rolled_back_from'] . '</span>' : '<span class="tag sms">Publish</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php else: ?>
      <div class="empty"><?= icon('publish', 32) ?><h3>Nothing published yet</h3><p>Publish your first configuration from Review &amp; publish.</p></div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card__head"><h3>Sync health</h3></div>
      <div class="row" style="gap:20px;align-items:flex-start">
        <div>
          <div class="small muted">Current version</div><div class="stat__value" style="font-size:22px">v<?= (int) $sync['current'] ?></div>
        </div>
        <div>
          <div class="small muted">Snapshot</div><div><span class="status <?= $sync['signed'] ? 'active' : 'draft' ?>"><?= $sync['signed'] ? 'Signed' : 'Unsigned' ?></span></div>
        </div>
        <div>
          <div class="small muted">Failed sync rate (7d)</div>
          <div><?= $sync['failedRate'] === null ? '<span class="small muted">Not available yet</span>' : e($sync['failedRate']) . '%' ?></div>
        </div>
      </div>
      <?php if ($sync['hasTelemetry']): ?>
        <p class="small mt"><?= (int) $sync['onCurrent'] ?> device(s) on current, <?= (int) $sync['onOlder'] ?> on older versions.</p>
      <?php else: ?>
        <p class="small muted mt">Per-device version telemetry is opt-in and not collected yet — <span class="muted">Not available yet</span>.</p>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card__head"><h3>Recent activity</h3><span class="spacer"></span><?php if (can('audit.view')): ?><a class="small" href="<?= e(url('/audit')) ?>">Audit log</a><?php endif; ?></div>
      <?php if (!empty($recentAudit)): ?>
        <?php foreach ($recentAudit as $a): ?>
          <div class="between" style="padding:6px 0;border-bottom:1px dashed var(--divider)">
            <div class="small"><b><?= e($a['actor_name']) ?></b> · <?= e($a['action']) ?> <span class="muted"><?= e($a['entity_type']) ?></span></div>
            <span class="small muted nowrap"><?= e(fmt_nairobi($a['created_at'], 'd M H:i')) ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="small muted">No activity recorded yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
