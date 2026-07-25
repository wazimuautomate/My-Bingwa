<div class="page-head">
  <div><h1>Updates &amp; versions</h1><div class="sub">Version rules the app checks. Only one is active at a time.</div></div>
  <div class="page-head__actions"><a class="btn" href="<?= e(url('/versions/new')) ?>"><?= icon('plus', 18) ?> Add rule</a></div>
</div>
<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Latest</th><th>Min supported</th><th>Forced</th><th>Rollout</th><th>Destination</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($versions as $v): ?>
          <tr>
            <td><b><?= (int) $v['latest_version_code'] ?></b> <span class="muted">(<?= e($v['latest_version_name']) ?>)</span></td>
            <td><?= (int) $v['min_supported_version_code'] ?></td>
            <td><?= (int) $v['mandatory'] ? '<span class="tag minutes">Forced</span>' : '<span class="tag muted">Optional</span>' ?></td>
            <td><?= (int) $v['rollout_percent'] ?>%</td>
            <td class="small muted"><?= e($v['play_store_url'] ? 'Play Store' : ($v['apk_url'] ? 'Direct APK' : '—')) ?></td>
            <td><span class="status <?= e($v['status']) ?>"><?= ucfirst($v['status']) ?></span></td>
            <td>
              <div class="row-actions">
                <a class="btn btn--secondary btn--sm" href="<?= e(url('/versions/' . (int) $v['id'] . '/edit')) ?>"><?= icon('edit', 14) ?></a>
                <?php if ($v['status'] !== 'active'): ?>
                  <form method="post" action="<?= e(url('/versions/' . (int) $v['id'] . '/activate')) ?>"
                        data-confirm="<?= (int) $v['mandatory'] === 1 ? 'Activate this FORCED update? Every eligible user will be prompted.' : 'Make this the active version rule?' ?>">
                    <?= App\Core\Csrf::field() ?><button class="btn btn--ghost btn--sm">Activate</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$versions): ?><tr><td colspan="7"><div class="empty"><?= icon('versions', 32) ?><h3>No version rules</h3><p>Add one to control update behaviour.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
