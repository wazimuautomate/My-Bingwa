<?php
/**
 * Safaricom message-recognition templates. Patterns are validated for ReDoS safety;
 * activating a template requires positive AND negative samples that actually pass
 * (every positive matches, no negative matches, sender included). You can test one
 * sample message against a saved template. Every revision is retained.
 *
 * Delivery detection is only "a recognised message arrived on this device" — never
 * authoritative fulfilment proof (see the app-side copy rules).
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;
use App\Services\TemplateMatcher;

final class TemplatesController extends Controller
{
    private const PURPOSES = ['delivery' => 'Delivery', 'low_balance' => 'Low balance', 'very_low_balance' => 'Very low balance'];
    private const CATEGORIES = ['DATA', 'SMS', 'MINUTES', 'SPECIAL'];

    private function table(): string { return Database::table('message_templates'); }

    public function index(Request $request): void
    {
        $this->guard('templates.manage');
        $this->view('templates/index', [
            'activeNav' => 'templates', 'pageTitle' => 'Message templates',
            'templates' => Database::fetchAll('SELECT * FROM ' . $this->table() . ' ORDER BY status, purpose, match_priority, template_key'),
            'purposes' => self::PURPOSES,
        ]);
    }

    public function create(Request $request): void
    {
        $this->guard('templates.manage');
        $this->renderForm(null, true);
    }

    public function edit(Request $request, string $id): void
    {
        $this->guard('templates.manage');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { Flash::error('Template not found.'); $this->redirect('/message-templates'); }
        $this->renderForm($row, false);
    }

    private function renderForm(?array $row, bool $isNew): void
    {
        $this->view('templates/form', [
            'activeNav' => 'templates', 'pageTitle' => $isNew ? 'Add template' : 'Edit template',
            'tpl' => $row, 'isNew' => $isNew, 'purposes' => self::PURPOSES, 'categories' => self::CATEGORIES,
        ]);
    }

    public function save(Request $request): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');
        $isNew = $request->post('is_new') === '1';
        $id = (int) $request->post('id', 0);

        $key = strtolower(trim((string) $request->post('template_key', '')));
        $pattern = (string) $request->post('pattern', '');
        $caseSensitive = $request->post('case_sensitive') ? 1 : 0;
        $wantActive = (bool) $request->post('active');
        $positives = $this->lines((string) $request->post('positive_samples', ''));
        $negatives = $this->lines((string) $request->post('negative_samples', ''));

        $input = [
            'template_key' => $key,
            'label' => trim((string) $request->post('label', '')),
            'sender_id' => trim((string) $request->post('sender_id', '')),
            'purpose' => (string) $request->post('purpose', 'delivery'),
            'category' => (string) $request->post('category', 'DATA'),
            'pattern' => $pattern,
            'case_sensitive' => $caseSensitive,
            'match_priority' => (int) $request->post('match_priority', 5),
            'correlation_window_min' => (int) $request->post('correlation_window_min', 30),
        ];

        $v = Validator::make($input);
        $v->validate([
            'template_key' => 'required|slug|max:48',
            'label' => 'required|max:120',
            'purpose' => 'required|in:' . implode(',', array_keys(self::PURPOSES)),
            'category' => 'required|in:' . implode(',', self::CATEGORIES),
        ]);
        $patternCheck = TemplateMatcher::validatePattern($pattern, (bool) $caseSensitive);
        if (!$patternCheck['ok']) {
            $v->add('pattern', $patternCheck['error']);
        }
        if ($isNew && Database::fetch('SELECT id FROM ' . $this->table() . ' WHERE template_key = ?', [$key])) {
            $v->add('template_key', 'That template key already exists.');
        }

        // To ACTIVATE, require samples that actually pass (sender + body).
        $status = 'draft';
        if ($wantActive) {
            $tplForTest = ['sender_id' => $input['sender_id'], 'pattern' => $pattern, 'case_sensitive' => $caseSensitive];
            if (!$positives || !$negatives) {
                $v->add('active', 'Add at least one positive and one negative sample before activating.');
            } else {
                foreach ($positives as $sample) {
                    if (!TemplateMatcher::test($tplForTest, $input['sender_id'], $sample)['matched']) {
                        $v->add('active', 'A positive sample does not match: “' . mb_strimwidth($sample, 0, 40, '…') . '”.');
                        break;
                    }
                }
                foreach ($negatives as $sample) {
                    if (TemplateMatcher::test($tplForTest, $input['sender_id'], $sample)['matched']) {
                        $v->add('active', 'A negative sample wrongly matches: “' . mb_strimwidth($sample, 0, 40, '…') . '”.');
                        break;
                    }
                }
            }
            if (!$v->fails()) {
                $status = 'active';
            }
        }
        if ($v->fails()) {
            Flash::error('Fix: ' . implode(' ', array_values($v->firstErrors())));
            $this->redirect($isNew ? '/message-templates/new' : '/message-templates/' . $id . '/edit');
        }

        $actor = Auth::user()['name'] ?? 'system';
        if ($isNew) {
            Database::run(
                'INSERT INTO ' . $this->table() . '
                    (template_key, label, sender_id, purpose, category, pattern_type, pattern, case_sensitive,
                     match_priority, correlation_window_min, positive_samples, negative_samples, status,
                     row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, \'regex\', ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)',
                [
                    $key, $input['label'], $input['sender_id'], $input['purpose'], $input['category'], $pattern, $caseSensitive,
                    $input['match_priority'], $input['correlation_window_min'], json_encode($positives), json_encode($negatives),
                    $status, $actor,
                ]
            );
            $id = (int) Database::pdo()->lastInsertId();
        } else {
            Database::run(
                'UPDATE ' . $this->table() . ' SET
                    label=?, sender_id=?, purpose=?, category=?, pattern=?, case_sensitive=?, match_priority=?,
                    correlation_window_min=?, positive_samples=?, negative_samples=?, status=?,
                    row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ?',
                [
                    $input['label'], $input['sender_id'], $input['purpose'], $input['category'], $pattern, $caseSensitive,
                    $input['match_priority'], $input['correlation_window_min'], json_encode($positives), json_encode($negatives),
                    $status, $actor, $id,
                ]
            );
        }
        $this->writeRevision($key, $isNew ? 'create' : 'update');
        Audit::log(['action' => $isNew ? 'template.create' : 'template.update', 'entity_type' => 'message_template', 'entity_id' => $key]);
        Flash::success('Template saved (' . $status . '). Publish to apply.');
        $this->redirect('/message-templates');
    }

    public function setStatus(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');
        $status = (string) $request->post('status', 'draft');
        if (!in_array($status, ['active', 'draft', 'archived'], true)) { $status = 'draft'; }
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { $this->redirect('/message-templates'); }
        // Guard activation without valid samples.
        if ($status === 'active') {
            $pos = json_decode((string) $row['positive_samples'], true) ?: [];
            $neg = json_decode((string) $row['negative_samples'], true) ?: [];
            if (!$pos || !$neg) {
                Flash::error('Add positive and negative samples on the edit screen before activating.');
                $this->redirect('/message-templates/' . (int) $id . '/edit');
            }
        }
        Database::run('UPDATE ' . $this->table() . ' SET status=?, row_version=row_version+1, updated_at=UTC_TIMESTAMP() WHERE id=?', [$status, (int) $id]);
        $this->writeRevision($row['template_key'], $status);
        Audit::log(['action' => 'template.' . $status, 'entity_type' => 'message_template', 'entity_id' => $row['template_key']]);
        Flash::success('Template ' . $status . '. Publish to apply.');
        $this->redirect('/message-templates');
    }

    public function duplicate(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { $this->redirect('/message-templates'); }
        $newKey = $row['template_key'] . '_copy';
        $i = 1;
        while (Database::fetch('SELECT id FROM ' . $this->table() . ' WHERE template_key = ?', [$newKey])) {
            $newKey = $row['template_key'] . '_copy_' . (++$i);
        }
        Database::run(
            'INSERT INTO ' . $this->table() . '
                (template_key, label, sender_id, purpose, category, pattern_type, pattern, case_sensitive,
                 match_priority, correlation_window_min, positive_samples, negative_samples, status,
                 row_version, created_at, updated_at, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'draft\', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)',
            [
                $newKey, $row['label'] . ' (copy)', $row['sender_id'], $row['purpose'], $row['category'], $row['pattern_type'],
                $row['pattern'], $row['case_sensitive'], $row['match_priority'], $row['correlation_window_min'],
                $row['positive_samples'], $row['negative_samples'], Auth::user()['name'] ?? 'system',
            ]
        );
        Audit::log(['action' => 'template.duplicate', 'entity_type' => 'message_template', 'entity_id' => $newKey]);
        Flash::success("Duplicated as draft “{$newKey}”.");
        $this->redirect('/message-templates');
    }

    public function delete(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { $this->redirect('/message-templates'); }
        if ($row['status'] !== 'draft') {
            Flash::error('Only draft templates can be deleted. Archive active templates instead.');
            $this->redirect('/message-templates');
        }
        $this->writeRevision($row['template_key'], 'delete');
        Database::run('DELETE FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        Audit::log(['action' => 'template.delete', 'entity_type' => 'message_template', 'entity_id' => $row['template_key']]);
        Flash::success('Draft template deleted.');
        $this->redirect('/message-templates');
    }

    /* -------------------------------------------------- test one sample */

    /** Test a single sample message against a saved template; flashes the result. */
    public function testSample(Request $request): void
    {
        Csrf::check($request);
        $this->guard('templates.manage');
        $id = (int) $request->post('id', 0);
        $sample = (string) $request->post('sample', '');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        if (!$row) { Flash::error('Save the template first, then test a sample message.'); $this->redirect('/message-templates'); }

        $res = TemplateMatcher::test(
            ['sender_id' => $row['sender_id'], 'pattern' => $row['pattern'], 'case_sensitive' => (int) $row['case_sensitive']],
            (string) $row['sender_id'],
            $sample
        );
        if ($res['error']) {
            Flash::error('Pattern problem: ' . $res['error']);
        } elseif ($res['matched']) {
            Flash::success('That sample MATCHES this template.');
        } elseif (!$res['senderMatched']) {
            Flash::warning('The sender does not match this template’s Sender ID.');
        } else {
            Flash::warning('That sample does NOT match this template’s pattern.');
        }
        $this->redirect('/message-templates/' . $id . '/edit');
    }

    /* helpers */
    private function lines(string $raw): array
    {
        $out = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: []));
        return array_values($out);
    }

    private function writeRevision(string $key, string $action): void
    {
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE template_key = ?', [$key]);
        Database::run(
            'INSERT INTO ' . Database::table('message_template_revisions') . ' (template_key, snapshot_json, action, actor_name, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$key, json_encode($row), $action, Auth::user()['name'] ?? 'system']
        );
    }
}
