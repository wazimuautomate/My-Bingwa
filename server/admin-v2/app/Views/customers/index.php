<?php
/**
 * Customers — the register of who is using the app.
 *
 * The app submits a name and number once, at the end of onboarding. Nothing here
 * is editable: the customer owns their details on their phone. The only write is
 * removal, single or in bulk.
 *
 * No <script> anywhere — the CSP is script-src 'self', and the select-all / bulk
 * behaviour already lives in assets/js/app.js behind the data-* attributes below.
 */
use App\Repositories\CustomerRepository;
use App\Repositories\PaymentRepository;

$queryState = array_filter($filters, static fn($v): bool => (string) $v !== '');
$hasFilters = $queryState !== [];

/** Build a /customers link from the current state plus overrides ('' removes a key). */
$link = static function (array $overrides = []) use ($queryState): string {
    $q = array_filter(array_merge($queryState, $overrides), static fn($v): bool => (string) $v !== '');
    return url('/customers' . ($q ? '?' . http_build_query($q) : ''));
};
?>

<div class="page-head">
  <div>
    <h1>Customers</h1>
    <div class="sub">Everyone who has set up the app. Sent once, when they finish onboarding.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= e(url('/customers-export' . ($queryState ? '?' . http_build_query($queryState) : ''))) ?>">
      <?= icon('download', 18) ?> Export CSV
    </a>
  </div>
</div>

<?php if (!($summary['available'] ?? false)): ?>
  <div class="card">
    <div class="empty">
      <?= icon('user', 32) ?>
      <h3>No customers yet</h3>
      <p>
        The register fills itself: every phone that finishes onboarding sends its name and
        number once. If this stays empty after customers have installed the app, check that
        <code>register_user.php</code> is uploaded and that the app key matches.
      </p>
    </div>
  </div>
<?php else: ?>

<div class="grid cards mb">
  <div class="card">
    <div class="between">
      <div class="stat">
        <div class="stat__label">Total customers</div>
        <div class="stat__value"><?= number_format((int) $summary['total']) ?></div>
        <div class="small muted">Unique Safaricom numbers registered</div>
      </div>
      <div class="stat__icon blue"><?= icon('user', 20) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="between">
      <div class="stat">
        <div class="stat__label">New today</div>
        <div class="stat__value"><?= number_format((int) $summary['today']) ?></div>
        <div class="small muted">Since midnight, Nairobi time</div>
      </div>
      <div class="stat__icon green"><?= icon('user', 20) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="between">
      <div class="stat">
        <div class="stat__label">New this week</div>
        <div class="stat__value"><?= number_format((int) $summary['week']) ?></div>
        <div class="small muted">Last 7 days including today</div>
      </div>
      <div class="stat__icon blue"><?= icon('user', 20) ?></div>
    </div>
  </div>
</div>

<div class="card mt">
  <form class="pay-filters" method="get" action="<?= e(url('/customers')) ?>">
    <div class="field pay-search">
      <label for="c-q">Search</label>
      <input id="c-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Name or phone number">
    </div>
    <div class="field">
      <label for="c-from">Joined from</label>
      <input id="c-from" type="date" name="from" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
      <label for="c-to">Joined to</label>
      <input id="c-to" type="date" name="to" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <div class="row" style="gap:8px">
        <button class="btn" type="submit"><?= icon('search', 16) ?> Filter</button>
        <?php if ($hasFilters): ?>
          <a class="btn btn--ghost" href="<?= e(url('/customers')) ?>">Clear</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <?php if (can('customers.delete')): ?>
    <form method="post" action="<?= e(url('/customers/delete-bulk')) ?>"
          data-confirm="Remove the selected customers from your register? Their app keeps working — this only deletes your copy of their name and number."
          data-confirm-title="Remove selected customers">
      <?= App\Core\Csrf::field() ?>
      <div class="row between mt">
        <span class="muted small" data-bulk-count>No records selected</span>
        <button class="btn btn--danger btn--sm" type="submit" data-bulk-delete disabled>
          <?= icon('trash', 16) ?> Remove selected
        </button>
      </div>
  <?php endif; ?>

      <div class="table-wrap mt">
        <table class="data">
          <thead><tr>
            <?php if (can('customers.delete')): ?>
              <th style="width:36px"><input type="checkbox" data-check-all aria-label="Select all customers on this page"></th>
            <?php endif; ?>
            <th>Name</th><th>Phone number</th><th>Joined</th><th>Last seen</th><th>App</th><th></th>
          </tr></thead>
          <tbody>
            <?php foreach ($customers as $c): ?>
              <tr>
                <?php if (can('customers.delete')): ?>
                  <td><input type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>" data-row-check
                             aria-label="Select <?= e($c['name'] ?: 'customer') ?>"></td>
                <?php endif; ?>
                <td><?= e($c['name'] !== '' ? $c['name'] : '—') ?></td>
                <td class="mono nowrap"><?= e(CustomerRepository::displayNumber((string) $c['msisdn'])) ?></td>
                <td class="muted small nowrap"><?= e(PaymentRepository::nairobiTime($c['created_at'] ?? null)) ?></td>
                <td class="muted small nowrap"><?= e(PaymentRepository::nairobiTime($c['updated_at'] ?? null)) ?></td>
                <td class="muted small nowrap"><?= e($c['app_version'] !== '' ? $c['app_version'] : '—') ?></td>
                <td>
                  <?php if (can('customers.delete')): ?>
                    <button class="btn btn--ghost btn--sm" type="button"
                            data-modal-open="#customer-remove-<?= (int) $c['id'] ?>"
                            aria-label="Remove <?= e($c['name'] ?: 'customer') ?>"><?= icon('trash', 16) ?></button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$customers): ?>
              <tr><td colspan="<?= can('customers.delete') ? 7 : 6 ?>">
                <div class="empty">
                  <?= icon('user', 32) ?>
                  <h3><?= $hasFilters ? 'No customers match' : 'No customers yet' ?></h3>
                  <p><?= $hasFilters ? 'Adjust or clear the filters.' : 'They appear here as soon as someone finishes onboarding.' ?></p>
                </div>
              </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

  <?php if (can('customers.delete')): ?>
    </form>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
    <div class="row between mt">
      <span class="muted small">
        Page <?= (int) $page ?> of <?= (int) $pages ?> · <?= number_format((int) $total) ?> customers
      </span>
      <div class="row" style="gap:8px">
        <?php if ($page > 1): ?>
          <a class="btn btn--ghost btn--sm" href="<?= e($link(['page' => $page - 1])) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
          <a class="btn btn--ghost btn--sm" href="<?= e($link(['page' => $page + 1])) ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (can('customers.delete')): ?>
  <?php foreach ($customers as $c): ?>
    <div class="modal-backdrop" data-modal id="customer-remove-<?= (int) $c['id'] ?>" role="dialog" aria-modal="true"
         aria-label="Remove customer">
      <div class="modal">
        <div class="card__head"><h3>Remove this customer?</h3></div>
        <p class="muted">
          <b><?= e($c['name'] ?: 'This customer') ?></b> ·
          <?= e(CustomerRepository::displayNumber((string) $c['msisdn'])) ?>
        </p>
        <p class="muted small">
          This deletes your copy of their name and number. Their app keeps working, and it
          will register again if they reinstall.
        </p>
        <div class="row end mt" style="gap:8px">
          <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
          <form method="post" action="<?= e(url('/customers/' . (int) $c['id'] . '/delete')) ?>">
            <?= App\Core\Csrf::field() ?>
            <button class="btn btn--danger" type="submit"><?= icon('trash', 18) ?> Remove</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>
