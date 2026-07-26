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
