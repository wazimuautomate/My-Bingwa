<?php $u = $user; ?>
<div class="page-head">
  <div><h1>Settings</h1><div class="sub">Your profile, security and (for Super Admins) administrators and roles.</div></div>
  <div class="page-head__actions">
    <?php if (can('admins.manage')): ?>
      <a class="btn btn--secondary" href="<?= e(url('/settings/admins')) ?>"><?= icon('user', 18) ?> Administrators</a>
      <a class="btn btn--secondary" href="<?= e(url('/settings/roles')) ?>"><?= icon('shield', 18) ?> Roles</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid two">
  <div class="stack">
    <div class="card">
      <div class="card__head"><?= icon('user', 18) ?><h3>My profile</h3></div>
      <form method="post" action="<?= e(url('/settings/profile')) ?>">
        <?= App\Core\Csrf::field() ?>
        <div class="form-grid">
          <div class="field"><label>Name</label><input type="text" name="name" value="<?= e($u['name']) ?>" required></div>
          <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($u['email']) ?>" required></div>
        </div>
        <div class="mt"><button class="btn" type="submit">Save profile</button></div>
      </form>
    </div>

    <div class="card">
      <div class="card__head"><?= icon('key', 18) ?><h3>Password</h3></div>
      <form method="post" action="<?= e(url('/settings/password')) ?>" data-once>
        <?= App\Core\Csrf::field() ?>
        <div class="form-grid">
          <div class="field"><label>Current password</label><input type="password" name="current_password" autocomplete="current-password" required></div>
          <div class="field"><label>New password (min 10)</label><input type="password" name="new_password" autocomplete="new-password" minlength="10" required></div>
        </div>
        <div class="mt"><button class="btn" type="submit">Change password</button></div>
      </form>
    </div>

    <div class="card">
      <div class="card__head"><?= icon('shield', 18) ?><h3>Two-factor authentication</h3><span class="spacer"></span>
        <?= (int) $u['totp_enabled'] === 1 ? '<span class="status active">On</span>' : '<span class="status draft">Off</span>' ?>
      </div>
      <p class="small muted">Protect your account with a time-based one-time code. Recommended (and expected for Super Admins).</p>
      <a class="btn btn--secondary mt" href="<?= e(url('/settings/2fa')) ?>"><?= (int) $u['totp_enabled'] === 1 ? 'Manage 2FA' : 'Set up 2FA' ?></a>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card__head"><?= icon('phone', 18) ?><h3>Active sessions</h3></div>
      <?php foreach ($sessions as $s): ?>
        <div class="between" style="padding:8px 0;border-bottom:1px dashed var(--divider)">
          <div class="small">
            <b><?= $s['session_id'] === $currentSession ? 'This device' : 'Session' ?></b>
            <div class="muted mono"><?= e($s['ip']) ?> · <?= e(mb_strimwidth((string) $s['user_agent'], 0, 40, '…')) ?></div>
            <div class="muted">Last seen <?= e(fmt_nairobi($s['last_seen_at'])) ?></div>
          </div>
          <?php if ($s['session_id'] !== $currentSession): ?>
            <form method="post" action="<?= e(url('/settings/sessions/revoke')) ?>"><?= App\Core\Csrf::field() ?><input type="hidden" name="session_id" value="<?= e($s['session_id']) ?>"><button class="btn btn--danger btn--sm">Revoke</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$sessions): ?><p class="small muted">No other active sessions.</p><?php endif; ?>
    </div>

    <div class="card">
      <div class="card__head"><?= icon('config', 18) ?><h3>System</h3></div>
      <div class="stack" style="gap:8px">
        <div class="between"><span class="muted small">Environment</span><span class="tag muted"><?= e($env) ?></span></div>
        <div class="between"><span class="muted small">PHP</span><span class="mono small"><?= e($phpVersion) ?></span></div>
        <div class="between"><span class="muted small">Database</span><span><?= $dbOk ? '<span class="status active">OK</span>' : '<span class="status failed">Error</span>' ?></span></div>
        <div class="between"><span class="muted small">Snapshot signing</span><span><?= $signingConfigured ? '<span class="status active">Configured</span>' : '<span class="status draft">Not set</span>' ?></span></div>
        <div class="between"><span class="muted small">Sync API health</span><a class="small" href="<?= e(url('/api/v1/health')) ?>" target="_blank" rel="noopener">Check</a></div>
      </div>
      <?php if (can('admins.manage')): ?>
        <form method="post" action="<?= e(url('/migrate')) ?>" class="mt" data-confirm="Run any pending database migrations now?">
          <?= App\Core\Csrf::field() ?><button class="btn btn--ghost btn--sm"><?= icon('sync', 14) ?> Run DB migrations</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
