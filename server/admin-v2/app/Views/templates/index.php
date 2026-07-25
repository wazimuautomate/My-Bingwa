<div class="page-head">
  <div><h1>Message templates</h1><div class="sub">Recognise Safaricom delivery &amp; low-balance messages. A match means a recognised message arrived — not verified fulfilment.</div></div>
  <div class="page-head__actions">
    <a class="btn" href="<?= e(url('/message-templates/new')) ?>"><?= icon('plus', 18) ?> Add template</a>
  </div>
</div>
<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Key</th><th>Purpose</th><th>Sender</th><th>Category</th><th>Pattern</th><th>Prio</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($templates as $t): ?>
          <tr>
            <td class="mono"><?= e($t['template_key']) ?><br><span class="muted small"><?= e($t['label']) ?></span></td>
            <td class="small"><?= e($purposes[$t['purpose']] ?? $t['purpose']) ?></td>
            <td class="muted small"><?= e($t['sender_id']) ?></td>
            <td><span class="tag <?= strtolower($t['category']) ?>"><?= e($t['category']) ?></span></td>
            <td class="mono small" style="max-width:240px;overflow:hidden;text-overflow:ellipsis"><?= e($t['pattern']) ?></td>
            <td><?= (int) $t['match_priority'] ?></td>
            <td><span class="status <?= e($t['status']) ?>"><?= ucfirst($t['status']) ?></span></td>
            <td>
              <div class="dropdown">
                <button class="btn btn--ghost btn--sm" data-dropdown="tpl-menu-<?= (int) $t['id'] ?>" aria-haspopup="true" aria-label="Template actions"><?= icon('more', 16) ?></button>
                <div class="dropdown-menu dropdown-menu--fixed" id="tpl-menu-<?= (int) $t['id'] ?>">
                  <button type="button" data-modal-open="#tpl-modal-<?= (int) $t['id'] ?>"><?= icon('eye', 18) ?> View</button>
                  <a href="<?= e(url('/message-templates/' . (int) $t['id'] . '/edit')) ?>"><?= icon('edit', 18) ?> Edit</a>
                  <?php if ($t['status'] !== 'active'): ?>
                    <form method="post" action="<?= e(url('/message-templates/' . (int) $t['id'] . '/status')) ?>"><?= App\Core\Csrf::field() ?><input type="hidden" name="status" value="active"><button type="submit"><?= icon('check', 18) ?> Activate</button></form>
                  <?php else: ?>
                    <form method="post" action="<?= e(url('/message-templates/' . (int) $t['id'] . '/status')) ?>" data-confirm="Deactivate this template?"><?= App\Core\Csrf::field() ?><input type="hidden" name="status" value="draft"><button type="submit"><?= icon('close', 18) ?> Deactivate</button></form>
                  <?php endif; ?>
                  <form method="post" action="<?= e(url('/message-templates/' . (int) $t['id'] . '/duplicate')) ?>"><?= App\Core\Csrf::field() ?><button type="submit"><?= icon('copy', 18) ?> Duplicate</button></form>
                  <?php if ($t['status'] === 'draft'): ?>
                    <div class="divider"></div>
                    <form method="post" action="<?= e(url('/message-templates/' . (int) $t['id'] . '/delete')) ?>" data-confirm="Delete this draft template?"><?= App\Core\Csrf::field() ?><button type="submit" class="danger"><?= icon('trash', 18) ?> Delete</button></form>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$templates): ?><tr><td colspan="8"><div class="empty"><?= icon('templates', 32) ?><h3>No templates</h3><p>Add one, then activate after samples pass.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php foreach ($templates as $t):
    $pos = json_decode((string) ($t['positive_samples'] ?? ''), true) ?: [];
    $neg = json_decode((string) ($t['negative_samples'] ?? ''), true) ?: [];
  ?>
    <div class="modal-backdrop" data-modal id="tpl-modal-<?= (int) $t['id'] ?>" role="dialog" aria-modal="true" aria-label="Template <?= e($t['template_key']) ?> details">
      <div class="modal modal--lg">
        <div class="card__head"><h3><?= e($t['template_key']) ?></h3><span class="spacer"></span><span class="status <?= e($t['status']) ?>"><?= ucfirst($t['status']) ?></span></div>
        <div class="stack" style="gap:8px">
          <div class="between"><span class="muted small">Label</span><span><?= e($t['label']) ?></span></div>
          <div class="between"><span class="muted small">Purpose</span><span><?= e($purposes[$t['purpose']] ?? $t['purpose']) ?></span></div>
          <div class="between"><span class="muted small">Sender ID</span><span class="mono small"><?= e($t['sender_id'] ?: '—') ?></span></div>
          <div class="between"><span class="muted small">Category</span><span class="tag <?= strtolower($t['category']) ?>"><?= e($t['category']) ?></span></div>
          <div class="between"><span class="muted small">Case sensitive</span><span><?= ((int) $t['case_sensitive'] === 1) ? 'Yes' : 'No' ?></span></div>
          <div class="between"><span class="muted small">Match priority</span><span><?= (int) $t['match_priority'] ?></span></div>
          <div class="between"><span class="muted small">Correlation window</span><span><?= (int) $t['correlation_window_min'] ?> min</span></div>
          <div><span class="muted small">Pattern</span><div class="mono small" style="word-break:break-all;margin-top:4px"><?= e($t['pattern']) ?></div></div>
          <div class="between"><span class="muted small">Positive samples</span><span><?= count($pos) ?></span></div>
          <div class="between"><span class="muted small">Negative samples</span><span><?= count($neg) ?></span></div>
          <div class="between"><span class="muted small">Last updated</span><span class="small"><?= e(fmt_nairobi($t['updated_at'] ?? null)) ?></span></div>
        </div>
        <div class="modal__actions">
          <a class="btn btn--secondary" href="<?= e(url('/message-templates/' . (int) $t['id'] . '/edit')) ?>">Edit</a>
          <button class="btn" type="button" data-modal-close>Close</button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
