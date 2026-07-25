<?php
$c = $config;
$w = fn($k, $d) => $weights[$k] ?? $d;
$em = fn($k) => implode(', ', $emergency[$k] ?? []);
?>
<div class="page-head">
  <div><h1>App configuration</h1><div class="sub">Remote settings that control known app behaviour. Drafts until published.</div></div>
</div>

<form method="post" action="<?= e(url('/app-config/save')) ?>" data-once>
  <?= App\Core\Csrf::field() ?>
  <div class="grid two">
    <div class="stack">
      <div class="card">
        <div class="card__head"><?= icon('warning', 18) ?><h3>Maintenance mode</h3></div>
        <label class="switch mb"><input type="checkbox" name="maintenance_mode" <?= (int) ($c['maintenance_mode'] ?? 0) ? 'checked' : '' ?>><span class="track"></span><span>Enable maintenance mode</span></label>
        <div class="form-grid">
          <div class="field full"><label>Title</label><input type="text" name="maintenance_title" value="<?= e($c['maintenance_title'] ?? '') ?>" placeholder="We'll be right back"></div>
          <div class="field full"><label>Message</label><textarea name="maintenance_message" placeholder="Short, customer-safe explanation"><?= e($c['maintenance_message'] ?? '') ?></textarea></div>
        </div>
        <label class="checkbox"><input type="checkbox" name="maintenance_allow_help" <?= (int) ($c['maintenance_allow_help'] ?? 1) ? 'checked' : '' ?>> Keep Help &amp; Activity reachable during maintenance</label>
      </div>

      <div class="card">
        <div class="card__head"><?= icon('sync', 18) ?><h3>Sync &amp; caching</h3></div>
        <div class="form-grid">
          <div class="field"><label>Sync interval (min)</label><input type="number" name="sync_interval_minutes" value="<?= (int) ($c['sync_interval_minutes'] ?? 360) ?>" min="<?= $syncMin ?>" max="<?= $syncMax ?>"><span class="hint">Clamped <?= $syncMin ?>–<?= $syncMax ?> min.</span></div>
          <div class="field"><label>Snapshot cache (hours)</label><input type="number" name="snapshot_cache_hours" value="<?= (int) ($c['snapshot_cache_hours'] ?? 168) ?>" min="1"></div>
          <div class="field"><label>Offline config valid (hours)</label><input type="number" name="offline_config_valid_hours" value="<?= (int) ($c['offline_config_valid_hours'] ?? 168) ?>" min="1"></div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><?= icon('config', 18) ?><h3>Feature flags</h3></div>
        <label class="switch mb"><input type="checkbox" name="flag_billboards" <?= !empty($flags['billboards']) ? 'checked' : '' ?>><span class="track"></span><span>Billboard adverts</span></label>
        <label class="switch"><input type="checkbox" name="flag_sms_parsing" <?= !empty($flags['sms_parsing']) ? 'checked' : '' ?>><span class="track"></span><span>SMS parsing (direct build only)</span></label>
        <div class="alert warning mt"><div><?= icon('shield', 16) ?> SMS parsing must never be enabled for the Play build without confirmed Play-policy eligibility.</div></div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card__head"><?= icon('notifications', 18) ?><h3>Notifications</h3></div>
        <div class="form-grid">
          <div class="field"><label>Quiet hours start</label><input type="time" name="quiet_hours_start" value="<?= e($c['quiet_hours_start'] ?? '21:00') ?>"></div>
          <div class="field"><label>Quiet hours end</label><input type="time" name="quiet_hours_end" value="<?= e($c['quiet_hours_end'] ?? '07:00') ?>"></div>
          <div class="field"><label>Daily campaign cap</label><input type="number" name="campaign_daily_cap" value="<?= (int) ($c['campaign_daily_cap'] ?? 2) ?>" min="0"></div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><?= icon('billboards', 18) ?><h3>Billboard personalisation weights</h3></div>
        <p class="small muted mb">Bounded scoring inputs used by the on-device billboard selector.</p>
        <div class="form-grid">
          <div class="field"><label>Frequency (0–2)</label><input type="number" step="0.1" name="w_frequency" value="<?= e($w('frequency_weight', 1.0)) ?>" min="0" max="2"></div>
          <div class="field"><label>Value step-up (0–2)</label><input type="number" step="0.1" name="w_value" value="<?= e($w('value_weight', 0.6)) ?>" min="0" max="2"></div>
          <div class="field"><label>Validity (0–2)</label><input type="number" step="0.1" name="w_validity" value="<?= e($w('validity_weight', 0.4)) ?>" min="0" max="2"></div>
          <div class="field"><label>Diversity floor (0–1)</label><input type="number" step="0.05" name="w_diversity" value="<?= e($w('diversity_floor', 0.2)) ?>" min="0" max="1"></div>
          <div class="field"><label>Max step-up (1–5)</label><input type="number" step="0.1" name="w_stepup" value="<?= e($w('max_step_up', 3.0)) ?>" min="1" max="5"></div>
          <div class="field"><label>Top candidate pool (3–10)</label><input type="number" name="w_pool" value="<?= e($w('top_pool', 5)) ?>" min="3" max="10"></div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><?= icon('warning', 18) ?><h3>Emergency disable</h3></div>
        <p class="small muted mb">Comma-separated IDs to force-disable without a full publish rollback.</p>
        <div class="form-grid">
          <div class="field full"><label>Offer IDs</label><input type="text" name="disable_offers" value="<?= e($em('offers')) ?>" placeholder="data_6, spec_3"></div>
          <div class="field full"><label>Campaign IDs</label><input type="text" name="disable_campaigns" value="<?= e($em('campaigns')) ?>"></div>
          <div class="field full"><label>Payment routes</label><input type="text" name="disable_routes" value="<?= e($em('routes')) ?>" placeholder="till, paybill"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save configuration</button></div>
</form>
