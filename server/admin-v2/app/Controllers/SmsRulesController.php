<?php
/**
 * Dynamic SMS rule management — the module that replaced v1 "Message templates".
 *
 * A rule says: when a message from this sender looks like this, it means this event.
 * The Android app downloads the enabled rules with the published configuration and
 * evaluates them locally, so a Safaricom wording change is an edit here, never an app
 * release. Event types and pattern types are read from their catalogue tables, so adding
 * a new event is a data change too.
 *
 * Guardrails kept from v1:
 *  - patterns are validated (regex goes through the ReDoS guard in TemplateMatcher),
 *  - a rule can only be ENABLED when its positive samples all match and none of its
 *    negative samples match,
 *  - every create/update/enable/disable/duplicate/delete writes a revision snapshot and
 *    an audit entry.
 *
 * A match only means "a recognised message arrived on this device". It is never proof
 * that a bundle reached the recipient.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;
use App\Services\SmsRuleEngine;

final class SmsRulesController extends Controller
{
    private const CATEGORIES = ['DATA', 'SMS', 'MINUTES', 'SPECIAL'];
    private const BUNDLE_TYPES = ['Hourly', 'Daily', 'Weekly', 'Monthly'];

    private function table(): string
    {
        return Database::table('sms_rules');
    }

    /* ------------------------------------------------------------------------ list */

    public function index(Request $request): void
    {
        $this->guard('templates.manage');

        $filters = [
            'q'     => trim((string) $request->get('q', '')),
            'event' => trim((string) $request->get('event', '')),
        ];

        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE 1 = 1';
        $params = [];
        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $sql .= ' AND (name LIKE ? OR rule_key LIKE ? OR pattern LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($filters['event'] !== '') {
            $sql .= ' AND event_type = ?';
            $params[] = $filters['event'];
        }
        // Grouped by event, strongest rule first — the order the app applies them in.
        $sql .= ' ORDER BY event_type ASC, priority DESC, rule_key ASC';

        $this->view('sms_rules/index', [
            'activeNav'  => 'templates',
            'pageTitle'  => 'SMS rules',
            'rules'      => Database::fetchAll($sql, $params),
            'eventTypes' => SmsRuleEngine::eventTypes(),
            'patternTypes' => SmsRuleEngine::patternTypes(),
            'filters'    => $filters,
        ]);
    }

    /** Old bookmarks for the v1 templates page land on the new module. */
    public function legacyRedirect(Request $request): void
    {
        $this->guard('templates.manage');
        Flash::info('Message templates are now SMS rules — everything moved here.');
        $this->redirect('/sms-rules');
    }

    /* ------------------------------------------------------------------------ form */

    public function create(Request $request): void
    {
        $this->guard('templates.manage');
        $this->renderForm(null, true);
    }

    public function edit(Request $request, string $id): void
    {
        $this->guard('templates.manage');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) {
            Flash::error('Rule not found.');
            $this->redirect('/sms-rules');
        }
        $this->renderForm($row, false);
    }

    private function renderForm(?array $row, bool $isNew): void
    {
        $this->view('sms_rules/form', [
            'activeNav'    => 'templates',
            'pageTitle'    => $isNew ? 'Add SMS rule' : 'Edit SMS rule',
            'rule'         => $row,
            'isNew'        => $isNew,
            'eventTypes'   => SmsRuleEngine::eventTypes(),
            'patternTypes' => SmsRuleEngine::patternTypes(),
            'senders'      => $this->senders(),
            'categories'   => self::CATEGORIES,
            'bundleTypes'  => self::BUNDLE_TYPES,
        ]);
    }

    /* ------------------------------------------------------------------------ save */

    public function save(Request $request): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');

        $isNew = $request->post('is_new') === '1';
        $id = (int) $request->post('id', 0);
        $eventTypes = SmsRuleEngine::eventTypes();
        $patternTypes = SmsRuleEngine::patternTypes();

        $key = strtolower(trim((string) $request->post('rule_key', '')));
        $pattern = (string) $request->post('pattern', '');
        $patternType = trim((string) $request->post('pattern_type', 'regex'));
        $eventType = trim((string) $request->post('event_type', ''));
        $caseSensitive = $request->post('case_sensitive') ? 1 : 0;
        $wantEnabled = (bool) $request->post('enabled');
        $positives = $this->lines((string) $request->post('positive_samples', ''));
        $negatives = $this->lines((string) $request->post('negative_samples', ''));

        // Raw values are validated as typed, then normalised into the row to store.
        $raw = [
            'rule_key'    => $key,
            'name'        => trim((string) $request->post('name', '')),
            'description' => trim((string) $request->post('description', '')),
            'sender_id'   => trim((string) $request->post('sender_id', '')),
            'category'    => trim((string) $request->post('category', '')),
            'bundle_type' => trim((string) $request->post('bundle_type', '')),
            'priority'    => trim((string) $request->post('priority', '100')),
            'correlation_window_min' => trim((string) $request->post('correlation_window_min', '30')),
        ];
        $v = Validator::make($raw);

        $input = [
            'rule_key'      => $key,
            'name'          => $raw['name'],
            'description'   => $raw['description'],
            'sender_id'     => $raw['sender_id'],
            'pattern_type'  => $patternType,
            'pattern'       => $pattern,
            'case_sensitive'=> $caseSensitive,
            'event_type'    => $eventType,
            'secondary_events' => $this->secondaryEvents($request, $eventType, $eventTypes),
            'category'      => $raw['category'],
            'bundle_type'   => $raw['bundle_type'],
            'captures_json' => null,
            'correlation_window_min' => max(0, (int) $raw['correlation_window_min']),
            'priority'      => (int) $raw['priority'],
        ];

        $v->validate([
            // maxlen, not max: `max` compares numerically for a numeric-looking string, so
            // an all-digits key like "99999" would be rejected as "at most 64".
            'rule_key'    => 'required|slug|maxlen:64',
            'name'        => 'required|maxlen:120',
            'description' => 'maxlen:255',
            'sender_id'   => 'maxlen:40',
            'category'    => 'in:' . implode(',', self::CATEGORIES),
            'bundle_type' => 'in:' . implode(',', self::BUNDLE_TYPES),
            'priority'    => 'int',
            'correlation_window_min' => 'int',
        ]);

        // Catalogue membership — never a PHP list, always the table.
        if ($eventType === '' || !isset($eventTypes[$eventType])) {
            $v->add('event_type', 'Choose an event type from the list.');
        }
        if ($patternType === '' || !isset($patternTypes[$patternType])) {
            $v->add('pattern_type', 'Choose a pattern type from the list.');
        } else {
            $patternCheck = SmsRuleEngine::validatePattern($patternType, $pattern, (bool) $caseSensitive);
            if (!$patternCheck['ok']) {
                $v->add('pattern', (string) $patternCheck['error']);
            }
        }
        if (mb_strlen($input['secondary_events']) > 255) {
            $v->add('secondary_events', 'Too many extra events — remove a few.');
        }

        $input['captures_json'] = $this->parseCaptureMap((string) $request->post('captures', ''), $v);

        if ($isNew && Database::fetch('SELECT id FROM ' . $this->table() . ' WHERE rule_key = ?', [$key])) {
            $v->add('rule_key', 'That rule key already exists.');
        }
        if (!$isNew) {
            $existing = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
            if (!$existing) {
                $v->add('rule_key', 'Rule not found.');
            } else {
                // The app matches rules by key, so the key never changes after creation.
                $input['rule_key'] = $key = (string) $existing['rule_key'];
            }
        }

        if ($v->fails()) {
            Flash::error('Please correct the highlighted fields.');
            Flash::keepOld(array_merge($input, [
                'captures'         => (string) $request->post('captures', ''),
                'positive_samples' => implode("\n", $positives),
                'negative_samples' => implode("\n", $negatives),
                'enabled'          => $wantEnabled ? 1 : 0,
                '_errors'          => $v->firstErrors(),
            ]));
            $this->redirect($isNew ? '/sms-rules/new' : '/sms-rules/' . $id . '/edit');
        }

        // Enabling is the only gate that does not block the save: an operator's work is
        // kept, the rule is simply stored disabled with the exact reason flashed back.
        $enabled = 0;
        $enableProblems = [];
        if ($wantEnabled) {
            $enableProblems = $this->sampleProblems($input, $positives, $negatives);
            $enabled = $enableProblems === [] ? 1 : 0;
        }

        $actor = Auth::user()['name'] ?? 'system';
        $before = $isNew ? null : Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);

        if ($isNew) {
            Database::run(
                'INSERT INTO ' . $this->table() . '
                    (rule_key, name, description, sender_id, pattern_type, pattern, case_sensitive,
                     event_type, secondary_events, category, bundle_type, captures_json,
                     correlation_window_min, priority, enabled, positive_samples, negative_samples,
                     row_version, created_at, updated_at, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?)',
                [
                    $input['rule_key'], $input['name'], $input['description'], $input['sender_id'],
                    $input['pattern_type'], $input['pattern'], $input['case_sensitive'],
                    $input['event_type'], $input['secondary_events'], $input['category'], $input['bundle_type'],
                    $input['captures_json'], $input['correlation_window_min'], $input['priority'], $enabled,
                    json_encode($positives), json_encode($negatives), $actor, $actor,
                ]
            );
            $id = (int) Database::pdo()->lastInsertId();
        } else {
            Database::run(
                'UPDATE ' . $this->table() . ' SET
                    name = ?, description = ?, sender_id = ?, pattern_type = ?, pattern = ?, case_sensitive = ?,
                    event_type = ?, secondary_events = ?, category = ?, bundle_type = ?, captures_json = ?,
                    correlation_window_min = ?, priority = ?, enabled = ?, positive_samples = ?, negative_samples = ?,
                    row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ?
                  WHERE id = ?',
                [
                    $input['name'], $input['description'], $input['sender_id'], $input['pattern_type'],
                    $input['pattern'], $input['case_sensitive'], $input['event_type'], $input['secondary_events'],
                    $input['category'], $input['bundle_type'], $input['captures_json'],
                    $input['correlation_window_min'], $input['priority'], $enabled,
                    json_encode($positives), json_encode($negatives), $actor, $id,
                ]
            );
        }

        $after = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        $this->writeRevision($input['rule_key'], $isNew ? 'create' : 'update');
        Audit::log([
            'action' => $isNew ? 'sms_rule.create' : 'sms_rule.update',
            'entity_type' => 'sms_rule', 'entity_id' => $input['rule_key'],
            'before' => $before, 'after' => $after,
        ]);

        Flash::success('Rule saved. Publish to apply.');
        if ($enableProblems !== []) {
            Flash::warning('Saved but left disabled. ' . implode(' ', $enableProblems));
        }
        $this->redirect('/sms-rules');
    }

    /* --------------------------------------------------------------- row operations */

    public function toggle(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');

        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) {
            Flash::error('Rule not found.');
            $this->redirect('/sms-rules');
        }
        $turningOn = (int) $row['enabled'] !== 1;

        if ($turningOn) {
            $problems = $this->sampleProblems(
                $row,
                json_decode((string) $row['positive_samples'], true) ?: [],
                json_decode((string) $row['negative_samples'], true) ?: []
            );
            if ($problems !== []) {
                Flash::error('Cannot enable this rule. ' . implode(' ', $problems));
                $this->redirect('/sms-rules/' . (int) $id . '/edit');
            }
        }

        Database::run(
            'UPDATE ' . $this->table() . ' SET enabled = ?, row_version = row_version + 1,
                updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ?',
            [$turningOn ? 1 : 0, Auth::user()['name'] ?? 'system', (int) $id]
        );
        $after = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        $this->writeRevision((string) $row['rule_key'], $turningOn ? 'enable' : 'disable');
        Audit::log([
            'action' => $turningOn ? 'sms_rule.enable' : 'sms_rule.disable',
            'entity_type' => 'sms_rule', 'entity_id' => (string) $row['rule_key'],
            'before' => $row, 'after' => $after,
        ]);
        Flash::success($turningOn ? 'Rule enabled. Publish to apply.' : 'Rule disabled. Publish to apply.');
        $this->redirect('/sms-rules');
    }

    public function duplicate(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');

        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) {
            Flash::error('Rule not found.');
            $this->redirect('/sms-rules');
        }

        $newKey = $this->uniqueCopyKey((string) $row['rule_key']);
        $actor = Auth::user()['name'] ?? 'system';
        Database::run(
            'INSERT INTO ' . $this->table() . '
                (rule_key, name, description, sender_id, pattern_type, pattern, case_sensitive,
                 event_type, secondary_events, category, bundle_type, captures_json,
                 correlation_window_min, priority, enabled, positive_samples, negative_samples,
                 row_version, created_at, updated_at, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?)',
            [
                $newKey, mb_substr($row['name'] . ' (copy)', 0, 120), $row['description'], $row['sender_id'],
                $row['pattern_type'], $row['pattern'], $row['case_sensitive'], $row['event_type'],
                $row['secondary_events'], $row['category'], $row['bundle_type'], $row['captures_json'],
                $row['correlation_window_min'], $row['priority'],
                $row['positive_samples'], $row['negative_samples'], $actor, $actor,
            ]
        );
        $after = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE rule_key = ?', [$newKey]);
        $this->writeRevision($newKey, 'duplicate');
        Audit::log([
            'action' => 'sms_rule.duplicate', 'entity_type' => 'sms_rule', 'entity_id' => $newKey,
            'before' => $row, 'after' => $after,
        ]);
        Flash::success('Copied to “' . $newKey . '” (disabled). Edit and test it, then enable.');
        $this->redirect('/sms-rules');
    }

    public function delete(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');

        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) {
            Flash::error('Rule not found.');
            $this->redirect('/sms-rules');
        }
        // Any rule may be deleted, but deleting one the app is currently using has to be
        // a deliberate act, so an enabled rule needs an explicit confirmation.
        if ((int) $row['enabled'] === 1 && (string) $request->post('confirm', '') !== '1') {
            Flash::error('This rule is enabled and in use. Confirm the deletion to continue.');
            $this->redirect('/sms-rules');
        }

        $this->writeRevision((string) $row['rule_key'], 'delete');
        Database::run('DELETE FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        Audit::log([
            'action' => 'sms_rule.delete', 'entity_type' => 'sms_rule', 'entity_id' => (string) $row['rule_key'],
            'before' => $row, 'after' => null,
        ]);
        Flash::success('Rule deleted. Publish to apply.');
        $this->redirect('/sms-rules');
    }

    /* ---------------------------------------------------------------- rule tester */

    public function tester(Request $request): void
    {
        $this->guard('templates.manage');
        // ?rule=<rule_key> lets the list page send an operator straight here with one
        // rule preselected. Nothing is evaluated until they paste a message.
        $this->renderTester(
            ['sender' => '', 'body' => '', 'rule_key' => trim((string) $request->get('rule', ''))],
            null
        );
    }

    /**
     * Run one pasted message through the rules and re-render the same screen with the
     * outcome. Deliberately not a redirect: the operator keeps the message they pasted.
     * Nothing typed here is ever stored.
     */
    public function runTester(Request $request): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');

        $submitted = [
            'sender'   => trim((string) $request->post('sender', '')),
            'body'     => (string) $request->post('body', ''),
            'rule_key' => trim((string) $request->post('rule_key', '')),
        ];

        if (trim($submitted['body']) === '') {
            Flash::warning('Paste a message to test.');
            $this->renderTester($submitted, null);
            return;
        }

        $rules = Database::fetchAll(
            'SELECT * FROM ' . $this->table() . ' ORDER BY priority DESC, rule_key ASC'
        );
        if ($submitted['rule_key'] !== '') {
            $rules = array_values(array_filter(
                $rules,
                static fn(array $r): bool => (string) $r['rule_key'] === $submitted['rule_key']
            ));
        }

        $this->renderTester($submitted, SmsRuleEngine::evaluate($rules, $submitted['sender'], $submitted['body']));
    }

    private function renderTester(array $submitted, ?array $result): void
    {
        $this->view('sms_rules/tester', [
            'activeNav'  => 'templates',
            'pageTitle'  => 'Test an SMS rule',
            'submitted'  => $submitted,
            'result'     => $result,
            'senders'    => $this->senders(),
            'eventTypes' => SmsRuleEngine::eventTypes(),
            'allRules'   => Database::fetchAll(
                'SELECT rule_key, name, enabled FROM ' . $this->table() . ' ORDER BY name ASC, rule_key ASC'
            ),
        ]);
    }

    /* ---------------------------------------------------------------------- helpers */

    /** Known Safaricom sender IDs, for the dropdowns. */
    private function senders(): array
    {
        return Database::fetchAll(
            'SELECT sender_id, note FROM ' . Database::table('message_sender_ids') . ' ORDER BY sender_id ASC'
        );
    }

    /**
     * Why this rule may not be enabled yet. An empty array means it is safe to enable.
     * Samples are tested against the rule's own sender, so the check is about the
     * pattern, not about typing the sender twice.
     *
     * @return string[]
     */
    private function sampleProblems(array $rule, array $positives, array $negatives): array
    {
        $problems = [];
        if (!$positives || !$negatives) {
            return ['Add at least one positive and one negative sample before enabling.'];
        }
        $sender = (string) ($rule['sender_id'] ?? '');
        foreach ($positives as $sample) {
            $res = SmsRuleEngine::match($rule, $sender, (string) $sample);
            if (!$res['matched']) {
                $problems[] = 'A positive sample does not match: “' . mb_strimwidth((string) $sample, 0, 40, '…') . '”.';
                break;
            }
        }
        foreach ($negatives as $sample) {
            $res = SmsRuleEngine::match($rule, $sender, (string) $sample);
            if ($res['matched']) {
                $problems[] = 'A negative sample wrongly matches: “' . mb_strimwidth((string) $sample, 0, 40, '…') . '”.';
                break;
            }
        }
        return $problems;
    }

    /** Selected extra events, minus the primary one and anything not in the catalogue. */
    private function secondaryEvents(Request $request, string $primary, array $eventTypes): string
    {
        $raw = $request->post('secondary_events', []);
        if (!is_array($raw)) {
            $raw = [];
        }
        $out = [];
        foreach ($raw as $event) {
            $event = trim((string) $event);
            if ($event === '' || $event === $primary || !isset($eventTypes[$event]) || in_array($event, $out, true)) {
                continue;
            }
            $out[] = $event;
        }
        return implode(',', $out);
    }

    /**
     * Turn the capture map textarea ("amount=1" per line) into JSON for the app.
     * A non-integer group number is rejected rather than silently dropped.
     */
    private function parseCaptureMap(string $raw, Validator $v): ?string
    {
        $map = [];
        foreach ($this->lines($raw) as $line) {
            if (strpos($line, '=') === false) {
                $v->add('captures', 'Write one “name=group number” per line, for example amount=1.');
                continue;
            }
            [$name, $group] = array_map('trim', explode('=', $line, 2));
            if ($name === '') {
                $v->add('captures', 'Every capture needs a name, for example amount=1.');
                continue;
            }
            if ($group === '' || filter_var($group, FILTER_VALIDATE_INT) === false || (int) $group < 0) {
                $v->add('captures', 'The group number for “' . $name . '” must be a whole number, for example ' . $name . '=1.');
                continue;
            }
            $map[$name] = (int) $group;
        }
        return $map === [] ? null : json_encode($map);
    }

    /** rule_key + _copy, then _copy_2, _copy_3 … staying inside the 64-character column. */
    private function uniqueCopyKey(string $base): string
    {
        $stem = mb_substr($base, 0, 52);
        $key = $stem . '_copy';
        $i = 1;
        while (Database::fetch('SELECT id FROM ' . $this->table() . ' WHERE rule_key = ?', [$key])) {
            $key = $stem . '_copy_' . (++$i);
        }
        return $key;
    }

    /** Split a textarea into trimmed, non-empty lines. */
    private function lines(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
    }

    private function writeRevision(string $key, string $action): void
    {
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE rule_key = ?', [$key]);
        Database::run(
            'INSERT INTO ' . Database::table('sms_rule_revisions') . '
                (rule_key, snapshot_json, action, actor_name, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$key, json_encode($row), $action, Auth::user()['name'] ?? 'system']
        );
    }
}
