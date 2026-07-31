<?php
/**
 * SMS rules list. Grouped by event, strongest priority first — the same order the app
 * applies them in, so what an operator reads here is what the phone will decide.
 */
$qs = http_build_query(array_filter($filters));
$eventLabel = function (string $key) use ($eventTypes): string {
    return (string) ($eventTypes[$key]['label'] ?? ($key !== '' ? $key : '—'));
};
$typeLabel = function (string $key) use ($patternTypes): string {
    return (string) ($patternTypes[$key]['label'] ?? ($key !== '' ? $key : '—'));
};
?>
<style>
  /* Page-scoped only: keeps the long pattern column readable without changing the shared table. */
  .rule-pattern { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
  .rule-events { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
</style>

<div class="page-head">
  <div>
    <h1>SMS rules</h1>
    <div class="sub">How the app reads Safaricom messages. A match means a recognised message arrived on the phone — never proof a bundle was delivered.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--secondary" href="<?= e(url('/sms-rules/tester')) ?>"><?= icon('flask', 18) ?> Test a rule</a>
    <a class="btn" href="<?= e(url('/sms-rules/new')) ?>"><?= icon('plus', 18) ?> Add rule</a>
  </div>
</div>

<div class="card">
  <form class="filters" method="get" action="<?= e(url('/sms-rules')) ?>">
    <div class="field">
      <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name, key or pattern">
    </div>
    <div class="field">
      <select name="event">
        <option value="">All events</option>
        <?php foreach ($eventTypes as $key => $row): ?>
          <option value="<?= e($key) ?>" <?= $filters['event'] === $key ? 'selected' : '' ?>><?= e($row['label'] ?: $key) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn--secondary btn--sm" type="submit"><?= icon('filter', 16) ?> Filter</button>
    <?php if ($qs): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/sms-rules')) ?>">Clear</a><?php endif; ?>
  </form>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Rule</th><th>Sender</th><th>Pattern type</th><th>Pattern</th><th>Event</th>
        <th>Priority</th><th>State</th><th>Updated</th><th></th>
      </tr></thead>
      <tbody>
        <?php $lastEvent = null; foreach ($rules as $r): ?>
          <?php
            $id = (int) $r['id'];
            $enabled = (int) $r['enabled'] === 1;
            $secondary = array_values(array_filter(array_map('trim', explode(',', (string) $r['secondary_events']))));
          ?>
          <?php if ($lastEvent !== $r['event_type']): $lastEvent = $r['event_type']; ?>
            <tr><td colspan="9" class="muted small"><b><?= e($eventLabel((string) $r['event_type'])) ?></b></td></tr>
          <?php endif; ?>
          <tr>
            <td>
              <?= e($r['name']) ?><br>
              <span class="muted small mono"><?= e($r['rule_key']) ?></span>
            </td>
            <td class="muted small"><?= e($r['sender_id'] !== '' ? $r['sender_id'] : 'Any sender') ?></td>
            <td class="small"><?= e($typeLabel((string) $r['pattern_type'])) ?></td>
            <td><span class="mono small rule-pattern"><?= e($r['pattern']) ?></span></td>
            <td class="small">
              <div class="rule-events">
                <span><?= e($eventLabel((string) $r['event_type'])) ?></span>
                <?php foreach ($secondary as $s): ?><span class="tag muted"><?= e($eventLabel($s)) ?></span><?php endforeach; ?>
              </div>
            </td>
            <td class="text-right"><?= (int) $r['priority'] ?></td>
            <td><span class="status <?= $enabled ? 'active' : 'draft' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span></td>
            <td class="muted small nowrap"><?= e(fmt_nairobi($r['updated_at'] ?? null)) ?></td>
            <td>
              <div class="dropdown">
                <button class="btn btn--ghost btn--sm" data-dropdown="rule-menu-<?= $id ?>" aria-haspopup="true" aria-label="Rule actions"><?= icon('more', 16) ?></button>
                <div class="dropdown-menu dropdown-menu--fixed" id="rule-menu-<?= $id ?>">
                  <button type="button" data-modal-open="#rule-modal-<?= $id ?>"><?= icon('eye', 18) ?> View</button>
                  <a href="<?= e(url('/sms-rules/' . $id . '/edit')) ?>"><?= icon('edit', 18) ?> Edit</a>
                  <a href="<?= e(url('/sms-rules/tester?rule=' . urlencode((string) $r['rule_key']))) ?>"><?= icon('flask', 18) ?> Test</a>
                  <form method="post" action="<?= e(url('/sms-rules/' . $id . '/duplicate')) ?>">
                    <?= App\Core\Csrf::field() ?><button type="submit"><?= icon('copy', 18) ?> Duplicate</button>
                  </form>
                  <?php if ($enabled): ?>
                    <form method="post" action="<?= e(url('/sms-rules/' . $id . '/toggle')) ?>" data-confirm="Disable this rule? The app stops using it after the next publish.">
                      <?= App\Core\Csrf::field() ?><button type="submit"><?= icon('close', 18) ?> Disable</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="<?= e(url('/sms-rules/' . $id . '/toggle')) ?>">
                      <?= App\Core\Csrf::field() ?><button type="submit"><?= icon('check', 18) ?> Enable</button>
                    </form>
                  <?php endif; ?>
                  <div class="divider"></div>
                  <form method="post" action="<?= e(url('/sms-rules/' . $id . '/delete')) ?>"
                        data-confirm="<?= $enabled
                            ? 'This rule is ENABLED and the app is using it. Deleting it is permanent. Continue?'
                            : 'Permanently delete this rule? This cannot be undone.' ?>">
                    <?= App\Core\Csrf::field() ?>
                    <input type="hidden" name="confirm" value="1">
                    <button type="submit" class="danger"><?= icon('trash', 18) ?> Delete</button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rules): ?>
          <tr><td colspan="9">
            <div class="empty">
              <?= icon('templates', 32) ?>
              <h3>No rules match</h3>
              <p><?= $qs ? 'Clear the filters, or add a new rule.' : 'Add a rule so the app can understand Safaricom messages.' ?></p>
            </div>
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php foreach ($rules as $r): ?>
    <?php
      $id = (int) $r['id'];
      $enabled = (int) $r['enabled'] === 1;
      $pos = json_decode((string) ($r['positive_samples'] ?? ''), true) ?: [];
      $neg = json_decode((string) ($r['negative_samples'] ?? ''), true) ?: [];
      $caps = json_decode((string) ($r['captures_json'] ?? ''), true) ?: [];
      $secondary = array_values(array_filter(array_map('trim', explode(',', (string) $r['secondary_events']))));
    ?>
    <div class="modal-backdrop" data-modal id="rule-modal-<?= $id ?>" role="dialog" aria-modal="true" aria-label="Rule <?= e($r['rule_key']) ?> details">
      <div class="modal modal--lg">
        <div class="card__head">
          <h3><?= e($r['name']) ?></h3><span class="spacer"></span>
          <span class="status <?= $enabled ? 'active' : 'draft' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <div class="stack" style="gap:8px">
          <div class="between"><span class="muted small">Rule key</span><span class="mono small"><?= e($r['rule_key']) ?></span></div>
          <?php if ((string) $r['description'] !== ''): ?>
            <div class="between"><span class="muted small">Description</span><span class="small"><?= e($r['description']) ?></span></div>
          <?php endif; ?>
          <div class="between"><span class="muted small">Sender ID</span><span class="mono small"><?= e($r['sender_id'] !== '' ? $r['sender_id'] : 'Any sender') ?></span></div>
          <div class="between"><span class="muted small">Pattern type</span><span><?= e($typeLabel((string) $r['pattern_type'])) ?></span></div>
          <div><span class="muted small">Pattern</span><div class="mono small" style="word-break:break-all;margin-top:4px"><?= e($r['pattern']) ?></div></div>
          <div class="between"><span class="muted small">Case sensitive</span><span><?= (int) $r['case_sensitive'] === 1 ? 'Yes' : 'No' ?></span></div>
          <div class="between"><span class="muted small">Event</span><span><?= e($eventLabel((string) $r['event_type'])) ?></span></div>
          <div class="between"><span class="muted small">Also raises</span><span class="small"><?= $secondary ? e(implode(', ', array_map($eventLabel, $secondary))) : '—' ?></span></div>
          <div class="between"><span class="muted small">Category hint</span><span class="small"><?= e($r['category'] !== '' ? $r['category'] : '—') ?></span></div>
          <div class="between"><span class="muted small">Bundle type hint</span><span class="small"><?= e($r['bundle_type'] !== '' ? $r['bundle_type'] : '—') ?></span></div>
          <div class="between"><span class="muted small">Extracted values</span><span class="mono small"><?= $caps ? e(implode(', ', array_map(fn($n, $g) => $n . '=group ' . $g, array_keys($caps), $caps))) : '—' ?></span></div>
          <div class="between"><span class="muted small">Correlation window</span><span><?= (int) $r['correlation_window_min'] ?> min</span></div>
          <div class="between"><span class="muted small">Priority</span><span><?= (int) $r['priority'] ?></span></div>
          <div class="between"><span class="muted small">Positive samples</span><span><?= count($pos) ?></span></div>
          <div class="between"><span class="muted small">Negative samples</span><span><?= count($neg) ?></span></div>
          <div class="between"><span class="muted small">Last updated</span><span class="small"><?= e(fmt_nairobi($r['updated_at'] ?? null)) ?><?= !empty($r['updated_by']) ? ' · ' . e($r['updated_by']) : '' ?></span></div>
        </div>
        <div class="modal__actions">
          <a class="btn btn--secondary" href="<?= e(url('/sms-rules/tester?rule=' . urlencode((string) $r['rule_key']))) ?>">Test</a>
          <a class="btn btn--secondary" href="<?= e(url('/sms-rules/' . $id . '/edit')) ?>">Edit</a>
          <button class="btn" type="button" data-modal-close>Close</button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
