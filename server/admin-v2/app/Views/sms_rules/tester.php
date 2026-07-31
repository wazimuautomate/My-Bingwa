<?php
/**
 * Rule tester. Paste one message, see exactly what the app would conclude: which rule
 * wins, which events it raises, which values it pulls out, and why every other rule did
 * or did not match. The pasted message is evaluated in memory and never stored.
 */
$submitted = $submitted ?? ['sender' => '', 'body' => '', 'rule_key' => ''];
$result = $result ?? null;

$eventLabel = function (string $key) use ($eventTypes): string {
    return (string) ($eventTypes[$key]['label'] ?? ($key !== '' ? $key : '—'));
};

// The winning candidate carries the sentence we want to show for the win.
$winner = $result['winner'] ?? null;
$winnerReason = '';
if ($winner !== null) {
    foreach ($result['candidates'] as $candidate) {
        if ((string) ($candidate['rule']['rule_key'] ?? '') === (string) ($winner['rule_key'] ?? '')) {
            $winnerReason = (string) $candidate['result']['reason'];
            break;
        }
    }
}
?>
<style>
  /* Page-scoped only: the tester's result blocks are unique to this screen. */
  .test-events { display: flex; flex-wrap: wrap; gap: 8px; }
  .test-reason { font-size: 13px; line-height: 1.55; }
  .test-captures { width: 100%; border-collapse: collapse; }
  .test-captures td { padding: 6px 0; border-bottom: 1px solid var(--divider); font-size: 13px; }
  .test-captures td:last-child { text-align: right; }
</style>

<div class="page-head">
  <div>
    <h1>Test an SMS rule</h1>
    <div class="sub">Check what the app would understand before you switch a rule on. Nothing you paste here is saved.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= e(url('/sms-rules')) ?>">Back to rules</a>
  </div>
</div>

<div class="grid two">
  <div class="card">
    <div class="card__head"><?= icon('flask', 18) ?><h3>The message</h3></div>
    <form method="post" action="<?= e(url('/sms-rules/tester')) ?>">
      <?= App\Core\Csrf::field() ?>
      <div class="form-grid">
        <div class="field">
          <label>Sender ID</label>
          <select name="sender">
            <option value="" <?= $submitted['sender'] === '' ? 'selected' : '' ?>>Any sender</option>
            <?php foreach ($senders as $s): ?>
              <option value="<?= e($s['sender_id']) ?>" <?= $submitted['sender'] === (string) $s['sender_id'] ? 'selected' : '' ?>>
                <?= e($s['sender_id']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="hint">Who the message came from. “Any sender” means no sender ID at all, so rules that
            require one will correctly say so.</span>
        </div>
        <div class="field">
          <label>Rule</label>
          <select name="rule_key">
            <option value="" <?= $submitted['rule_key'] === '' ? 'selected' : '' ?>>All rules</option>
            <?php foreach ($allRules as $r): ?>
              <option value="<?= e($r['rule_key']) ?>" <?= $submitted['rule_key'] === (string) $r['rule_key'] ? 'selected' : '' ?>>
                <?= e($r['name']) ?><?= (int) $r['enabled'] === 1 ? '' : ' (off)' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="hint">Leave on “All rules” to see which one wins, or pick one to check it on its own.</span>
        </div>
        <div class="field full">
          <label>Message</label>
          <textarea name="body" style="min-height:120px" required
                    placeholder="You have received Sh20=250MB 24hr from Bingwa Sokoni."><?= e($submitted['body']) ?></textarea>
          <span class="hint">Paste the message exactly as it arrived on the phone. Only the first 1000 characters are read.</span>
        </div>
      </div>
      <div class="mt"><button class="btn" type="submit"><?= icon('flask', 18) ?> Run test</button></div>
    </form>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card__head"><?= icon('check', 18) ?><h3>Result</h3></div>
      <?php if ($result === null): ?>
        <div class="empty">
          <?= icon('flask', 32) ?>
          <h3>Nothing tested yet</h3>
          <p>Paste a message and run the test.</p>
        </div>
      <?php elseif ($winner === null): ?>
        <div class="alert warning">
          <div><?= icon('warning', 16) ?> No enabled rule matched this message. The app would treat it as an
            ordinary message and do nothing.</div>
        </div>
      <?php else: ?>
        <div class="stack" style="gap:12px">
          <div class="between">
            <span class="muted small">Winning rule</span>
            <span><b><?= e($winner['name']) ?></b></span>
          </div>
          <div class="between">
            <span class="muted small">Rule key</span>
            <span class="mono small"><?= e($winner['rule_key']) ?></span>
          </div>
          <div class="between">
            <span class="muted small">Priority</span>
            <span><?= (int) ($winner['priority'] ?? 0) ?></span>
          </div>
          <div>
            <span class="muted small">Events raised</span>
            <div class="test-events mt">
              <?php foreach ($result['events'] as $event): ?>
                <span class="tag"><?= e($eventLabel($event)) ?></span>
              <?php endforeach; ?>
              <?php if (!$result['events']): ?><span class="muted small">None</span><?php endif; ?>
            </div>
          </div>
          <div>
            <span class="muted small">Values pulled out</span>
            <?php if ($result['captures']): ?>
              <table class="test-captures mt">
                <?php foreach ($result['captures'] as $name => $value): ?>
                  <tr>
                    <td class="mono"><?= e($name) ?></td>
                    <td><?= $value === null || $value === '' ? '<span class="muted small">not found</span>' : e($value) ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php else: ?>
              <div class="muted small mt">None — this rule does not extract any values.</div>
            <?php endif; ?>
          </div>
          <div>
            <span class="muted small">Why it matched</span>
            <div class="test-reason mt"><?= e($winnerReason) ?></div>
          </div>
        </div>
      <?php endif; ?>
      <div class="alert info mt small">
        <div><?= icon('info', 14) ?> A match only means the phone recognised a message. It is never proof that a
          bundle reached the customer.</div>
      </div>
    </div>
  </div>
</div>

<?php if ($result !== null): ?>
  <div class="card mt">
    <div class="card__head"><?= icon('filter', 18) ?><h3>Every rule that was checked</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Rule</th><th>Priority</th><th>State</th><th>Result</th><th>Reason</th></tr></thead>
        <tbody>
          <?php foreach ($result['candidates'] as $candidate): ?>
            <?php
              $rule = $candidate['rule'];
              $res = $candidate['result'];
              $enabled = (int) ($rule['enabled'] ?? 0) === 1;
              $isWinner = $winner !== null && (string) $rule['rule_key'] === (string) $winner['rule_key'];
            ?>
            <tr>
              <td>
                <?= e($rule['name']) ?><?= $isWinner ? ' <span class="tag">Winner</span>' : '' ?><br>
                <span class="muted small mono"><?= e($rule['rule_key']) ?></span>
              </td>
              <td class="text-right"><?= (int) ($rule['priority'] ?? 0) ?></td>
              <td><span class="status <?= $enabled ? 'active' : 'draft' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span></td>
              <td>
                <?php if ($res['matched'] && $enabled): ?>
                  <span class="status active">Matched</span>
                <?php elseif ($res['matched']): ?>
                  <span class="status draft">Matched, but off</span>
                <?php else: ?>
                  <span class="muted small">No match</span>
                <?php endif; ?>
              </td>
              <td class="small" style="white-space:normal"><?= e($res['reason']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$result['candidates']): ?>
            <tr><td colspan="5">
              <div class="empty"><?= icon('templates', 32) ?><h3>No rules to check</h3><p>Add a rule first.</p></div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
