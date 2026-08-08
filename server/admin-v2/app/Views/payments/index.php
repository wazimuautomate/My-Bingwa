<?php
/**
 * Payments — offer/bundle performance and the reconciliation ledger.
 *
 * Every card, the bundle table and the record list read the SAME query parameters, so a
 * figure and the rows behind it always agree. Identifiers stay unmasked here on purpose:
 * this is the owner-only reconciliation view.
 *
 * No <script> anywhere — the CSP is script-src 'self', and the bulk-select behaviour
 * already lives in assets/js/app.js behind the data-* attributes used below.
 */
use App\Repositories\PaymentRepository;

/** The current query state, minus defaults, so links stay short and honest. */
$queryState = array_filter($filters, static fn($v): bool => (string) $v !== '');
if (($queryState['sort'] ?? '') === 'revenue') {
    unset($queryState['sort']);
}
$hasFilters = false;
foreach (['category', 'buyer', 'state', 'from', 'to', 'q', 'min', 'max'] as $k) {
    if ((string) $filters[$k] !== '') { $hasFilters = true; break; }
}

/** Build a /payments link from the current state plus overrides ('' removes a key). */
$link = static function (array $overrides = []) use ($queryState): string {
    $q = array_filter(array_merge($queryState, $overrides), static fn($v): bool => (string) $v !== '');
    return url('/payments' . ($q ? '?' . http_build_query($q) : ''));
};
/** Clicking an already-applied filter clears it. */
$toggle = static function (string $key, string $value) use ($filters, $link): string {
    return $link([$key => $filters[$key] === $value ? '' : $value, 'page' => '']);
};

$tagClass = static function (string $category): string {
    $map = ['DATA' => 'data', 'SMS' => 'sms', 'MINUTES' => 'minutes', 'SPECIAL' => 'special'];
    return $map[strtoupper($category)] ?? 'muted';
};
$catLabel = ['DATA' => 'Data', 'SMS' => 'SMS', 'MINUTES' => 'Minutes', 'SPECIAL' => 'Special', 'OTHER' => 'Other'];

$pages = (int) ceil($total / max(1, $per));
$bulk = !empty($canDelete) && $rows;
$cols = 9 + ($bulk ? 1 : 0);
$windowAverage = $windowSales > 0 ? (int) round($windowRevenue / $windowSales) : 0;
$exportQs = http_build_query($queryState);

