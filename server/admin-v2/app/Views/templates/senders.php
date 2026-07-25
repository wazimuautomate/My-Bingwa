<div class="page-head">
  <div><h1>Sender IDs</h1><div class="sub">Known Safaricom sender IDs used to match messages (sender + body).</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/message-templates')) ?>">Back</a></div>
</div>
<div class="grid two">
  <div class="card">
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Sender ID</th><th>Normalised</th><th>Note</th></tr></thead>
      <tbody>
        <?php foreach ($senders as $s): ?>
          <tr><td class="mono"><?= e($s['sender_id']) ?></td><td class="mono muted small"><?= e($s['normalised']) ?></td><td class="small muted"><?= e($s['note']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$senders): ?><tr><td colspan="3" class="muted">No sender IDs yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <div class="card__head"><h3>Add / update sender</h3></div>
    <form method="post" action="<?= e(url('/message-templates/senders/save')) ?>">
      <?= App\Core\Csrf::field() ?>
      <div class="field mb"><label>Sender ID</label><input type="text" name="sender_id" placeholder="SAF_Balance" required></div>
      <div class="field mb"><label>Note</label><input type="text" name="note" placeholder="Balance / deal-of-the-day notices"></div>
      <button class="btn" type="submit"><?= icon('check', 18) ?> Save sender</button>
    </form>
  </div>
</div>
