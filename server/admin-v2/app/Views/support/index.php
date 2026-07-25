<?php $c = $config; ?>
<div class="page-head">
  <div><h1>Support details</h1><div class="sub">What the app shows customers. Publish changes to apply them.</div></div>
</div>

<div class="card">
  <form method="post" action="<?= e(url('/support/save')) ?>" data-once>
    <?= App\Core\Csrf::field() ?>

    <div class="card__head"><?= icon('money', 18) ?><h3>Offline payment numbers</h3></div>
    <p class="small muted mb">Shown to customers who pay offline (not the API payment numbers).</p>
    <div class="form-grid">
      <div class="field"><label>Till number <span class="muted small">— for offline payments not fetched by API</span></label><input type="text" name="till_number" value="<?= e($c['till_number'] ?? '') ?>" placeholder="Enter Till number"></div>
      <div class="field"><label>Paybill number <span class="muted small">— for offline payments not fetched by API</span></label><input type="text" name="paybill_number" value="<?= e($c['paybill_number'] ?? '') ?>" placeholder="Enter Paybill number"></div>
    </div>

    <div class="card__head mt"><?= icon('support', 18) ?><h3>Contact</h3></div>
    <div class="form-grid">
      <div class="field"><label>Support phone</label><input type="text" name="support_number" value="<?= e($c['support_number'] ?? '') ?>" placeholder="Enter phone number"></div>
      <div class="field"><label>WhatsApp number</label><input type="text" name="support_whatsapp" value="<?= e($c['support_whatsapp'] ?? '') ?>" placeholder="Enter WhatsApp number"></div>
    </div>

    <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save details</button></div>
  </form>
</div>
