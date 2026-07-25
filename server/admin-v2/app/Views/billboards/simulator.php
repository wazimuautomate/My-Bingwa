<?php $bh = $behaviour ?? []; $cc = $bh['categoryCounts'] ?? []; ?>
<div class="page-head">
  <div><h1>Why-this-billboard simulator</h1><div class="sub">Enter anonymous sample behaviour and inspect the transparent scoring — no opaque AI.</div></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/billboards')) ?>">Back</a></div>
</div>
<div class="grid two">
  <div class="card">
    <div class="card__head"><h3>Sample behaviour</h3></div>
    <form method="post" action="<?= e(url('/billboards/simulator')) ?>">
      <?= App\Core\Csrf::field() ?>
      <div class="form-grid">
        <div class="field"><label>DATA purchases</label><input type="number" name="c_data" value="<?= (int) ($cc['DATA'] ?? 3) ?>" min="0"></div>
        <div class="field"><label>SMS purchases</label><input type="number" name="c_sms" value="<?= (int) ($cc['SMS'] ?? 0) ?>" min="0"></div>
        <div class="field"><label>MINUTES purchases</label><input type="number" name="c_minutes" value="<?= (int) ($cc['MINUTES'] ?? 1) ?>" min="0"></div>
        <div class="field"><label>SPECIAL purchases</label><input type="number" name="c_special" value="<?= (int) ($cc['SPECIAL'] ?? 0) ?>" min="0"></div>
        <div class="field"><label>Average spend (KSh)</label><input type="number" name="avg_spend" value="<?= e($bh['avgSpend'] ?? 55) ?>" min="0"></div>
        <div class="field"><label>Bought-today IDs</label><input type="text" name="bought_today" value="<?= e(implode(',', $bh['boughtTodayIds'] ?? [])) ?>" placeholder="data_2, spec_1"></div>
      </div>
      <div class="mt"><button class="btn" type="submit"><?= icon('billboards', 18) ?> Run scoring</button></div>
    </form>
  </div>
  <div class="card">
    <div class="card__head"><h3>Ranked candidates</h3></div>
    <?php if ($result === null): ?>
      <p class="small muted">Run the scoring to see ranked upgrade candidates and the top pool used with controlled randomness.</p>
    <?php elseif (empty($result['ranked'])): ?>
      <div class="empty"><?= icon('offers', 28) ?><h3>No candidates</h3><p>All active offers were excluded (e.g. bought today).</p></div>
    <?php else: ?>
      <p class="small muted mb">Top <?= (int) $result['topPool'] ?> form the candidate pool; the app picks from them with weighted randomness so similar users don't always see the same advert.</p>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>#</th><th>Offer</th><th>Score</th><th>Freq</th><th>Step-up</th><th>Validity</th><th>Ratio</th></tr></thead>
        <tbody>
        <?php foreach ($result['ranked'] as $i => $row): $s = $row['score']; $o = $row['offer']; $inPool = $i < (int) $result['topPool']; ?>
          <tr style="<?= $inPool ? 'background:var(--green-soft)' : '' ?>">
            <td><?= $i + 1 ?><?= $inPool ? ' ' . icon('check', 12) : '' ?></td>
            <td><b><?= e($o['name']) ?></b> <span class="tag <?= strtolower($o['category']) ?>"><?= e($o['category']) ?></span><div class="muted small">KSh <?= (int) $o['price'] ?></div></td>
            <td><b><?= e($s['total']) ?></b></td>
            <td class="small muted"><?= e($s['factors']['frequency']) ?></td>
            <td class="small muted"><?= e($s['factors']['valueStepUp']) ?></td>
            <td class="small muted"><?= e($s['factors']['validity']) ?></td>
            <td class="small muted"><?= e($s['ratio']) ?>×</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <p class="small muted mt"><?= icon('info', 13) ?> Score = frequency×w + value-step-up×w + validity×w, using the bounded weights from App configuration. Extreme jumps (ratio &gt; max step-up) score 0.</p>
    <?php endif; ?>
  </div>
</div>
