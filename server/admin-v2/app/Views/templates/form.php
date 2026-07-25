<?php
$t = $tpl ?? [];
$pos = $tpl ? implode("\n", json_decode((string) ($tpl['positive_samples'] ?? '[]'), true) ?: []) : '';
$neg = $tpl ? implode("\n", json_decode((string) ($tpl['negative_samples'] ?? '[]'), true) ?: []) : '';
?>
<div class="page-head">
  <div><h1><?= $isNew ? 'Add template' : 'Edit template' ?></h1><div class="sub">Patterns are checked for safety; activation needs passing samples.</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/message-templates')) ?>">Cancel</a></div>
</div>
<form method="post" action="<?= e(url('/message-templates/save')) ?>" data-once>
  <?= App\Core\Csrf::field() ?>
  <input type="hidden" name="is_new" value="<?= $isNew ? '1' : '0' ?>">
  <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><?php endif; ?>
  <div class="grid two">
    <div class="card">
      <div class="form-grid">
        <div class="field"><label>Template key</label><input type="text" name="template_key" value="<?= e($t['template_key'] ?? '') ?>" <?= $isNew ? '' : 'readonly' ?> placeholder="data_bingwa_sokoni" required></div>
        <div class="field"><label>Label</label><input type="text" name="label" value="<?= e($t['label'] ?? '') ?>" required></div>
        <div class="field"><label>Sender ID</label>
          <input type="text" name="sender_id" value="<?= e($t['sender_id'] ?? '') ?>" placeholder="Safaricom">
        </div>
        <div class="field"><label>Purpose</label><select name="purpose"><?php foreach ($purposes as $k => $lab): ?><option value="<?= $k ?>" <?= ($t['purpose'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Category</label><select name="category"><?php foreach ($categories as $c): ?><option value="<?= $c ?>" <?= ($t['category'] ?? 'DATA') === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Match priority <span class="muted small">(lower wins)</span></label><input type="number" name="match_priority" value="<?= e($t['match_priority'] ?? 5) ?>"></div>
        <div class="field full"><label>Pattern (regex)</label><input type="text" class="mono" name="pattern" value="<?= e($t['pattern'] ?? '') ?>" placeholder="received\b.*?\d+\s*(?:MB|GB)" required></div>
        <div class="field"><label>Correlation window (min)</label><input type="number" name="correlation_window_min" value="<?= e($t['correlation_window_min'] ?? 30) ?>"></div>
        <div class="field"><label class="checkbox mt"><input type="checkbox" name="case_sensitive" <?= (int) ($t['case_sensitive'] ?? 0) ? 'checked' : '' ?>> Case-sensitive</label></div>
      </div>
    </div>
    <div class="card">
      <div class="card__head"><h3>Samples (required to activate)</h3></div>
      <div class="field mb"><label>Positive samples <span class="muted small">(one per line — must match)</span></label><textarea name="positive_samples" style="min-height:110px"><?= e($pos) ?></textarea></div>
      <div class="field mb"><label>Negative samples <span class="muted small">(one per line — must NOT match)</span></label><textarea name="negative_samples" style="min-height:110px"><?= e($neg) ?></textarea></div>
      <label class="switch"><input type="checkbox" name="active" <?= ($t['status'] ?? '') === 'active' ? 'checked' : '' ?>><span class="track"></span><span>Activate (samples must pass)</span></label>
      <div class="alert info mt small"><div><?= icon('info', 14) ?> Matching detects a recognised delivery/balance message on-device. It is not proof the bundle reached the recipient.</div></div>
    </div>
  </div>
  <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save template</button></div>
</form>

<?php if (!$isNew): ?>
  <div class="card mt" style="max-width:640px">
    <div class="card__head"><?= icon('templates', 18) ?><h3>Test a sample message</h3></div>
    <p class="small muted">Paste one message to check whether it matches this saved template.</p>
    <form method="post" action="<?= e(url('/message-templates/test')) ?>">
      <?= App\Core\Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
      <div class="field mb"><textarea name="sample" placeholder="e.g. You have received 2GB valid for 24 hours"></textarea></div>
      <button class="btn btn--secondary" type="submit">Test sample</button>
    </form>
  </div>
<?php endif; ?>
