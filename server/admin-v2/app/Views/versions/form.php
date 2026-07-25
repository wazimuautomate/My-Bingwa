<?php $v = $version ?? []; ?>
<div class="page-head">
  <div><h1><?= $isNew ? 'Add release rule' : 'Edit release rule' ?></h1><div class="sub">Controls optional and forced updates. Drafts until published.</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/versions')) ?>">Cancel</a></div>
</div>
<div class="grid two">
  <div class="card">
    <form method="post" action="<?= e(url('/versions/save')) ?>" data-once
          data-confirm="A required update interrupts every eligible user. Continue?" data-confirm-title="Confirm release rule">
      <?= App\Core\Csrf::field() ?>
      <input type="hidden" name="is_new" value="<?= $isNew ? '1' : '0' ?>">
      <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $v['id'] ?>"><?php endif; ?>
      <div class="form-grid">
        <div class="field"><label>Latest versionCode</label><input type="number" name="latest_version_code" value="<?= e($v['latest_version_code'] ?? '') ?>" min="1" required></div>
        <div class="field"><label>Latest versionName</label><input type="text" name="latest_version_name" value="<?= e($v['latest_version_name'] ?? '') ?>" placeholder="1.0.0" required></div>
        <div class="field"><label>Minimum supported versionCode</label><input type="number" name="min_supported_version_code" value="<?= e($v['min_supported_version_code'] ?? 1) ?>" min="1" required></div>
        <div class="field"><label>Rollout percent</label><input type="number" name="rollout_percent" value="<?= e($v['rollout_percent'] ?? 100) ?>" min="0" max="100"></div>
        <div class="field full"><label>Play Store URL</label><input type="url" name="play_store_url" value="<?= e($v['play_store_url'] ?? '') ?>" placeholder="https://play.google.com/store/apps/details?id=com.bingwasokoni"></div>
        <div class="field full"><label>Direct APK URL <span class="muted small">(optional)</span></label><input type="url" name="apk_url" value="<?= e($v['apk_url'] ?? '') ?>"></div>
        <div class="field full"><label>APK SHA-256 <span class="muted small">(direct build)</span></label><input type="text" class="mono" name="apk_sha256" value="<?= e($v['apk_sha256'] ?? '') ?>"></div>
        <div class="field full"><label>Release notes</label><textarea name="release_notes"><?= e($v['release_notes'] ?? '') ?></textarea></div>
      </div>
      <div class="row mt" style="justify-content:space-between">
        <label class="switch"><input type="checkbox" name="mandatory" <?= (int) ($v['mandatory'] ?? 0) ? 'checked' : '' ?>><span class="track"></span><span>Required update</span></label>
        <label class="checkbox"><input type="checkbox" name="active" <?= (($v['status'] ?? 'active') === 'active' || $isNew) ? 'checked' : '' ?>> Make this the active rule</label>
      </div>
      <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save rule</button></div>
    </form>
  </div>
  <div class="card">
    <div class="card__head"><h3>Guardrails</h3></div>
    <ul class="small muted" style="line-height:1.9">
      <li><?= icon('check', 13) ?> Minimum supported ≤ latest version</li>
      <li><?= icon('check', 13) ?> Forced update requires a destination</li>
      <li><?= icon('check', 13) ?> No downgrade below the active latest</li>
      <li><?= icon('check', 13) ?> Rollout 0–100%</li>
    </ul>
  </div>
</div>
