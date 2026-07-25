<div class="page-head">
  <div><h1>Match console</h1><div class="sub">Test a sample message (sender + body) against all active templates.</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/message-templates')) ?>">Back</a></div>
</div>
<div class="grid two">
  <div class="card">
    <form method="post" action="<?= e(url('/message-templates/console')) ?>">
      <?= App\Core\Csrf::field() ?>
      <div class="field mb"><label>Sender ID</label><input type="text" name="sender" value="<?= e($sender) ?>" placeholder="Safaricom"></div>
      <div class="field mb"><label>Message body</label><textarea name="body" style="min-height:120px" placeholder="You have received Sh20=250MB 24hr from Bingwa Sokoni..."><?= e($body) ?></textarea></div>
      <button class="btn" type="submit"><?= icon('templates', 18) ?> Test match</button>
    </form>
  </div>
  <div class="card">
    <div class="card__head"><h3>Results</h3></div>
    <?php if ($results === null): ?>
      <p class="small muted">Enter a sample and test to see which templates match.</p>
    <?php elseif (!$results): ?>
      <div class="empty"><?= icon('close', 28) ?><h3>No match</h3><p>No active template matched this sender + body.</p></div>
    <?php else: ?>
      <?php if (count($results) > 1): ?><div class="alert warning mb small"><div><?= icon('warning', 14) ?> <?= count($results) ?> templates overlap. The highest-priority one wins: <b><?= e($results[0]['template']['template_key']) ?></b>.</div></div><?php endif; ?>
      <?php foreach ($results as $i => $hit): $tpl = $hit['template']; ?>
        <div class="diff-item">
          <span class="badge <?= $i === 0 ? 'added' : 'changed' ?>"><?= $i === 0 ? 'Winner' : 'Also' ?></span>
          <div>
            <b><?= e($tpl['template_key']) ?></b> <span class="tag <?= strtolower($tpl['category']) ?>"><?= e($tpl['category']) ?></span> <span class="muted small">prio <?= (int) $tpl['match_priority'] ?></span>
            <?php if (!empty($hit['captures'])): ?><div class="small muted">Captures: <?= e(json_encode($hit['captures'])) ?></div><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
