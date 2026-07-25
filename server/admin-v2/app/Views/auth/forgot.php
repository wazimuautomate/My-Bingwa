<?php use App\Core\Csrf; $theme = $_COOKIE['mb_theme'] ?? 'system'; ?>
<!doctype html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Account recovery · My Bingwa Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth">
  <div class="auth__card">
    <div class="auth__brand"><span class="brand__logo">B</span><span class="brand__name">My <b>Bingwa</b></span></div>
    <h1>Account recovery</h1>
    <p class="sub">Reset your password with one of the recovery codes you saved when enabling two-factor.</p>
    <?php foreach (($flashes ?? []) as $f): ?>
      <div class="alert <?= e($f['level']) ?>" style="margin-bottom:14px"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/forgot')) ?>" data-once>
      <?= Csrf::field() ?>
      <div class="field mb"><label>Email</label><input type="email" name="email" autocomplete="username" required></div>
      <div class="field mb"><label>Recovery code</label><input type="text" name="recovery_code" required placeholder="xxxx-xxxx"></div>
      <div class="field mb"><label>New password</label><input type="password" name="new_password" autocomplete="new-password" required minlength="10"></div>
      <button class="btn btn--block" type="submit">Reset password</button>
    </form>
    <p class="text-center mt small">
      <a href="<?= e(url('/login')) ?>">Back to sign in</a>
    </p>
    <p class="small muted mt">No recovery codes? A Super Admin can reset your password from Settings → Administrators, or run <span class="mono">php database/seed.php</span> on the server for the first account.</p>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
