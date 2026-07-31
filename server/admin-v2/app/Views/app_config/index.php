<?php $c = $config; ?>
<div class="page-head">
  <div><h1>App configuration</h1><div class="sub">A few settings the app reads. Publish changes to apply them.</div></div>
</div>

<form method="post" action="<?= e(url('/app-config/save')) ?>" data-once>
  <?= App\Core\Csrf::field() ?>
  <div class="card">
    <div class="card__head"><?= icon('warning', 18) ?><h3>Maintenance mode</h3></div>
    <label class="switch mb"><input type="checkbox" name="maintenance_mode" <?= (int) ($c['maintenance_mode'] ?? 0) ? 'checked' : '' ?>><span class="track"></span><span>Turn on maintenance mode</span></label>
    <div class="field full"><label>Maintenance message</label><textarea name="maintenance_message" placeholder="Short message shown to customers during maintenance"><?= e($c['maintenance_message'] ?? '') ?></textarea></div>

    <div class="card__head mt"><?= icon('sync', 18) ?><h3>App sync</h3></div>
    <div class="field"><label>App sync interval (minutes)</label><input type="number" name="sync_interval_minutes" value="<?= (int) ($c['sync_interval_minutes'] ?? 360) ?>" min="<?= $syncMin ?>" max="<?= $syncMax ?>"><span class="hint">How often the app checks for new data. Between <?= $syncMin ?> and <?= $syncMax ?> minutes.</span></div>

    <div class="card__head mt"><?= icon('payments', 18) ?><h3>Payments</h3></div>
    <div class="field"><label>Payment Till number (Buy&nbsp;Goods)</label><input type="text" name="payment_till_number" inputmode="numeric" pattern="[0-9]*" value="<?= e($paymentTill ?? '') ?>" placeholder="Buy Goods Till, digits only"><span class="hint">The Buy&nbsp;Goods <strong>Till</strong> that collects <strong>buy&nbsp;for&nbsp;myself</strong> money. Must be a Till, never a Paybill. Applies immediately. Leave blank to use the server default.</span></div>
    <div class="field"><label>Fulfilment number</label><input type="text" name="fulfilment_number" inputmode="numeric" pattern="[0-9]*" value="<?= e($fulfilmentNumber ?? '') ?>" placeholder="07XXXXXXXX"><span class="hint">The phone that receives the <strong>buy&nbsp;for&nbsp;another</strong> notification SMS. Applies immediately. Leave blank to use the server default.</span></div>

    <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save configuration</button></div>
  </div>
</form>

<style>
  .cfg-rows { width: 100%; border-collapse: collapse; }
  .cfg-rows th { text-align: left; font-size: .8rem; font-weight: 600; padding: 6px 8px; }
  .cfg-rows td { padding: 6px 8px; vertical-align: middle; }
  .cfg-rows input[type="text"], .cfg-rows input[type="number"] { width: 100%; }
  .cfg-rows .w-key { width: 9rem; }
  .cfg-rows .w-order { width: 6rem; }
  .cfg-rows .w-on { width: 5rem; text-align: center; }
  .cfg-flag { display: grid; grid-template-columns: auto 1fr; gap: 10px; align-items: start; padding: 10px 0; }
  .cfg-flag + .cfg-flag { border-top: 1px solid var(--line, rgba(128,128,128,.2)); }
</style>

<form method="post" action="<?= e(url('/app-config/categories/save')) ?>" data-once>
  <?= App\Core\Csrf::field() ?>
  <div class="card">
    <div class="card__head"><?= icon('layers', 18) ?><h3>Offer categories</h3></div>
    <p class="small muted">The tabs customers see on the Home screen. The <b>key</b> is what an
      offer is filed under — it can never be changed once offers use it. The <b>label</b> is what
      the customer reads, and <b>order</b> decides the tab position (lowest first). Turning a
      category off hides its tab; any offers still filed under it stop appearing.</p>
    <div class="table-wrap mt">
      <table class="cfg-rows">
        <thead>
          <tr><th class="w-key">Key</th><th>Label</th><th>Description</th><th class="w-order">Order</th><th class="w-on">Shown</th></tr>
        </thead>
        <tbody>
          <?php $i = 0; foreach ($categories as $cat): ?>
            <tr>
              <td>
                <input type="text" name="category_key[]" value="<?= e($cat['category_key']) ?>"
                       class="mono" <?= (int) $cat['is_system'] === 1 ? 'readonly' : '' ?>>
              </td>
              <td><input type="text" name="category_label[]" value="<?= e($cat['label']) ?>" maxlength="40"></td>
              <td><input type="text" name="category_description[]" value="<?= e($cat['description']) ?>" maxlength="160"></td>
              <td><input type="number" name="category_sort[]" value="<?= (int) $cat['sort_order'] ?>" min="0" max="999"></td>
              <td class="w-on">
                <input type="checkbox" name="category_enabled[]" value="<?= $i ?>" <?= (int) $cat['enabled'] === 1 ? 'checked' : '' ?>>
              </td>
            </tr>
          <?php $i++; endforeach; ?>
          <?php for ($n = 0; $n < 2; $n++, $i++): ?>
            <tr>
              <td><input type="text" name="category_key[]" value="" class="mono" placeholder="NEW_KEY"></td>
              <td><input type="text" name="category_label[]" value="" maxlength="40" placeholder="Add another category"></td>
              <td><input type="text" name="category_description[]" value="" maxlength="160"></td>
              <td><input type="number" name="category_sort[]" value="<?= 200 + $n * 10 ?>" min="0" max="999"></td>
              <td class="w-on"><input type="checkbox" name="category_enabled[]" value="<?= $i ?>"></td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
    <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save categories</button></div>
  </div>
</form>

<form method="post" action="<?= e(url('/app-config/flags/save')) ?>" data-once>
  <?= App\Core\Csrf::field() ?>
  <div class="card">
    <div class="card__head"><?= icon('flag', 18) ?><h3>Feature flags</h3></div>
    <p class="small muted">Capabilities the app checks before using a feature. Turning one off is
      the fastest way to retire something without releasing a new app version.</p>
    <?php foreach ($flags as $flag): ?>
      <div class="cfg-flag">
        <label class="switch">
          <input type="checkbox" name="flags[]" value="<?= e($flag['flag_key']) ?>" <?= (int) $flag['enabled'] === 1 ? 'checked' : '' ?>>
          <span class="track"></span>
        </label>
        <div>
          <div><b><?= e($flag['label'] !== '' ? $flag['label'] : $flag['flag_key']) ?></b>
            <span class="mono small muted"><?= e($flag['flag_key']) ?></span></div>
          <div class="small muted"><?= e($flag['description']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if ($flags === []): ?>
      <div class="empty">No feature flags are defined.</div>
    <?php endif; ?>
    <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save feature flags</button></div>
  </div>
</form>
