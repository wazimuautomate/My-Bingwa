<?php
$b = $b ?? [];
$kind = $b['kind'] ?? 'simple';
$toLocal = function (?string $utc) {
    if (!$utc) return '';
    try { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d\TH:i'); }
    catch (Throwable $e) { return ''; }
};
?>
<div class="page-head">
  <div><h1><?= $isNew ? 'New billboard' : 'Edit billboard' ?></h1><div class="sub">Simple billboards generate copy from an offer with the tokens: <?= e(implode(', ', array_map(fn($t) => '{{' . $t . '}}', $tokens))) ?>.</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/billboards')) ?>">Cancel</a></div>
</div>
<form method="post" action="<?= e(url('/billboards/save')) ?>" enctype="multipart/form-data" data-once>
  <?= App\Core\Csrf::field() ?>
  <input type="hidden" name="is_new" value="<?= $isNew ? '1' : '0' ?>">
  <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><?php endif; ?>
  <div class="grid two">
    <div class="card">
      <div class="form-grid">
        <div class="field"><label>Internal name</label><input type="text" name="name" value="<?= e($b['name'] ?? '') ?>" required></div>
        <div class="field"><label>Kind</label><select name="kind"><option value="simple" <?= $kind === 'simple' ? 'selected' : '' ?>>Simple (offer-linked)</option><option value="advanced" <?= $kind === 'advanced' ? 'selected' : '' ?>>Advanced (image)</option></select></div>
        <div class="field"><label>Linked offer</label>
          <select name="linked_offer_id"><option value="">None</option>
            <?php foreach ($offers as $o): ?><option value="<?= e($o['offer_id']) ?>" <?= ($b['linked_offer_id'] ?? '') === $o['offer_id'] ? 'selected' : '' ?>><?= e($o['category'] . ' · ' . $o['name'] . ' · KSh ' . $o['price']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Priority <span class="muted small">(lower = higher)</span></label><input type="number" name="priority" value="<?= (int) ($b['priority'] ?? 5) ?>"></div>
        <div class="field"><label>Tag</label><input type="text" name="tag" value="<?= e($b['tag'] ?? '') ?>" data-preview-src="#pv-tag" placeholder="BEST VALUE"></div>
        <div class="field"><label>CTA label</label><input type="text" name="cta_label" value="<?= e($b['cta_label'] ?? 'Buy now') ?>"></div>
        <div class="field full"><label>Headline <span class="muted small">(blank = auto from offer for simple)</span></label><input type="text" name="headline" value="<?= e($b['headline'] ?? '') ?>" data-preview-src="#pv-headline" placeholder="{{offer_name}} for KSh {{price}}"></div>
        <div class="field full"><label>Body</label><input type="text" name="body" value="<?= e($b['body'] ?? '') ?>" data-preview-src="#pv-body" placeholder="Stay connected for {{validity}}."></div>
        <div class="field full"><label>CTA destination (deep link)</label><input type="text" name="cta_destination" value="<?= e($b['cta_destination'] ?? '') ?>" placeholder="mybingwa://offers/data_6"></div>
        <div class="field full"><label>Image <span class="muted small">(advanced — JPEG/PNG/WebP, max 3 MB, re-encoded)</span></label><input type="file" name="image" accept="image/*"><?php if (!empty($image)): ?><span class="hint">Current: <?= (int) $image['width'] ?>×<?= (int) $image['height'] ?></span><?php endif; ?></div>
        <div class="field full"><label>Image alt text</label><input type="text" name="alt_text" value="<?= e($b['alt_text'] ?? '') ?>" maxlength="160"></div>
        <div class="field"><label>Audience rule</label><input type="text" name="audience_rule" value="<?= e($b['audience_rule'] ?? 'all') ?>"></div>
        <div class="field"><label>Frequency cap / day <span class="muted small">(0 = none)</span></label><input type="number" name="frequency_cap" value="<?= (int) ($b['frequency_cap'] ?? 0) ?>" min="0"></div>
        <div class="field"><label>Starts at</label><input type="datetime-local" name="starts_at" value="<?= e($toLocal($b['starts_at'] ?? null)) ?>"></div>
        <div class="field"><label>Ends at</label><input type="datetime-local" name="ends_at" value="<?= e($toLocal($b['ends_at'] ?? null)) ?>"></div>
        <div class="field"><label>Status</label><select name="status"><?php foreach (['draft', 'scheduled', 'active', 'paused', 'archived'] as $s): ?><option value="<?= $s ?>" <?= ($b['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save billboard</button></div>
    </div>
    <div class="card">
      <div class="card__head"><h3>Preview (light)</h3></div>
      <div class="phone">
        <div style="background:var(--surface);border:1px solid var(--divider);border-radius:16px;padding:14px">
          <span class="tag minutes" id="pv-tag" data-empty="BEST VALUE"><?= e($b['tag'] ?? '') ?: 'BEST VALUE' ?></span>
          <div class="stat__value" style="font-size:18px;margin-top:8px" id="pv-headline" data-empty="Headline"><?= e($b['headline'] ?? '') ?: 'Headline' ?></div>
          <div class="muted small" id="pv-body" data-empty="Body"><?= e($b['body'] ?? '') ?: 'Body' ?></div>
          <div class="mt"><span class="btn btn--sm"><?= e($b['cta_label'] ?? 'Buy now') ?></span></div>
        </div>
      </div>
      <p class="phone__note">Simple billboards resolve {{tokens}} from the linked offer at publish.</p>
    </div>
  </div>
</form>
