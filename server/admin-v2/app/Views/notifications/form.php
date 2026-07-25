<?php
use App\Services\PublishingService;
$c = $c ?? [];
$toLocal = function (?string $utc) {
    if (!$utc) return '';
    try { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d\TH:i'); }
    catch (Throwable $e) { return ''; }
};
?>
<div class="page-head">
  <div><h1><?= $isNew ? 'New campaign' : 'Edit campaign' ?></h1><div class="sub">Times are Africa/Nairobi. Payment notifications are system-only.</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/notifications')) ?>">Cancel</a></div>
</div>
<form method="post" action="<?= e(url('/notifications/save')) ?>" data-once>
  <?= App\Core\Csrf::field() ?>
  <input type="hidden" name="is_new" value="<?= $isNew ? '1' : '0' ?>">
  <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><?php endif; ?>
  <div class="grid two">
    <div class="card">
      <div class="form-grid">
        <div class="field"><label>Campaign name</label><input type="text" name="name" value="<?= e($c['name'] ?? '') ?>" required></div>
        <div class="field"><label>Type</label><select name="type"><?php foreach ($types as $k => $lab): ?><option value="<?= $k ?>" <?= ($c['type'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></div>
        <div class="field full"><label>Title</label><input type="text" name="title" value="<?= e($c['title'] ?? '') ?>" data-preview-src="#pv-title" required></div>
        <div class="field full"><label>Body</label><textarea name="body" data-preview-src="#pv-body" required><?= e($c['body'] ?? '') ?></textarea></div>
        <div class="field"><label>Linked offer <span class="muted small">(optional)</span></label>
          <select name="linked_offer_id"><option value="">None</option>
            <?php foreach ($offers as $o): ?><option value="<?= e($o['offer_id']) ?>" <?= ($c['linked_offer_id'] ?? '') === $o['offer_id'] ? 'selected' : '' ?>><?= e($o['category'] . ' · ' . $o['name'] . ' (' . $o['offer_id'] . ')') ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Deep link <span class="muted small">(optional)</span></label><input type="text" name="deep_link" value="<?= e($c['deep_link'] ?? '') ?>" placeholder="mybingwa://offers/data_6"></div>
        <div class="field"><label>Audience rule</label><input type="text" name="audience_rule" value="<?= e($c['audience_rule'] ?? 'all') ?>" placeholder="all"></div>
        <div class="field"><label>Priority</label><select name="priority"><?php foreach ($priorities as $k => $lab): ?><option value="<?= $k ?>" <?= ($c['priority'] ?? 'normal') === $k ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Schedule at (Nairobi)</label><input type="datetime-local" name="scheduled_at" value="<?= e($toLocal($c['scheduled_at'] ?? null)) ?>"></div>
        <div class="field"><label>Expires at</label><input type="datetime-local" name="expires_at" value="<?= e($toLocal($c['expires_at'] ?? null)) ?>"></div>
        <div class="field"><label>Frequency cap</label><input type="number" name="frequency_cap" value="<?= (int) ($c['frequency_cap'] ?? 1) ?>" min="0"></div>
      </div>
      <div class="row mt" style="gap:18px;flex-wrap:wrap">
        <label class="checkbox"><input type="checkbox" name="respect_quiet_hours" <?= (int) ($c['respect_quiet_hours'] ?? 1) ? 'checked' : '' ?>> Respect quiet hours</label>
        <label class="checkbox"><input type="checkbox" name="suppress_recent_purchase" <?= (int) ($c['suppress_recent_purchase'] ?? 1) ? 'checked' : '' ?>> Suppress after recent purchase</label>
        <label class="switch"><input type="checkbox" name="schedule" <?= ($c['status'] ?? '') === 'scheduled' ? 'checked' : '' ?>><span class="track"></span><span>Schedule now</span></label>
      </div>
      <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save campaign</button></div>
    </div>
    <div class="card">
      <div class="card__head"><h3>Phone preview</h3></div>
      <div class="phone">
        <div style="background:var(--surface);border:1px solid var(--divider);border-radius:14px;padding:12px;display:flex;gap:10px">
          <span class="brand__logo" style="width:28px;height:28px;border-radius:8px;font-size:14px">B</span>
          <div style="min-width:0">
            <b class="small" id="pv-title" data-empty="Notification title"><?= e($c['title'] ?? '') ?: 'Notification title' ?></b>
            <div class="small muted" id="pv-body" data-empty="Body text preview"><?= e($c['body'] ?? '') ?: 'Body text preview' ?></div>
            <div class="small muted mt">My Bingwa · now</div>
          </div>
        </div>
      </div>
      <p class="phone__note">Numbers/receipts never appear on the lock screen.</p>
    </div>
  </div>
</form>
