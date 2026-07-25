<?php use App\Core\Csrf; $theme = $_COOKIE['mb_theme'] ?? 'system'; ?>
<!doctype html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Two-factor · My Bingwa Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth">
  <div class="auth__card">
    <div class="auth__brand"><span class="brand__logo">B</span><span class="brand__name">My <b>Bingwa</b></span></div>
    <h1>Two-factor verification</h1>
    <p class="sub">Enter the 6-digit code from your authenticator app.</p>
    <?php foreach (($flashes ?? []) as $f): ?>
      <div class="alert <?= e($f['level']) ?>" style="margin-bottom:14px"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/2fa')) ?>" data-once>
      <?= Csrf::field() ?>
      <div class="field mb">
        <label for="totp_code">Authentication code</label>
        <input id="totp_code" type="text" name="totp_code" inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9A-Za-z\-]*" autofocus required placeholder="123456 or a recovery code">
      </div>
      <button class="btn btn--block" type="submit">Verify</button>
    </form>
    <p class="text-center mt small"><a href="<?= e(url('/login')) ?>">Back to sign in</a></p>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
