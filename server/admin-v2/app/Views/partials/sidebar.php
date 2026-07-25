<?php
/** Left navigation. Items are permission-gated (hiding is UX only; the server enforces). */
$nav = [
    ['dashboard',     'Dashboard',         '/',                 'dashboard.view',     'dashboard'],
    ['offers',        'Offers',            '/offers',           'offers.view',        'offers'],
    ['billboards',    'Billboard adverts', '/billboards',       'billboards.manage',  'billboards'],
    ['notifications', 'Notifications',     '/notifications',    'notifications.create','notifications'],
    ['templates',     'Message templates', '/message-templates','templates.manage',   'templates'],
    ['payments',      'Payments',          '/payments',         'payments.view',      'payments'],
    ['support',       'Support details',   '/support',          'support.edit',       'support'],
    ['config',        'App configuration', '/app-config',       'config.edit',        'config'],
    ['versions',      'Updates & versions','/versions',         'releases.manage',    'versions'],
    ['audit',         'Audit log',         '/audit',            'audit.view',         'audit'],
    ['settings',      'Settings',          '/settings',         null,                 'settings'],
];
$draftCount = (int) ($publishStatus['draftCount'] ?? 0);
?>
<aside class="sidebar">
  <a class="brand" href="<?= e(url('/')) ?>">
    <span class="brand__logo">B</span>
    <span class="brand__name">My <b>Bingwa</b></span>
  </a>

  <nav class="nav" aria-label="Primary">
    <?php foreach ($nav as [$icon, $label, $path, $perm, $key]): ?>
      <?php if ($perm !== null && !can($perm)) { continue; } ?>
      <a class="nav__item <?= ($activeNav ?? '') === $key ? 'is-active' : '' ?>" href="<?= e(url($path)) ?>">
        <?= icon($icon, 20) ?>
        <span><?= e($label) ?></span>
        <?php if ($key === 'dashboard' && $draftCount > 0): ?>
          <span class="nav__badge" title="Unpublished changes"><?= $draftCount ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <?php if (can('publish.execute') || can('rollback.execute')): ?>
      <div class="nav__section">Publishing</div>
      <a class="nav__item <?= ($activeNav ?? '') === 'publish' ? 'is-active' : '' ?>" href="<?= e(url('/publish')) ?>">
        <?= icon('publish', 20) ?><span>Review &amp; publish</span>
        <?php if ($draftCount > 0): ?><span class="nav__badge"><?= $draftCount ?></span><?php endif; ?>
      </a>
      <a class="nav__item <?= ($activeNav ?? '') === 'releases' ? 'is-active' : '' ?>" href="<?= e(url('/releases')) ?>">
        <?= icon('versions', 20) ?><span>Release history</span>
      </a>
    <?php endif; ?>
  </nav>

  <div class="nav__footer">
    <a class="nav__item" href="<?= e(url('/settings')) ?>"><?= icon('help', 20) ?><span>Help &amp; profile</span></a>
    <form method="post" action="<?= e(url('/logout')) ?>">
      <?= App\Core\Csrf::field() ?>
      <button class="nav__item" type="submit" style="width:100%;border:0;background:0;cursor:pointer;font:inherit">
        <?= icon('logout', 20) ?><span>Sign out</span>
      </button>
    </form>
  </div>
</aside>