// 14-day bars are scaled against the busiest day in the series.
$maxDay = 0;
foreach ($series['revenue'] as $v) { $maxDay = max($maxDay, (int) $v); }
$seriesTotal = array_sum($series['sales']);

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
<style>
.pay-chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 16px; }
.pay-split { display: flex; flex-direction: column; gap: 2px; }
.pay-split a, .pay-split .r {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 8px 10px; border-radius: var(--radius-sm); color: var(--text);
}
.pay-split a:hover { background: var(--grouped); text-decoration: none; }
.pay-split .is-on { background: var(--grouped); box-shadow: inset 2px 0 0 var(--green); }
.pay-split .k { display: flex; align-items: center; gap: 8px; font-size: 13.5px; }
.pay-split .v { text-align: right; white-space: nowrap; }
.pay-split .v b { font-family: var(--font-title); font-size: 15px; }
.pay-split .v em { display: block; font-style: normal; font-size: 11.5px; color: var(--text-2); }
.pay-bars { display: flex; align-items: flex-end; gap: 5px; height: 132px; }
.pay-bars .b { flex: 1 1 0; height: 100%; display: flex; align-items: flex-end; }
.pay-bars .b > span { display: block; width: 100%; min-height: 3px; border-radius: 5px 5px 0 0; background: var(--green); }
.pay-bars .b.is-zero > span { background: var(--divider); }
.pay-axis { display: flex; gap: 5px; margin-top: 8px; }
.pay-axis span { flex: 1 1 0; text-align: center; font-size: 10.5px; color: var(--text-2); overflow: hidden; }
.pay-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: end; margin-bottom: 16px; }
.pay-filters .field > label { font-size: 12px; color: var(--text-2); }
.pay-filters input, .pay-filters select {
  width: 100%; font-family: inherit; font-size: 13.5px; color: var(--text);
  background: var(--surface); border: 1px solid var(--divider);
  border-radius: var(--radius-sm); padding: 8px 11px;
}
.pay-filters .pay-actions { display: flex; gap: 8px; align-items: center; }
.pay-search { grid-column: 1 / -1; }
.pay-sorts { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.pay-offer { display: flex; flex-direction: column; gap: 4px; }
.pay-offer__meta { display: flex; align-items: center; justify-content: center; gap: 8px; }
</style>

<div class="page-head">
  <div>
    <h1>Payments</h1>
    <div class="sub">How the bundles are performing, and the full payment record. Fulfilment is tracked separately from payment.</div>
  </div>
  <?php if ($canReveal): ?>
    <div class="page-head__actions">
      <a class="btn btn--secondary" href="<?= e(url('/payments-export' . ($exportQs ? '?' . $exportQs : ''))) ?>"><?= icon('download', 18) ?> Export CSV</a>
    </div>
  <?php endif; ?>
</div>

<?php if (!$available): ?>
  <div class="alert info mb"><?= icon('info', 20) ?>
    <div>No payments have been recorded yet. The figures below stay at zero until the first payment reaches the payment API.</div>
  </div>
<?php endif; ?>

<div class="pay-chips">
  <span class="chip"><?= icon('calendar', 16) ?> <?= e($windowLabel) ?></span>
  <?php if ($filters['category'] !== ''): ?>
    <a class="chip" href="<?= e($toggle('category', $filters['category'])) ?>"><?= icon('layers', 16) ?> <?= e($catLabel[$filters['category']] ?? $filters['category']) ?> <?= icon('close', 14) ?></a>
  <?php endif; ?>
  <?php if ($filters['buyer'] !== ''): ?>
    <a class="chip" href="<?= e($toggle('buyer', $filters['buyer'])) ?>"><?= icon('user', 16) ?> <?= $filters['buyer'] === 'self' ? 'Bought for themselves' : 'Bought for another number' ?> <?= icon('close', 14) ?></a>
  <?php endif; ?>
  <?php if ($filters['state'] !== ''): ?>
    <a class="chip" href="<?= e($toggle('state', $filters['state'])) ?>"><?= icon('flag', 16) ?> <?= e(PaymentRepository::displayState($filters['state'])['label']) ?> <?= icon('close', 14) ?></a>
  <?php endif; ?>
  <?php if ($hasFilters): ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/payments')) ?>"><?= icon('close', 16) ?> Clear filters</a>
  <?php endif; ?>
</div>

<!-- ------------------------------------------------------------ money cards -->
<div class="grid cards mb">
  <div class="card">
    <div class="between">
      <div class="stat">
        <div class="stat__label">Money in today</div>
        <div class="stat__value"><?= e(ksh($summary['todayRevenue'])) ?></div>
        <div class="small muted"><?= (int) $summary['todaySales'] ?> confirmed sale<?= (int) $summary['todaySales'] === 1 ? '' : 's' ?></div>
      </div>
      <div class="stat__icon green"><?= icon('money', 20) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="between">
      <div class="stat">
        <div class="stat__label">Money in — all time</div>
        <div class="stat__value"><?= e(ksh($summary['totalRevenue'])) ?></div>
        <div class="small muted"><?= (int) $summary['totalSales'] ?> confirmed sale<?= (int) $summary['totalSales'] === 1 ? '' : 's' ?></div>
      </div>
      <div class="stat__icon green"><?= icon('payments', 20) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="between">
      <div class="stat">
        <div class="stat__label">In this view</div>
        <div class="stat__value"><?= e(ksh($windowRevenue)) ?></div>
        <div class="small muted"><?= (int) $windowSales ?> sale<?= (int) $windowSales === 1 ? '' : 's' ?> · <?= e($windowLabel) ?></div>
      </div>
      <div class="stat__icon blue"><?= icon('filter', 20) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="between">
      <div class="stat">
        <div class="stat__label">Average sale</div>
        <div class="stat__value"><?= e(ksh($windowAverage)) ?></div>
        <div class="small muted"><?= e(ksh($summary['averageSale'])) ?> all time</div>
      </div>
      <div class="stat__icon blue"><?= icon('up', 20) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="between">
      <div class="stat">
        <div class="stat__label">Attempts completed</div>
        <div class="stat__value"><?= (int) $outcomes['confirmed'] ?><small> / <?= (int) $outcomes['total'] ?></small></div>
        <div class="small muted"><?= (int) $outcomes['successRate'] ?>% of payment attempts were received</div>
      </div>
      <div class="stat__icon <?= (int) $outcomes['successRate'] >= 50 ? 'green' : 'blue' ?>"><?= icon('check', 20) ?></div>
    </div>
  </div>
</div>

<!-- --------------------------------------------- category / buyer / outcomes -->
<div class="grid cards mb">
  <div class="card">
    <div class="card__head"><h3>Sales by category</h3></div>
    <div class="pay-split">
      <?php foreach ($categoryKeys as $key):
        $c = $categories[$key] ?? ['sales' => 0, 'revenue' => 0];
        if ($key === 'OTHER' && (int) $c['sales'] === 0 && $filters['category'] !== 'OTHER') { continue; }
      ?>
        <a href="<?= e($toggle('category', $key)) ?>" class="<?= $filters['category'] === $key ? 'is-on' : '' ?>"
           aria-label="Filter this page to <?= e($catLabel[$key] ?? $key) ?>">
          <span class="k"><span class="tag <?= $tagClass($key) ?>"><?= e($catLabel[$key] ?? $key) ?></span></span>
          <span class="v"><b><?= e(ksh($c['revenue'])) ?></b><em><?= (int) $c['sales'] ?> sale<?= (int) $c['sales'] === 1 ? '' : 's' ?></em></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ((int) $windowSales === 0): ?>
      <p class="small muted mt">No confirmed sales in this window yet.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card__head"><h3>Who the bundle was for</h3></div>
    <div class="pay-split">
      <a href="<?= e($toggle('buyer', 'self')) ?>" class="<?= $filters['buyer'] === 'self' ? 'is-on' : '' ?>">
        <span class="k"><?= icon('user', 18) ?> For themselves</span>
        <span class="v"><b><?= (int) $trend['self']['sales'] ?></b><em><?= e(ksh($trend['self']['revenue'])) ?></em></span>
      </a>
      <a href="<?= e($toggle('buyer', 'other')) ?>" class="<?= $filters['buyer'] === 'other' ? 'is-on' : '' ?>">
        <span class="k"><?= icon('phone', 18) ?> For another number</span>
        <span class="v"><b><?= (int) $trend['other']['sales'] ?></b><em><?= e(ksh($trend['other']['revenue'])) ?></em></span>
      </a>
    </div>
    <div class="bar mt" role="img" aria-label="<?= (int) $trend['selfShare'] ?>% of confirmed sales were bought for the payer's own number">
      <span style="width:<?= (int) $trend['selfShare'] ?>%"></span>
    </div>
    <p class="small muted mt">
      <?php if ((int) $trend['total'] === 0): ?>
        The split appears after the first confirmed sale.
      <?php else: ?>
        <?= (int) $trend['selfShare'] ?>% bought for themselves · <?= 100 - (int) $trend['selfShare'] ?>% for another number.
      <?php endif; ?>
    </p>
  </div>

  <div class="card">
    <div class="card__head"><h3>Payment outcomes</h3><span class="spacer"></span><span class="small muted"><?= (int) $outcomes['successRate'] ?>% received</span></div>
    <div class="pay-split">
      <?php
        $stateCounts = $outcomes['states'];
        foreach ($states as $s) { $stateCounts[$s] = $stateCounts[$s] ?? 0; }
        foreach ($stateCounts as $s => $count):
          $d = PaymentRepository::displayState((string) $s);
          $share = (int) $outcomes['total'] > 0 ? (int) round($count * 100 / (int) $outcomes['total']) : 0;
      ?>
        <a href="<?= e($toggle('state', (string) $s)) ?>" class="<?= $filters['state'] === (string) $s ? 'is-on' : '' ?>">
          <span class="k"><span class="status <?= e($d['class']) ?>"><?= e($d['label']) ?></span></span>
          <span class="v"><b><?= (int) $count ?></b><em><?= $share ?>%</em></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ((int) $outcomes['total'] === 0): ?>
      <p class="small muted mt">No payment attempts in this window yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ------------------------------------------------------- bundle performance -->
<div class="card mb">
  <div class="card__head">
    <div>
      <h3>Bundle performance</h3>
      <div class="sub">Confirmed sales, money earned and how many attempts completed — <?= e($windowLabel) ?>.</div>
    </div>
    <span class="spacer"></span>
    <div class="pay-sorts">
      <span class="small muted">Sort by</span>
      <?php foreach (['revenue' => 'Revenue', 'sales' => 'Sales', 'conversion' => 'Conversion'] as $key => $label): ?>
        <a class="btn btn--sm <?= $filters['sort'] === $key ? '' : 'btn--secondary' ?>"
           href="<?= e($link(['sort' => $key === 'revenue' ? '' : $key, 'page' => ''])) ?>"
           aria-label="Sort bundles by <?= e(strtolower($label)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Bundle</th><th>Category</th><th>Price</th><th>Sales</th>
        <th>Revenue</th><th>Attempts</th><th>Conversion</th>
      </tr></thead>
      <tbody>
        <?php foreach ($bundles as $b): $bc = strtoupper((string) $b['category']); ?>
          <tr>
            <td>
              <div class="pay-offer">
                <span><?= $b['name'] !== '' ? e($b['name']) : '<span class="muted">Offer no longer in the catalogue</span>' ?></span>
                <span class="mono muted"><?= e($b['offer_id']) ?></span>
              </div>
            </td>
            <td><span class="tag <?= $tagClass($bc) ?>"><?= e($catLabel[$bc] ?? ($bc !== '' ? $bc : 'Other')) ?></span></td>
            <td class="nowrap"><?= (int) $b['price'] > 0 ? e(ksh($b['price'])) : '<span class="muted">—</span>' ?></td>
            <td><b><?= (int) $b['sales'] ?></b></td>
            <td class="nowrap"><?= e(ksh($b['revenue'])) ?></td>
            <td class="muted"><?= (int) $b['attempts'] ?></td>
            <td><?= (int) $b['conversion'] ?>%</td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$bundles): ?>
          <tr><td colspan="7"><div class="empty"><?= icon('offers', 28) ?><h3>No bundle sales yet</h3>
            <p>Each bundle appears here once it has been paid for at least once in this window.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- --------------------------------------------------------------- last 14 days -->
