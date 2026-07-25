<div class="page-head">
  <div><h1>Campaign schedule</h1><div class="sub">Upcoming scheduled campaigns (Africa/Nairobi).</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/notifications')) ?>">All campaigns</a></div>
</div>
<div class="card">
  <?php if (!empty($upcoming)): ?>
    <?php $lastDay = null; foreach ($upcoming as $c): $day = fmt_nairobi($c['scheduled_at'], 'l, d M Y'); ?>
      <?php if ($day !== $lastDay): $lastDay = $day; ?>
        <div class="card__head" style="margin-top:14px"><?= icon('calendar', 16) ?><h3 style="font-size:14px"><?= e($day) ?></h3></div>
      <?php endif; ?>
      <div class="between" style="padding:8px 0;border-bottom:1px dashed var(--divider)">
        <div class="row" style="gap:10px"><span class="tag muted"><?= e(fmt_nairobi($c['scheduled_at'], 'H:i')) ?></span><div><b class="small"><?= e($c['title'] ?: $c['name']) ?></b><div class="small muted"><?= e($types[$c['type']] ?? $c['type']) ?></div></div></div>
        <a class="btn btn--secondary btn--sm" href="<?= e(url('/notifications/' . (int) $c['id'] . '/edit')) ?>">Edit</a>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty"><?= icon('calendar', 32) ?><h3>Nothing scheduled</h3><p>Schedule a campaign to see it here.</p></div>
  <?php endif; ?>
</div>
