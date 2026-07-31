<?php
/**
 * Notification rules list. Every rule is evaluated on the phone; this screen only
 * describes what the phone is allowed to do.
 */
$qs = http_build_query(array_filter($filters, static fn($v) => $v !== '' && $v !== null));
$catLabel = static function (string $key) use ($categories): string {
    return isset($categories[$key]) ? (string) $categories[$key]['label'] : ($key !== '' ? $key : '—');
};
$trigLabel = static function (string $key) use ($triggers): string {
    return isset($triggers[$key]) ? (string) $triggers[$key]['label'] : ($key !== '' ? $key : '—');
};
?>
<style>
  .nrule__name { display: block; }
  .nrule__notes { display: block; max-width: 320px; white-space: normal; }
  .nrule__sched { white-space: normal; max-width: 280px; }
  .nrule__state { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
  .nrule__live { border: 1px solid var(--divider); }
</style>

<div class="page-head">
  <div>
    <h1>Notifications</h1>
    <div class="sub">Rules the app checks on the phone. It picks one wording at random and fills in the details itself — no name, number or purchase ever reaches this server.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--secondary" href="<?= e(url('/notifications/calendar')) ?>"><?= icon('calendar', 18) ?> Schedule</a>
    <a class="btn" href="<?= e(url('/notifications/new')) ?>"><?= icon('plus', 18) ?> New notification</a>
  </div>
</div>

<div class="card">
  <form class="filters" method="get" action="<?= e(url('/notifications')) ?>">
    <div class="field"><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name or wording"></div>
    <div class="field"><select name="category">
      <option value="">Any kind</option>
      <?php foreach ($categories as $key => $cat): ?>
        <option value="<?= e($key) ?>" <?= $filters['category'] === (string) $key ? 'selected' : '' ?>><?= e($cat['label']) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="field"><select name="trigger">
      <option value="">Any moment</option>
      <?php foreach ($triggers as $key => $trg): ?>
        <option value="<?= e($key) ?>" <?= $filters['trigger'] === (string) $key ? 'selected' : '' ?>><?= e($trg['label']) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="field"><select name="status">
      <option value="">Any state</option>
      <?php foreach ($statuses as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $filters['status'] === (string) $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select></div>
    <button class="btn btn--secondary btn--sm" type="submit"><?= icon('filter', 16) ?> Filter</button>
    <?php if ($qs !== ''): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/notifications')) ?>">Clear</a><?php endif; ?>
  </form>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Notification</th><th>Kind</th><th>Shows when</th><th>Wordings</th><th>Allowed days &amp; times</th><th>State</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($campaigns as $c): $id = (int) $c['id']; $status = (string) ($c['status'] ?? 'draft'); $isLegacy = $status === 'sent'; ?>
          <tr>
            <td>
              <b class="nrule__name"><?= e($c['name']) ?></b>
              <?php if (trim((string) ($c['notes'] ?? '')) !== ''): ?>
                <span class="small muted nrule__notes"><?= e($c['notes']) ?></span>
              <?php elseif (trim((string) ($c['title'] ?? '')) !== ''): ?>
                <span class="small muted nrule__notes"><?= e(mb_strimwidth((string) $c['title'], 0, 48, '…')) ?></span>
              <?php endif; ?>
            </td>
            <td><span class="tag muted"><?= e($catLabel((string) ($c['category'] ?? ''))) ?></span></td>
            <td class="small">
              <?= e($trigLabel((string) ($c['trigger_type'] ?? ''))) ?>
              <?php if (trim((string) ($c['trigger_event'] ?? '')) !== ''): ?>
                <span class="mono muted"><?= e($c['trigger_event']) ?></span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?= icon('variations', 14) ?> <?= (int) ($c['variation_count'] ?? 0) ?>
              <?php if ((int) ($c['variation_count'] ?? 0) === 0 && !$isLegacy): ?>
                <span class="muted">none yet</span>
              <?php endif; ?>
            </td>
            <td class="small muted nrule__sched">
              <?= e((string) ($c['schedule_summary'] ?? '')) ?>
              <?php if ((int) ($c['cooldown_minutes'] ?? 0) > 0): ?>
                <br><span class="small">Rest <?= (int) $c['cooldown_minutes'] ?> min between showings</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="nrule__state">
                <span class="status <?= e($status) ?>"><?= e($statuses[$status] ?? ucfirst($status)) ?></span>
                <?php if ($isLegacy): ?>
                  <span class="small muted">Old record</span>
                <?php else: ?>
                  <span class="small muted"><?= (int) ($c['enabled'] ?? 0) === 1 ? 'Switched on' : 'Switched off' ?></span>
                <?php endif; ?>
                <?php if (!empty($c['live_now'])): ?>
                  <span class="chip nrule__live"><span class="dot"></span> Live now</span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <div class="dropdown">
                <button class="btn btn--ghost btn--sm" data-dropdown="notif-menu-<?= $id ?>" aria-haspopup="true" aria-label="Actions for <?= e($c['name']) ?>"><?= icon('more', 16) ?></button>
                <div class="dropdown-menu dropdown-menu--fixed" id="notif-menu-<?= $id ?>">
                  <a href="<?= e(url('/notifications/' . $id . '/edit')) ?>"><?= icon('edit', 18) ?> Edit</a>
                  <form method="post" action="<?= e(url('/notifications/' . $id . '/duplicate')) ?>" data-confirm="Make a switched-off draft copy of this notification?">
                    <?= App\Core\Csrf::field() ?><button type="submit"><?= icon('copy', 18) ?> Duplicate</button>
                  </form>
                  <?php if (!$isLegacy): ?>
                    <form method="post" action="<?= e(url('/notifications/' . $id . '/toggle')) ?>"<?= (int) ($c['enabled'] ?? 0) === 1 ? ' data-confirm="Switch this notification off? Publish afterwards to stop it reaching phones."' : '' ?>>
                      <?= App\Core\Csrf::field() ?>
                      <button type="submit"><?= icon('toggle', 18) ?> <?= (int) ($c['enabled'] ?? 0) === 1 ? 'Switch off' : 'Switch on' ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/notifications/' . $id . '/test')) ?>">
                      <?= App\Core\Csrf::field() ?><button type="submit"><?= icon('bell', 18) ?> Test on my devices</button>
                    </form>
                    <?php if ($status !== 'cancelled'): ?>
                      <form method="post" action="<?= e(url('/notifications/' . $id . '/cancel')) ?>" data-confirm="Retire this notification? It stops being published. Notifications already shown cannot be recalled.">
                        <?= App\Core\Csrf::field() ?><button type="submit"><?= icon('close', 18) ?> Retire</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                  <div class="divider"></div>
                  <form method="post" action="<?= e(url('/notifications/' . $id . '/delete')) ?>" data-confirm="Permanently delete this notification and all of its wordings? This cannot be undone.">
                    <?= App\Core\Csrf::field() ?>
                    <input type="hidden" name="confirm" value="1">
                    <button type="submit" class="danger"><?= icon('trash', 18) ?> Delete</button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$campaigns): ?>
          <tr><td colspan="7"><div class="empty"><?= icon('notifications', 32) ?><h3>No notifications match</h3><p>Adjust the filters, or create one the app can show at the right moment.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="small muted mt"><?= icon('clock', 14) ?> “Live now” checked at <?= e($checkedAt) ?> (Africa/Nairobi). The phone still applies quiet hours, the rest period and the daily cap before showing anything.</p>
</div>