<div class="card mb">
  <div class="card__head">
    <div>
      <h3>Last 14 days</h3>
      <div class="sub">Money received per Nairobi day. <?= (int) $seriesTotal ?> confirmed sale<?= (int) $seriesTotal === 1 ? '' : 's' ?> in this period.</div>
    </div>
  </div>
  <div class="pay-bars" role="group" aria-label="Money received on each of the last 14 days">
    <?php foreach ($series['labels'] as $i => $label):
      $rev = (int) ($series['revenue'][$i] ?? 0);
      $sold = (int) ($series['sales'][$i] ?? 0);
      $height = $maxDay > 0 ? max(2, (int) round($rev * 100 / $maxDay)) : 2;
      $desc = $label . ': ' . ksh($rev) . ' from ' . $sold . ' sale' . ($sold === 1 ? '' : 's');
    ?>
      <div class="b <?= $rev === 0 ? 'is-zero' : '' ?>" role="img" aria-label="<?= e($desc) ?>" title="<?= e($desc) ?>">
        <span style="height:<?= $height ?>%"></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="pay-axis" aria-hidden="true">
    <?php foreach ($series['labels'] as $label): ?><span><?= e($label) ?></span><?php endforeach; ?>
  </div>
  <?php if ($maxDay === 0): ?>
    <p class="small muted mt">No money has come in over the last 14 days.</p>
  <?php endif; ?>
