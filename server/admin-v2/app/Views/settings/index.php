<?php $u = $user; $theme = $_COOKIE['mb_theme'] ?? 'system'; ?>
<div class="page-head">
  <div><h1>Settings</h1><div class="sub">Your name, password and appearance.</div></div>
</div>

<div class="grid two">
  <div class="stack">
    <div class="card">
      <div class="card__head"><?= icon('user', 18) ?><h3>My name &amp; email</h3></div>
      <form method="post" action="<?= e(url('/settings/profile')) ?>">
        <?= App\Core\Csrf::field() ?>
        <div class="form-grid">
          <div class="field"><label>Name</label><input type="text" name="name" value="<?= e($u['name']) ?>" required></div>
          <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($u['email']) ?>" required></div>
        </div>
        <div class="mt"><button class="btn" type="submit">Save</button></div>
      </form>
    </div>

    <div class="card">
      <div class="card__head"><?= icon('key', 18) ?><h3>Change password</h3></div>
      <form method="post" action="<?= e(url('/settings/password')) ?>" data-once>
        <?= App\Core\Csrf::field() ?>
        <div class="form-grid">
          <div class="field"><label>Current password</label><input type="password" name="current_password" autocomplete="current-password" required></div>
          <div class="field"><label>New password (min 10)</label><input type="password" name="new_password" autocomplete="new-password" minlength="10" required></div>
        </div>
        <div class="mt"><button class="btn" type="submit">Change password</button></div>
      </form>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card__head"><?= icon('sun', 18) ?><h3>Appearance</h3></div>
      <p class="small muted">Choose how the admin looks.</p>
      <div class="row" style="gap:8px;margin-top:8px">
        <?php foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $val => $lab): ?>
          <button type="button" class="btn <?= $theme === $val ? '' : 'btn--secondary' ?>" data-set-theme="<?= $val ?>"><?= $lab ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!empty($isSuperAdmin)): ?>
      <div class="card">
        <div class="card__head"><?= icon('user', 18) ?><h3>Partner Admin</h3></div>
        <p class="small muted">Add a second administrator and choose which pages they can see and edit.</p>
        <a class="btn btn--secondary mt" href="<?= e(url('/settings/admins')) ?>">Manage partner Admin</a>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card__head"><?= icon('logout', 18) ?><h3>Sign out</h3></div>
      <form method="post" action="<?= e(url('/logout')) ?>">
        <?= App\Core\Csrf::field() ?>
        <button class="btn btn--secondary" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('[data-set-theme]').forEach(function (b) {
  b.addEventListener('click', function () {
    var t = b.getAttribute('data-set-theme');
    document.cookie = 'mb_theme=' + t + ';path=/;max-age=31536000;samesite=Lax';
    document.documentElement.setAttribute('data-theme', t);
    location.reload();
  });
});
</script>
