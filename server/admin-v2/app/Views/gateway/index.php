<?php $g = $g ?? []; ?>
<div class="page-head">
  <div><h1>Payment gateway</h1><div class="sub">Server-side payment &amp; delivery settings. Super Admin only. These are <b>not</b> synced to the app.</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/support')) ?>">Back to support</a></div>
</div>

<div class="alert warning mb"><div><?= icon('shield', 16) ?>
  These values drive the live payment API. They take effect on the payment server only after the gateway bridge is enabled (see the cutover guide). The SMS API key is stored encrypted.
</div></div>

<form method="post" action="<?= e(url('/gateway/save')) ?>" data-once
      data-confirm="Change live payment gateway settings? Confirm your password." data-confirm-title="Confirm gateway change" data-reauth>
  <?= App\Core\Csrf::field() ?>
  <div class="grid two">
    <div class="stack">
      <div class="card">
        <div class="card__head"><?= icon('money', 18) ?><h3>Buy-for-myself route (Till)</h3></div>
        <p class="small muted mb">The Till (Buy Goods) number that receives money when a customer buys for their own line.</p>
        <div class="form-grid">
          <div class="field"><label>Transaction type</label>
            <select name="self_transaction_type">
              <option value="CustomerBuyGoodsOnline" <?= ($g['self_transaction_type'] ?? '') === 'CustomerBuyGoodsOnline' ? 'selected' : '' ?>>Buy Goods (Till)</option>
              <option value="CustomerPayBillOnline" <?= ($g['self_transaction_type'] ?? '') === 'CustomerPayBillOnline' ? 'selected' : '' ?>>Paybill</option>
            </select>
            <span class="hint">Till → BuyGoods. Paybill → shortcode must equal PartyB.</span>
          </div>
          <div class="field"><label>Business shortcode <span class="muted small">(store/HO for the password)</span></label><input type="text" name="business_shortcode" value="<?= e($g['business_shortcode'] ?? '') ?>"></div>
          <div class="field"><label>PartyB <span class="muted small">(Till number receiving money)</span></label><input type="text" name="party_b" value="<?= e($g['party_b'] ?? '') ?>"></div>
          <div class="field"><label>Daraja environment</label>
            <select name="daraja_env"><option value="production" <?= ($g['daraja_env'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option><option value="sandbox" <?= ($g['daraja_env'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option></select>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><?= icon('money', 18) ?><h3>Buy-for-another route (Paybill)</h3></div>
        <div class="form-grid">
          <div class="field"><label>Paybill shortcode <span class="muted small">(BusinessShortCode == PartyB)</span></label><input type="text" name="paybill_shortcode" value="<?= e($g['paybill_shortcode'] ?? '') ?>"></div>
          <div class="field full"><label>Callback URL</label><input type="url" name="callback_url" value="<?= e($g['callback_url'] ?? '') ?>" placeholder="https://your-domain/callback.php"></div>
        </div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card__head"><?= icon('phone', 18) ?><h3>Fulfilment SMS</h3></div>
        <p class="small muted mb">The mocked M-Pesa SMS for buy-for-another is sent here so the operator loads the recipient's line.</p>
        <div class="form-grid">
          <div class="field"><label>Fulfilment phone</label><input type="text" name="fulfilment_phone" value="<?= e($g['fulfilment_phone'] ?? '') ?>" placeholder="0110092715"></div>
          <div class="field"><label>Business name</label><input type="text" name="business_name" value="<?= e($g['business_name'] ?? 'MyBingwa') ?>"></div>
          <div class="field"><label>SMS sender ID</label><input type="text" name="sms_sender_id" value="<?= e($g['sms_sender_id'] ?? '') ?>" placeholder="SKYSCOPE_"></div>
          <div class="field"><label>SMS API URL</label><input type="url" name="sms_api_url" value="<?= e($g['sms_api_url'] ?? '') ?>"></div>
          <div class="field full"><label>SMS API key <span class="muted small">(<?= $hasSmsKey ? 'stored — leave blank to keep' : 'not set' ?>)</span></label>
            <input type="password" name="sms_api_key" autocomplete="new-password" placeholder="<?= $hasSmsKey ? '•••••••• (unchanged)' : 'Enter key' ?>">
            <?php if ($hasSmsKey): ?><span class="hint">Type a new key to replace it, or <code>__CLEAR__</code> to remove it.</span><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card__head"><?= icon('info', 18) ?><h3>Notes</h3></div>
        <ul class="small muted" style="line-height:1.8">
          <li>Deepest Daraja secrets (consumer key/secret, passkey) remain in the server config file.</li>
          <li>App-facing Till/Paybill shown to customers are set on the <a href="<?= e(url('/support')) ?>">Support</a> page.</li>
          <li>Last updated <?= e(fmt_nairobi($g['updated_at'] ?? null)) ?> by <?= e($g['updated_by'] ?? '—') ?>.</li>
        </ul>
      </div>
    </div>
  </div>
  <div class="mt"><button class="btn btn--warn" type="submit"><?= icon('check', 18) ?> Save gateway settings</button></div>
</form>
