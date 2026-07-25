<?php
/** Compact top bar: mobile menu, search, preview-changes, theme, profile. */
$user = $authUser ?? [];
$initials = strtoupper(substr((string) ($user['name'] ?? 'A'), 0, 1));
$draftCount = (int) ($publishStatus['draftCount'] ?? 0);
$theme = $_COOKIE['mb_theme'] ?? 'system';
?>
<header class="topbar">
  <button class="iconbtn topbar__menu" data-toggle-nav aria-label="Open navigation"><?= icon('menu', 20) ?></button>

  <form class="search" role="search" method="get" action="<?= e(url('/offers')) ?>">
    <?= icon('search', 18) ?>
    <input type="search" name="q" placeholder="Search offers, payments…" aria-label="Search">
  </form>

  <div class="topbar__spacer"></div>

  <?php if ($draftCount > 0): ?>
    <a class="btn btn--secondary btn--sm" href="<?= e(url('/publish')) ?>">Preview changes</a>
  <?php endif; ?>

  <button class="iconbtn" data-theme-toggle title="Theme (light / dark / system)" aria-label="Toggle theme">
    <?= icon($theme === 'dark' ? 'moon' : 'sun', 18) ?>
  </button>

  <div class="dropdown">
    <button class="avatar" data-dropdown="profile-menu" aria-haspopup="true">
      <span class="avatar__img"><?= e($initials) ?></span>
      <span class="avatar__meta">
        <b><?= e($user['name'] ?? 'Admin') ?></b>
        <span><?= (int) ($user['is_super_admin'] ?? 0) === 1 ? 'Super Admin' : 'Admin' ?></span>
      </span>
      <?= icon('chevron-down', 16) ?>
    </button>
    <div class="dropdown-menu" id="profile-menu">
      <a href="<?= e(url('/settings')) ?>"><?= icon('user', 18) ?> My profile</a>
      <span data-theme-label style="display:none"></span>
      <div class="divider"></div>
      <form method="post" action="<?= e(url('/logout')) ?>">
        <?= App\Core\Csrf::field() ?>
        <button type="submit"><?= icon('logout', 18) ?> Sign out</button>
      </form>
    </div>
  </div>
</header>
