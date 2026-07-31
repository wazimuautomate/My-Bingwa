<?php
/**
 * Add / edit one SMS rule. Written for a non-developer: every field says in plain words
 * what it does, and the pattern-type help block explains each way of recognising a
 * message. Nothing here changes the app until the configuration is published.
 */
use App\Core\Session;

$old = Session::get('_old', []);
Session::forget('_old'); // consume repopulation data exactly once
$errs = $old['_errors'] ?? [];
$r = $rule ?? [];

$val = function (string $key, $default = '') use ($old, $r) {
    if (array_key_exists($key, $old)) { return $old[$key]; }
    return $r[$key] ?? $default;
};
$err = fn(string $k) => isset($errs[$k]) ? '<span class="err">' . e($errs[$k]) . '</span>' : '';
$hasErr = fn(string $k) => isset($errs[$k]) ? 'has-error' : '';

// Samples and the capture map are textareas, so rebuild their text form.
$sampleText = function (string $key) use ($old, $r): string {
    if (array_key_exists($key, $old)) { return (string) $old[$key]; }
    $list = json_decode((string) ($r[$key] ?? ''), true);
    return is_array($list) ? implode("\n", $list) : '';
};
$captureText = (function () use ($old, $r): string {
    if (array_key_exists('captures', $old)) { return (string) $old['captures']; }
    $map = json_decode((string) ($r['captures_json'] ?? ''), true);
    if (!is_array($map) || $map === []) { return ''; }
    $lines = [];
    foreach ($map as $name => $group) { $lines[] = $name . '=' . (int) $group; }
    return implode("\n", $lines);
})();

// Secondary events arrive as a checkbox array from the form and as a comma string from
// the database / repopulation, so normalise both into one list.
$selectedSecondary = (function () use ($old, $r): array {
    $raw = array_key_exists('secondary_events', $old) ? $old['secondary_events'] : ($r['secondary_events'] ?? '');
    if (is_array($raw)) { return array_map('strval', $raw); }
    return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
})();

// The sender list is the known sender IDs plus whatever this rule already uses.
$currentSender = (string) $val('sender_id', '');
$senderOptions = [];
foreach ($senders as $s) { $senderOptions[(string) $s['sender_id']] = (string) ($s['note'] ?? ''); }
if ($currentSender !== '' && !array_key_exists($currentSender, $senderOptions)) {
    $senderOptions[$currentSender] = 'saved on this rule';
}
$currentType = (string) $val('pattern_type', 'regex');
$currentEvent = (string) $val('event_type', '');
?>
<style>
  /* Page-scoped only. The help list is specific to this screen. */
  .type-help { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; }
  .type-help li { font-size: 12.5px; line-height: 1.5; }
  .type-help b { font-weight: 600; }
  .event-picker { display: flex; flex-wrap: wrap; gap: 8px 16px; }
  .event-picker label { font-size: 13px; }
</style>

<div class="page-head">
  <div>
    <h1><?= $isNew ? 'Add SMS rule' : 'Edit SMS rule' ?></h1>
    <div class="sub"><?= $isNew
      ? 'Describe one kind of Safaricom message and what it means.'
      : 'Editing ' . e((string) ($r['rule_key'] ?? '')) ?></div>
  </div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(url('/sms-rules')) ?>">Cancel</a></div>
</div>

