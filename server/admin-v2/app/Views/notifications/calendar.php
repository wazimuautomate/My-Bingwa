<?php
/**
 * Month view of the notification SCHEDULE WINDOWS.
 *
 * A day is marked for every rule whose date range and chosen weekdays allow it on that
 * day. The time-of-day window is deliberately ignored here — it is shown per rule below
 * the grid. Nothing on this page implies a message was ever delivered.
 */
?>
<style>
  .cal__grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
  .cal__dayname { font-size: 12px; font-weight: 600; color: var(--text-2); padding: 4px 6px; text-align: center; }
  .cal__cell { border: 1px solid var(--divider); border-radius: var(--radius-sm); min-height: 92px; padding: 6px; }
  .cal__cell--empty { border-color: transparent; }
  .cal__cell--today { border-color: var(--green); }
  .cal__num { font-size: 12px; font-weight: 600; color: var(--text-2); }
  .cal__item { display: block; font-size: 11.5px; line-height: 1.35; margin-top: 4px; padding: 2px 5px;
               border-radius: 6px; background: var(--grouped); color: var(--text);
               overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .cal__item:hover { text-decoration: none; background: var(--surface); }
  .cal__more { font-size: 11px; color: var(--text-2); margin-top: 3px; display: block; }
  .cal__prev svg { transform: rotate(180deg); }
  /* Narrow screens drop the names and keep a plain count instead. */
  .cal__count { display: none; }
  @media (max-width: 720px) {
    .cal__cell { min-height: 64px; }
    .cal__item, .cal__more { display: none; }
    .cal__count { display: block; font-size: 11px; color: var(--text-2); margin-top: 4px; }
  }
</style>

<div class="page-head">
  <div>
    <h1>Notification schedule</h1>
    <div class="sub">Which days each notification is allowed on (Africa/Nairobi). The phone still decides whether to show anything.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= e(url('/notifications')) ?>">All notifications</a>
  </div>
</div>

<div class="card">
  <div class="between mb">
    <div class="row" style="gap:8px">
      <a class="btn btn--secondary btn--sm cal__prev" href="<?= e(url('/notifications/calendar?month=' . $prevMonth)) ?>" aria-label="Previous month"><?= icon('chevron', 16) ?></a>
      <b><?= e($monthLabel) ?></b>
      <a class="btn btn--secondary btn--sm" href="<?= e(url('/notifications/calendar?month=' . $nextMonth)) ?>" aria-label="Next month"><?= icon('chevron', 16) ?></a>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/notifications/calendar?month=' . $thisMonth)) ?>"><?= icon('calendar', 16) ?> This month</a>
  </div>

  <div class="cal__grid">
    <?php foreach ($dayNames as $label): ?>
      <div class="cal__dayname"><?= e($label) ?></div>
    <?php endforeach; ?>
  </div>

  <div class="cal__grid mt">
    <?php foreach ($weeks as $week): ?>
      <?php foreach ($week as $cell): ?>
        <?php if ($cell === null): ?>
          <div class="cal__cell cal__cell--empty"></div>
        <?php else: ?>
          <div class="cal__cell <?= $cell['today'] ? 'cal__cell--today' : '' ?>">
            <div class="cal__num"><?= (int) $cell['day'] ?></div>
            <?php if ($cell['items'] !== []): ?><span class="cal__count"><?= count($cell['items']) ?></span><?php endif; ?>
            <?php foreach (array_slice($cell['items'], 0, 3) as $item): ?>
              <a class="cal__item" href="<?= e(url('/notifications/' . (int) $item['id'] . '/edit')) ?>" title="<?= e($item['name'] . ' — ' . $item['schedule_summary']) ?>"><?= e($item['name']) ?></a>
            <?php endforeach; ?>
            <?php if (count($cell['items']) > 3): ?>
              <span class="cal__more">+<?= count($cell['items']) - 3 ?> more</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>

<div class="card mt">
  <div class="card__head"><?= icon('clock', 18) ?><h3>Windows in detail</h3></div>
  <?php if (!$rules): ?>
    <div class="empty"><?= icon('calendar', 32) ?><h3>Nothing scheduled</h3><p>Create a notification to see its window here.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Notification</th><th>Allowed days &amp; times</th><th>Rest</th><th>State</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rules as $r): ?>
            <tr>
              <td><b><?= e($r['name']) ?></b></td>
              <td class="small muted" style="white-space:normal"><?= e((string) $r['schedule_summary']) ?></td>
              <td class="small muted"><?= (int) ($r['cooldown_minutes'] ?? 0) > 0 ? (int) $r['cooldown_minutes'] . ' min' : '—' ?></td>
              <td>
                <span class="status <?= e((string) ($r['status'] ?? 'draft')) ?>"><?= e(ucfirst((string) ($r['status'] ?? 'draft'))) ?></span>
                <span class="small muted"><?= (int) ($r['enabled'] ?? 0) === 1 ? 'on' : 'off' ?></span>
              </td>
              <td class="text-right"><a class="btn btn--secondary btn--sm" href="<?= e(url('/notifications/' . (int) $r['id'] . '/edit')) ?>"><?= icon('edit', 14) ?> Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
