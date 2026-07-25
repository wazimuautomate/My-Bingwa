<?php use App\Core\Auth; ?>
<div class="page-head">
  <div><h1>Administrators</h1><div class="sub">Owner (Super Admin) and partners (Admin). Permissions come from assigned roles.</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/settings')) ?>">Back</a></div>
</div>
<div class="grid two">
  <div class="card">
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Roles</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($admins as $a): $myRoles = $assignments[(int) $a['id']] ?? []; ?>
          <tr>
            <td><b><?= e($a['name']) ?></b><?= (int) $a['totp_enabled'] === 1 ? ' ' . icon('shield', 13) : '' ?></td>
            <td class="small muted"><?= e($a['email']) ?></td>
            <td><?= (int) $a['is_super_admin'] === 1 ? '<span class="tag special">Super Admin</span>' : '<span class="tag muted">Admin</span>' ?></td>
            <td class="small muted"><?= (int) $a['is_super_admin'] === 1 ? 'All' : (count($myRoles) . ' role(s)') ?></td>
            <td><?= (int) $a['status'] === 1 ? '<span class="status active">Active</span>' : '<span class="status inactive">Disabled</span>' ?></td>
            <td>
              <div class="row-actions">
                <button class="btn btn--secondary btn--sm" type="button" data-edit-admin
                  data-id="<?= (int) $a['id'] ?>" data-name="<?= e($a['name']) ?>" data-email="<?= e($a['email']) ?>"
                  data-super="<?= (int) $a['is_super_admin'] ?>" data-roles="<?= e(implode(',', $myRoles)) ?>"><?= icon('edit', 14) ?></button>
                <?php if ((int) $a['id'] !== Auth::id() && (int) $a['status'] === 1): ?>
                  <form method="post" action="<?= e(url('/settings/admins/' . (int) $a['id'] . '/disable')) ?>" data-confirm="Disable this administrator and sign them out?"><?= App\Core\Csrf::field() ?><button class="btn btn--danger btn--sm"><?= icon('close', 14) ?></button></form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card">
    <div class="card__head"><h3 id="admin-form-title">Add administrator</h3></div>
    <form method="post" action="<?= e(url('/settings/admins/save')) ?>" id="admin-form">
      <?= App\Core\Csrf::field() ?>
      <input type="hidden" name="id" id="af-id" value="0">
      <div class="field mb"><label>Name</label><input type="text" name="name" id="af-name" required></div>
      <div class="field mb"><label>Email</label><input type="email" name="email" id="af-email" required></div>
      <div class="field mb"><label>Password <span class="muted small">(leave blank to keep on edit)</span></label><input type="password" name="password" id="af-password" autocomplete="new-password"></div>
      <div class="field mb">
        <label>Roles</label>
        <?php foreach ($roles as $r): ?>
          <label class="checkbox" style="display:flex"><input type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>" class="af-role" data-role="<?= (int) $r['id'] ?>"> <?= e($r['name']) ?> <span class="muted small"><?= e($r['description']) ?></span></label>
        <?php endforeach; ?>
      </div>
      <?php if (Auth::isSuperAdmin()): ?>
        <label class="switch mb"><input type="checkbox" name="is_super_admin" id="af-super"><span class="track"></span><span>Super Admin (full control)</span></label>
      <?php endif; ?>
      <button class="btn" type="submit"><?= icon('check', 18) ?> Save administrator</button>
      <button class="btn btn--ghost" type="button" id="af-reset">New</button>
    </form>
  </div>
</div>
<script src="<?= e(asset('js/settings-admins.js')) ?>" defer></script>