<?php if (!$eventTypes): ?>
  <div class="alert warning mb">
    <div><?= icon('warning', 16) ?> No event types are set up yet, so this rule cannot be saved. Add rows to the SMS event type list first.</div>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/sms-rules/save')) ?>" data-once>
  <?= App\Core\Csrf::field() ?>
  <input type="hidden" name="is_new" value="<?= $isNew ? '1' : '0' ?>">
  <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>"><?php endif; ?>

  <div class="grid two">
    <!-- ------------------------------------------------- what the message looks like -->
    <div class="stack">
      <div class="card">
        <div class="card__head"><?= icon('templates', 18) ?><h3>What this rule is</h3></div>
        <div class="form-grid">
          <div class="field full <?= $hasErr('rule_key') ?>">
            <label>Rule key</label>
            <input type="text" class="mono" name="rule_key" value="<?= e($val('rule_key')) ?>"
                   placeholder="saf_data_bingwa_sokoni" <?= $isNew ? '' : 'readonly' ?> required>
            <span class="hint">A short name with no spaces, using lowercase letters, numbers, hyphen or underscore.
              The app identifies the rule by this, so it never changes once saved.</span>
            <?= $err('rule_key') ?>
          </div>
          <div class="field full <?= $hasErr('name') ?>">
            <label>Name</label>
            <input type="text" name="name" value="<?= e($val('name')) ?>" placeholder="Bingwa Sokoni data received" required>
            <span class="hint">What you will call this rule in these screens. Write it for a person, not a computer.</span>
            <?= $err('name') ?>
          </div>
          <div class="field full <?= $hasErr('description') ?>">
            <label>Description <span class="muted small">(optional)</span></label>
            <input type="text" name="description" value="<?= e($val('description')) ?>"
                   placeholder="You have received Sh20=250MB 24hr from Bingwa Sokoni.">
            <span class="hint">Paste a real example of the message so the next person knows what this rule is for.</span>
            <?= $err('description') ?>
          </div>
          <div class="field full <?= $hasErr('sender_id') ?>">
            <label>Sender ID</label>
            <select name="sender_id">
              <option value="" <?= $currentSender === '' ? 'selected' : '' ?>>Any sender</option>
              <?php foreach ($senderOptions as $sender => $note): ?>
                <option value="<?= e($sender) ?>" <?= $currentSender === $sender ? 'selected' : '' ?>>
                  <?= e($sender) ?><?= $note !== '' ? ' — ' . e($note) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="hint">Who the message must come from, for example Safaricom or SAF_Balance.
              Choose “Any sender” only when the wording alone is enough to be sure.</span>
            <?= $err('sender_id') ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><?= icon('search', 18) ?><h3>How the message is recognised</h3></div>
        <div class="form-grid">
          <div class="field <?= $hasErr('pattern_type') ?>">
            <label>Pattern type</label>
            <select name="pattern_type">
              <?php foreach ($patternTypes as $key => $row): ?>
                <option value="<?= e($key) ?>" <?= $currentType === $key ? 'selected' : '' ?>><?= e($row['label'] ?: $key) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="hint">How the text below is compared with the message. See the guide underneath.</span>
            <?= $err('pattern_type') ?>
          </div>
          <div class="field">
            <label>Letter case</label>
            <label class="checkbox mt">
              <input type="checkbox" name="case_sensitive" <?= $val('case_sensitive', 0) ? 'checked' : '' ?>>
              Capital letters must match exactly
            </label>
            <span class="hint">Leave this off unless capitals really matter — Safaricom changes them often.</span>
          </div>
          <div class="field full <?= $hasErr('pattern') ?>">
            <label>Pattern</label>
            <textarea class="mono" name="pattern" style="min-height:90px" required
                      placeholder="received\s+Sh(\d+)\s*=\s*([\d.]+\s*(?:MB|GB))"><?= e($val('pattern')) ?></textarea>
            <span class="hint">The text (or, for a regular expression, the pattern) that identifies this message.
              For keywords, write one per line or separate them with commas.</span>
            <?= $err('pattern') ?>
          </div>
          <div class="field full <?= $hasErr('captures') ?>">
            <label>Values to pull out <span class="muted small">(optional, regular expressions only)</span></label>
            <textarea class="mono" name="captures" style="min-height:70px" placeholder="amount=1&#10;allowance=2"><?= e($captureText) ?></textarea>
            <span class="hint">One per line as <span class="mono">name=group number</span>. Group 1 is the first
              bracketed part of the pattern, group 2 the second, and so on. Leave empty if you do not need any value.</span>
            <?= $err('captures') ?>
          </div>
        </div>
        <div class="alert info mt">
          <div>
            <?= icon('info', 16) ?>
            <b>Pattern types</b>
            <ul class="type-help mt">
              <?php foreach ($patternTypes as $key => $row): ?>
                <li><b><?= e($row['label'] ?: $key) ?></b> — <?= e($row['description'] ?: 'No description.') ?></li>
              <?php endforeach; ?>
              <?php if (!$patternTypes): ?><li>No pattern types are configured.</li><?php endif; ?>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- ---------------------------------------------------------- what the message means -->
    <div class="stack">
      <div class="card">
        <div class="card__head"><?= icon('flag', 18) ?><h3>What it means</h3></div>
        <div class="form-grid">
          <div class="field full <?= $hasErr('event_type') ?>">
            <label>Event</label>
            <select name="event_type" required>
              <option value="">Choose an event…</option>
              <?php foreach ($eventTypes as $key => $row): ?>
                <option value="<?= e($key) ?>" <?= $currentEvent === $key ? 'selected' : '' ?>><?= e($row['label'] ?: $key) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="hint">What the app concludes when this message arrives. This list is managed as data —
              new events are added to the event list, not by changing the app.</span>
            <?= $err('event_type') ?>
          </div>
          <div class="field full <?= $hasErr('secondary_events') ?>">
            <label>Also raises <span class="muted small">(optional)</span></label>
            <div class="event-picker">
              <?php foreach ($eventTypes as $key => $row): ?>
                <label class="checkbox">
                  <input type="checkbox" name="secondary_events[]" value="<?= e($key) ?>"
                         <?= in_array($key, $selectedSecondary, true) ? 'checked' : '' ?>>
                  <?= e($row['label'] ?: $key) ?>
                </label>
              <?php endforeach; ?>
              <?php if (!$eventTypes): ?><span class="muted small">No events configured.</span><?php endif; ?>
            </div>
            <span class="hint">Tick extra events for a message that means more than one thing, such as
              minutes and SMS arriving together. The main event above is added automatically.</span>
            <?= $err('secondary_events') ?>
          </div>
          <div class="field <?= $hasErr('category') ?>">
            <label>Category hint <span class="muted small">(optional)</span></label>
            <select name="category">
              <option value="">None</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>" <?= (string) $val('category') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="hint">Which kind of bundle this message is about, when you know it.</span>
            <?= $err('category') ?>
          </div>
          <div class="field <?= $hasErr('bundle_type') ?>">
            <label>Bundle length hint <span class="muted small">(optional)</span></label>
            <select name="bundle_type">
              <option value="">None</option>
              <?php foreach ($bundleTypes as $b): ?>
                <option value="<?= e($b) ?>" <?= (string) $val('bundle_type') === $b ? 'selected' : '' ?>><?= e($b) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="hint">How long the bundle lasts, when the message says so.</span>
            <?= $err('bundle_type') ?>
          </div>
          <div class="field <?= $hasErr('priority') ?>">
            <label>Priority</label>
            <input type="number" name="priority" value="<?= e($val('priority', 100)) ?>" min="0" max="1000">
            <span class="hint">When several rules match the same message, the <b>highest</b> number wins.
              Give a very specific rule a higher number than a general one.</span>
            <?= $err('priority') ?>
          </div>
          <div class="field <?= $hasErr('correlation_window_min') ?>">
            <label>Link to a purchase within (minutes)</label>
            <input type="number" name="correlation_window_min" value="<?= e($val('correlation_window_min', 30)) ?>" min="0" max="1440">
            <span class="hint">How long after a purchase this message may still be about it. Use 0 for
              balance warnings, which are never about a specific purchase.</span>
            <?= $err('correlation_window_min') ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><?= icon('flask', 18) ?><h3>Samples and switching it on</h3></div>
        <div class="field mb">
          <label>Messages that MUST match</label>
          <textarea name="positive_samples" style="min-height:100px"
                    placeholder="You have received Sh20=250MB 24hr from Bingwa Sokoni."><?= e($sampleText('positive_samples')) ?></textarea>
          <span class="hint">One real message per line. Every one of them has to match before the rule can be switched on.</span>
        </div>
        <div class="field mb">
          <label>Messages that must NOT match</label>
          <textarea name="negative_samples" style="min-height:100px"
                    placeholder="You have received 20 SMS Daily SMS Bundle."><?= e($sampleText('negative_samples')) ?></textarea>
          <span class="hint">One per line. These prove the rule is not too greedy — none of them may match.</span>
        </div>
        <label class="switch">
          <input type="checkbox" name="enabled" <?= $val('enabled', 0) ? 'checked' : '' ?>>
          <span class="track"></span><span>Switch this rule on</span>
        </label>
        <div class="alert info mt small">
          <div><?= icon('info', 14) ?> The rule is only switched on when the samples pass. If any sample fails,
            your work is still saved — the rule is simply left off and the reason is shown.</div>
        </div>
        <div class="alert warning mt small">
          <div><?= icon('warning', 14) ?> Recognising a message means one arrived on that phone. It is never proof
            that a bundle reached the customer.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt">
    <button class="btn" type="submit"><?= icon('check', 18) ?> Save rule</button>
    <span class="muted small">Changes reach the app only after you publish.</span>
  </div>
</form>