</div>

<!-- ------------------------------------------------- when people buy (hourly) -->
<div class="card mt">
  <div class="card__head">
    <div>
      <h3>When people buy</h3>
      <div class="sub">
        Confirmed sales by hour of the Nairobi day, <?= e(strtolower($windowLabel)) ?>.
        <?php if ($hourly['peakHour'] !== null && (int) $hourly['peakSales'] > 0): ?>
          Busiest hour: <b><?= e($hourly['labels'][$hourly['peakHour']]) ?></b>
          (<?= (int) $hourly['peakSales'] ?> sale<?= (int) $hourly['peakSales'] === 1 ? '' : 's' ?>).
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php $maxHour = max(1, max($hourly['sales'])); ?>
  <div class="row" style="align-items:flex-end;gap:4px;height:150px;margin-top:8px">
    <?php foreach ($hourly['labels'] as $h => $label): ?>
      <?php
        $count = (int) $hourly['sales'][$h];
        $height = (int) round(($count / $maxHour) * 120);
      ?>
      <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:4px"
           title="<?= e($label) ?> — <?= $count ?> sale<?= $count === 1 ? '' : 's' ?>, <?= e(ksh((int) $hourly['revenue'][$h])) ?>">
        <span class="small muted" style="font-size:10px"><?= $count > 0 ? $count : '' ?></span>
        <span style="width:100%;height:<?= max($count > 0 ? 3 : 1, $height) ?>px;border-radius:3px 3px 0 0;background:<?= $count > 0 ? 'var(--brand)' : 'var(--divider)' ?>"></span>
        <span class="small muted" style="font-size:9px"><?= $h % 3 === 0 ? (int) $h : '' ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted small mt">
    Hours are Nairobi time, worked out from the measured database clock — not the server's
    own hour, which on this kind of host is often UTC and would read three hours early.
  </p>
