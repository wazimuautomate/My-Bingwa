<?php
// Group permissions by their group for a readable matrix.
$byGroup = [];
foreach ($permissions as $p) { $byGroup[$p['perm_group']][] = $p; }
?>
<div class="page-head">
  <div><h1>Roles &amp; permissions</h1><div class="sub">Grant granular permissions per role. Enforced on the server for every request.</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/settings')) ?>">Back</a></div>
</div>

<div class="stack">
  <?php foreach ($roles as $role): $granted = $rolePerms[(int) $role['id']] ?? []; ?>
    <div class="card">
      <div class="card__head"><?= icon('shield', 18) ?><h3><?= e($role['name']) ?></h3><span class="spacer"></span><span class="muted small"><?= e($role['description']) ?></span></div>
      <form method="post" action="<?= e(url('/settings/roles/save')) ?>">
        <?= App\Core\Csrf::field() ?>
        <input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">
        <div class="grid cards">
          <?php foreach ($byGroup as $group => $perms): ?>
            <div>
              <div class="small muted mb" style="text-transform:uppercase;letter-spacing:.5px"><?= e($group) ?></div>
              <?php foreach ($perms as $p): ?>
                <label class="checkbox" style="display:flex;margin:4px 0">
                  <input type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>" <?= isset($granted[(int) $p['id']]) ? 'checked' : '' ?>>
                  <span class="small"><?= e($p['label']) ?> <span class="muted mono">(<?= e($p['perm_key']) ?>)</span></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mt"><button class="btn btn--sm" type="submit"><?= icon('check', 16) ?> Save <?= e($role['name']) ?> permissions</button></div>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<div class="alert info mt"><div><?= icon('info', 16) ?> Super Admin holds every permission implicitly and is not listed here.</div></div>
