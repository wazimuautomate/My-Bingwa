<?php $c = $config; ?>
<div class="page-head">
  <div><h1>Support &amp; payment details</h1><div class="sub">Public details the app shows and caches for offline use. Changes are drafts until published.</div></div>
  <?php if (($isSuperAdmin ?? false)): ?>
    <div class="page-head__actions"><a class="btn btn--secondary" href="<?= e(url('/gateway')) ?>"><?= icon('money', 18) ?> Payment gateway (server)</a></div>
  <?php endif; ?>
</div>

<div class="grid two">
  <div class="card">
    <form method="post" action="<?= e(url('/support/save')) ?>" data-once
          data-confirm="Changing the Till or Paybill affects real payments. Confirm your password to continue." data-confirm-title="Confirm payment details" data-reauth>
      <?= App\Core\Csrf::field() ?>
      <div class="card__head"><?= icon('money', 18) ?><h3>Payment routes</h3></div>
      <div class="alert warning mb"><div><?= icon('shield', 16) ?> Till/Paybill changes need Super Admin + re-authentication and are audited.</div></div>
      <div class="form-grid">
        <div class="field"><label>Till number (Buy Goods — own number)</label><input type="text" name="till_number" value="<?= e($c['till_number'] ?? '') ?>" placeholder="4953696"></div>
        <div class="field"><label>Paybill number (another number)</label><input type="text" name="paybill_number" value="<?= e($c['paybill_number'] ?? '') ?>" placeholder="40450595"></div>
      </div>

      <div class="card__head mt"><?= icon('support', 18) ?><h3>Contact</h3></div>
      <div class="form-grid">
        <div class="field"><label>Support phone</label><input type="text" name="support_number" value="<?= e($c['support_number'] ?? '') ?>" placeholder="0727921038"></div>
        <div class="field"><label>WhatsApp (2547…)</label><input type="text" name="support_whatsapp" value="<?= e($c['support_whatsapp'] ?? '') ?>" placeholder="254727921038"></div>
        <div class="field full"><label>Working hours <span class="muted small">(optional)</span></label><input type="text" name="working_hours" value="<?= e($c['working_hours'] ?? '') ?>"></div>
        <div class="field full"><label>Support banner <span class="muted small">(optional)</span></label><input type="text" name="support_banner" value="<?= e($c['support_banner'] ?? '') ?>" maxlength="255"></div>
      </div>

      <div class="card__head mt"><?= icon('phone', 18) ?><h3>Offline purchase instructions</h3></div>
      <div class="form-grid">
        <div class="field full"><label>Own-number (Till)</label><textarea name="offline_self_instructions" data-preview-src="#pv-self"><?= e($c['offline_self_instructions'] ?? '') ?></textarea></div>
        <div class="field full"><label>Another-number (Paybill, recipient as account)</label><textarea name="offline_other_instructions" data-preview-src="#pv-other"><?= e($c['offline_other_instructions'] ?? '') ?></textarea></div>
      </div>
      <div class="mt"><button class="btn" type="submit"><?= icon('check', 18) ?> Save details</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card__head"><h3>App preview</h3></div>
    <div class="phone">
      <div style="background:var(--surface);border:1px solid var(--divider);border-radius:16px;padding:14px">
        <b>Need help?</b>
        <div class="small muted mt">Call <?= e($c['support_number'] ?? '—') ?></div>
        <div class="small muted">WhatsApp <?= e($c['support_whatsapp'] ?? '—') ?></div>
        <hr style="border:0;border-top:1px solid var(--divider);margin:10px 0">
        <b class="small">Pay offline — own number</b>
        <div class="small muted" id="pv-self" data-empty="—"><?= e($c['offline_self_instructions'] ?? '') ?: '—' ?></div>
        <b class="small mt" style="display:block">Pay offline — another number</b>
        <div class="small muted" id="pv-other" data-empty="—"><?= e($c['offline_other_instructions'] ?? '') ?: '—' ?></div>
      </div>
    </div>
    <p class="phone__note">Publish to update the live app.</p>
  </div>
</div>