</div>

<!-- --------------------------------------- once-a-day vs repeatable, regulars -->
<div class="grid halves mt">
  <div class="card">
    <div class="card__head"><h3>Once a day vs repeatable</h3></div>
    <?php
      $policyTotal = (int) $policy['once']['sales'] + (int) $policy['repeatable']['sales'] + (int) $policy['unknown']['sales'];
      $policyRows = [
        ['once', 'Once-a-day bundles', 'Limited to one purchase per number per day'],
        ['repeatable', 'Repeatable bundles', 'Can be bought again the same day'],
        ['unknown', 'Offer no longer in the catalogue', 'Sold before the offer was removed'],
      ];
    ?>
    <?php if ($policyTotal === 0): ?>
      <p class="muted small">No confirmed sales in this period yet.</p>
    <?php else: ?>
      <div class="stack" style="gap:12px">
        <?php foreach ($policyRows as [$key, $label, $note]): ?>
          <?php
            $sales = (int) $policy[$key]['sales'];
            if ($key === 'unknown' && $sales === 0) { continue; }
            $share = $policyTotal > 0 ? (int) round($sales * 100 / $policyTotal) : 0;
          ?>
          <div>
            <div class="between">
              <span><b><?= e($label) ?></b> <span class="muted small"><?= e($note) ?></span></span>
              <span class="nowrap"><?= $sales ?> · <?= e(ksh((int) $policy[$key]['revenue'])) ?></span>
            </div>
            <div class="bar mt" style="height:8px;background:var(--grouped);border-radius:4px;overflow:hidden">
              <span style="display:block;height:8px;width:<?= $share ?>%;background:var(--brand)"></span>
            </div>
            <span class="muted small"><?= $share ?>% of confirmed sales</span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card__head">
      <h3>Bundles with regulars</h3>
      <span class="spacer"></span>
      <span class="small muted">sales per line</span>
    </div>
    <p class="muted small">
      How many different numbers bought each bundle, and how many sales that produced.
      Well above 1.0 means the same people keep coming back for it.
    </p>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Bundle</th><th>Sales</th><th>Numbers</th><th>Per line</th><th>Revenue</th></tr></thead>
        <tbody>
          <?php foreach ($regulars as $r): ?>
            <tr>
              <td>
                <?= e($r['name'] !== '' ? $r['name'] : $r['offer_id']) ?>
                <?php if ($r['category'] !== ''): ?>
                  <span class="tag <?= e($tagClass($r['category'])) ?>"><?= e($catLabel[strtoupper($r['category'])] ?? $r['category']) ?></span>
                <?php endif; ?>
              </td>
              <td class="nowrap"><?= (int) $r['sales'] ?></td>
              <td class="nowrap"><?= (int) $r['lines'] ?></td>
              <td class="nowrap"><b><?= e(number_format((float) $r['perLine'], 1)) ?></b></td>
              <td class="nowrap"><?= e(ksh((int) $r['revenue'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$regulars): ?>
            <tr><td colspan="5"><span class="muted small">No confirmed sales in this period yet.</span></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ------------------------------------------------------------ records + filters -->
<div class="card">
  <div class="card__head">
    <div>
      <h3>Payment records</h3>
      <div class="sub"><?= (int) $total ?> record<?= (int) $total === 1 ? '' : 's' ?> match<?= (int) $total === 1 ? 'es' : '' ?> this view. Full details are shown unmasked for reconciliation.</div>
    </div>
  </div>

  <form class="pay-filters" method="get" action="<?= e(url('/payments')) ?>">
    <div class="field pay-search">
      <label for="f-q">Search</label>
      <input id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Offer id or M-Pesa receipt">
    </div>
    <div class="field">
      <label for="f-from">From (date)</label>
      <input id="f-from" type="date" name="from" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
      <label for="f-to">To (date)</label>
      <input id="f-to" type="date" name="to" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field">
      <label for="f-category">Category</label>
      <select id="f-category" name="category">
        <option value="">Any category</option>
        <?php foreach ($categoryKeys as $key): ?>
          <option value="<?= e($key) ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= e($catLabel[$key] ?? $key) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="f-buyer">Bought for</label>
      <select id="f-buyer" name="buyer">
        <option value="">Anyone</option>
        <option value="self" <?= $filters['buyer'] === 'self' ? 'selected' : '' ?>>Themselves</option>
        <option value="other" <?= $filters['buyer'] === 'other' ? 'selected' : '' ?>>Another number</option>
      </select>
    </div>
    <div class="field">
      <label for="f-state">State</label>
      <select id="f-state" name="state">
        <option value="">Any state</option>
        <?php foreach ($states as $s): ?>
          <option value="<?= e($s) ?>" <?= $filters['state'] === $s ? 'selected' : '' ?>><?= e(PaymentRepository::displayState($s)['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="f-min">Amount from</label>
      <input id="f-min" type="number" min="0" name="min" value="<?= e($filters['min']) ?>" placeholder="KSh">
    </div>
    <div class="field">
      <label for="f-max">Amount to</label>
      <input id="f-max" type="number" min="0" name="max" value="<?= e($filters['max']) ?>" placeholder="KSh">
    </div>
    <?php if ($filters['sort'] !== 'revenue'): ?><input type="hidden" name="sort" value="<?= e($filters['sort']) ?>"><?php endif; ?>
    <div class="pay-actions">
      <button class="btn btn--secondary btn--sm"><?= icon('filter', 16) ?> Filter</button>
      <?php if ($hasFilters): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/payments')) ?>">Clear filters</a><?php endif; ?>
    </div>
  </form>

  <?php if (!empty($capped)): ?>
    <div class="alert warning mb"><?= icon('warning', 20) ?>
      <div>Only the 5,000 most recent matching records are filtered here. Narrow the date range to reach older records.</div>
    </div>
  <?php endif; ?>

  <?php if ($bulk): ?>
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
      <thead><tr>
        <?php if ($bulk): ?><th class="nowrap"><input type="checkbox" form="pay-bulk-form" data-check-all aria-label="Select all payment records on this page"></th><?php endif; ?>
        <th>Time</th><th>Payer</th><th>Recipient</th><th>Bought for</th><th>Offer</th>
        <th>Amount</th><th>Receipt</th><th>State</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $p): $st = PaymentRepository::displayState($p['status']); ?>
          <tr>
            <?php if ($bulk): ?><td class="nowrap"><input type="checkbox" form="pay-bulk-form" name="ids[]" value="<?= (int) $p['id'] ?>" data-row-check aria-label="Select payment <?= (int) $p['id'] ?>"></td><?php endif; ?>
            <td class="muted nowrap"><?= e(PaymentRepository::nairobiTime($p['created_at'], 'd M H:i')) ?></td>
            <td class="mono"><?= e($p['payer'] ?: '—') ?></td>
            <td class="mono"><?= e(($p['recipient'] ?: $p['payer']) ?: '—') ?></td>
            <td class="small"><?= $p['_buyer'] === 'other' ? 'Another number' : 'Themselves' ?></td>
            <td>
              <div class="pay-offer">
                <span><?= $p['_offer_name'] !== '' ? e($p['_offer_name']) : '<span class="muted">Not in the catalogue</span>' ?></span>
                <span class="pay-offer__meta">
                  <span class="tag <?= $tagClass((string) $p['_offer_category']) ?>"><?= e($catLabel[(string) $p['_offer_category']] ?? $p['_offer_category']) ?></span>
                  <span class="mono muted"><?= e($p['offer_id']) ?></span>
                </span>
              </div>
            </td>
            <td class="nowrap"><?= e(ksh($p['amount'])) ?></td>
            <td class="mono small"><?= e($p['mpesa_receipt'] ?: '—') ?></td>
            <td><span class="status <?= $st['class'] ?>"><?= e($st['label']) ?></span></td>
            <td>
              <div class="dropdown">
                <button class="btn btn--ghost btn--sm" data-dropdown="pay-menu-<?= (int) $p['id'] ?>" aria-haspopup="true" aria-label="Payment actions"><?= icon('more', 16) ?></button>
                <div class="dropdown-menu dropdown-menu--fixed" id="pay-menu-<?= (int) $p['id'] ?>">
                  <button type="button" data-modal-open="#pay-modal-<?= (int) $p['id'] ?>"><?= icon('eye', 18) ?> View details</button>
                  <a href="<?= e(url('/payments/' . (int) $p['id'])) ?>"><?= icon('external', 18) ?> Open full page</a>
                  <a href="<?= e($link(['q' => $p['offer_id'], 'page' => ''])) ?>"><?= icon('offers', 18) ?> Other sales of this bundle</a>
                  <?php if (!empty($canDelete)): ?>
                    <div class="divider"></div>
                    <form method="post" action="<?= e(url('/payments/' . (int) $p['id'] . '/delete')) ?>" data-confirm="Delete this payment record? This cannot be undone."><?= App\Core\Csrf::field() ?><button type="submit" class="danger"><?= icon('trash', 18) ?> Delete record</button></form>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="<?= (int) $cols ?>"><div class="empty"><?= icon('payments', 28) ?>
            <h3><?= $hasFilters ? 'No payments match' : 'No payments yet' ?></h3>
            <p><?= $hasFilters ? 'Try a wider date range, or clear the filters.' : 'Records appear here as soon as the first customer pays.' ?></p>
          </div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php foreach ($rows as $p): $st = PaymentRepository::displayState($p['status']); ?>
    <div class="modal-backdrop" data-modal id="pay-modal-<?= (int) $p['id'] ?>" role="dialog" aria-modal="true" aria-label="Payment <?= (int) $p['id'] ?> details">
      <div class="modal modal--lg">
        <div class="card__head"><h3>Payment #<?= (int) $p['id'] ?></h3><span class="spacer"></span><span class="status <?= $st['class'] ?>"><?= e($st['label']) ?></span></div>
        <p class="small muted mb">Operator reconciliation view — full, unmasked details.</p>
        <div class="stack" style="gap:8px">
          <div class="between"><span class="muted small">Bundle</span>
            <span class="small"><?= $p['_offer_name'] !== '' ? e($p['_offer_name']) : '<span class="muted">Not in the catalogue</span>' ?>
              <span class="tag <?= $tagClass((string) $p['_offer_category']) ?>"><?= e($catLabel[(string) $p['_offer_category']] ?? $p['_offer_category']) ?></span></span></div>
          <div class="between"><span class="muted small">Bought for</span>
            <span class="small"><?= $p['_buyer'] === 'other' ? 'Another number' : 'Themselves' ?></span></div>
          <?php foreach ($p as $k => $v):
            if (is_string($k) && $k !== '' && $k[0] === '_') { continue; } // view-only additions
            $lab = $payLabels[$k] ?? ucwords(str_replace('_', ' ', (string) $k));
            if ($k === 'created_at' || $k === 'updated_at') { $disp = PaymentRepository::nairobiTime($v); }
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
        <a class="btn <?= $pp === $page ? '' : 'btn--secondary' ?> btn--sm" href="<?= e($link(['page' => $pp])) ?>"><?= (int) $pp ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
