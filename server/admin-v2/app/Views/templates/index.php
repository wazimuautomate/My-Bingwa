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
              <div class="row-actions">
                <a class="btn btn--secondary btn--sm" href="<?= e(url('/message-templates/' . (int) $t['id'] . '/edit')) ?>"><?= icon('edit', 14) ?></a>
                <?php if ($t['status'] !== 'active'): ?>
                  <form method="post" action="<?= e(url('/message-templates/' . (int) $t['id'] . '/status')) ?>"><?= App\Core\Csrf::field() ?><input type="hidden" name="status" value="active"><button class="btn btn--ghost btn--sm" title="Activate"><?= icon('check', 14) ?></button></form>
                <?php else: ?>
                  <form method="post" action="<?= e(url('/message-templates/' . (int) $t['id'] . '/status')) ?>" data-confirm="Deactivate this template?"><?= App\Core\Csrf::field() ?><input type="hidden" name="status" value="draft"><button class="btn btn--ghost btn--sm" title="Deactivate"><?= icon('close', 14) ?></button></form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/message-templates/' . (int) $t['id'] . '/duplicate')) ?>"><?= App\Core\Csrf::field() ?><button class="btn btn--ghost btn--sm" title="Duplicate"><?= icon('copy', 14) ?></button></form>
                <?php if ($t['status'] === 'draft'): ?>
                  <form method="post" action="<?= e(url('/message-templates/' . (int) $t['id'] . '/delete')) ?>" data-confirm="Delete this draft template?"><?= App\Core\Csrf::field() ?><button class="btn btn--danger btn--sm" title="Delete"><?= icon('trash', 14) ?></button></form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$templates): ?><tr><td colspan="8"><div class="empty"><?= icon('templates', 32) ?><h3>No templates</h3><p>Add one, then activate after samples pass.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
