<?php $u = $user; $enabled = (int) $u['totp_enabled'] === 1; ?>
<div class="page-head">
  <div><h1>Two-factor authentication</h1><div class="sub">Time-based one-time codes (Google Authenticator, Authy, etc.).</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/settings')) ?>">Back</a></div>
</div>

<?php if (!empty($recoveryCodes)): ?>
  <div class="card mb" style="border-left:4px solid var(--orange)">
    <div class="card__head"><?= icon('key', 18) ?><h3>Recovery codes — save these now</h3></div>
    <p class="small muted">Each can be used once if you lose your authenticator. They are shown only this once.</p>
    <div class="row mt" style="gap:8px;flex-wrap:wrap">
      <?php foreach ($recoveryCodes as $c): ?><span class="tag muted mono" style="font-size:14px"><?= e($c) ?></span><?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="grid two">
  <div class="card">
    <?php if ($enabled): ?>
      <div class="card__head"><?= icon('shield', 18) ?><h3>Two-factor is on</h3><span class="spacer"></span><span class="status active">Enabled</span></div>
      <p class="small muted">To disable, confirm your password.</p>
      <form method="post" action="<?= e(url('/settings/2fa/disable')) ?>" data-confirm="Disable two-factor for your account?">
        <?= App\Core\Csrf::field() ?>
        <div class="field mb"><label>Password</label><input type="password" name="password" autocomplete="current-password" required></div>
        <button class="btn btn--danger" type="submit">Disable 2FA</button>
      </form>
    <?php else: ?>
      <div class="card__head"><?= icon('shield', 18) ?><h3>Set up two-factor</h3></div>
      <ol class="small" style="line-height:1.9;padding-left:18px">
        <li>Open your authenticator app and add an account.</li>
        <li>Scan the QR — or enter this key manually:</li>
      </ol>
      <div class="field mb"><label>Secret key</label><input class="mono" type="text" value="<?= e($pendingSecret) ?>" readonly></div>
      <div class="field mb"><label>Setup URI</label><input class="mono" type="text" value="<?= e($provisioningUri) ?>" readonly>
        <span class="hint">Paste into an authenticator that supports otpauth:// URIs.</span></div>
      <form method="post" action="<?= e(url('/settings/2fa/enable')) ?>" data-once>
        <?= App\Core\Csrf::field() ?>
        <div class="field mb"><label>Enter the current 6-digit code</label><input type="text" name="totp_code" inputmode="numeric" autocomplete="one-time-code" required></div>
        <button class="btn" type="submit"><?= icon('check', 18) ?> Enable 2FA</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card__head"><?= icon('info', 18) ?><h3>Why 2FA</h3></div>
    <p class="small muted">Two-factor adds a second step so a leaked password alone cannot sign in. Super Admins manage payment routes, publishing and forced updates — 2FA is strongly expected for those accounts.</p>
    <p class="small muted mt">Your secret is stored encrypted at rest; recovery codes are stored hashed.</p>
  </div>
</div>
