<?php
/**
 * Notification rules.
 *
 * A notification is no longer "a message we send". It is a RULE the offline-first
 * Android app evaluates locally: a category, a trigger, an optional schedule window, a
 * rest period and several wording variations the app picks from at random. The server
 * never learns who saw what — no name, number, balance or purchase history reaches it.
 *
 * Categories, triggers and variables come from catalogue tables (see
 * App\Services\NotificationService), so adding a new one is a data change.
 *
 * Status: 'draft' (kept here only), 'active' (published as a rule), 'cancelled'
 * (retired). PublishingService publishes rows with enabled = 1 AND status = 'active'.
 * Legacy 'sent' rows from older installs are read-only history and must never crash
 * this screen.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;
use App\Services\NotificationService;

final class NotificationsController extends Controller
{
    private const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'];

    /** Statuses an administrator can choose between on this screen. */
    private const STATUSES = [
        'active'    => 'Active',
        'draft'     => 'Draft',
        'cancelled' => 'Retired',
        'sent'      => 'History',
    ];

    private function table(): string
    {
        return Database::table('notification_campaigns');
    }

    private function varTable(): string
    {
        return Database::table('notification_variations');
    }

    private function actor(): string
    {
        $user = Auth::user();
        return (string) ($user['name'] ?? 'system');
    }

    /* ------------------------------------------------------------------ list */

    public function index(Request $request): void
    {
        $this->guard('notifications.create');

        $filters = [
            'q'        => trim((string) $request->get('q', '')),
            'category' => trim((string) $request->get('category', '')),
            'trigger'  => trim((string) $request->get('trigger', '')),
            'status'   => trim((string) $request->get('status', '')),
        ];

        $where = [];
        $params = [];
        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $where[] = '(c.name LIKE ? OR c.title LIKE ? OR c.body LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($filters['category'] !== '') {
            $where[] = 'c.category = ?';
            $params[] = $filters['category'];
        }
        if ($filters['trigger'] !== '') {
            $where[] = 'c.trigger_type = ?';
            $params[] = $filters['trigger'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'c.status = ?';
            $params[] = $filters['status'];
        }

        $sql = 'SELECT c.*,
                       (SELECT COUNT(*) FROM ' . $this->varTable() . ' v WHERE v.campaign_id = c.id) AS variation_count
                  FROM ' . $this->table() . ' c'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY FIELD(c.status,'active','draft','cancelled','sent'), c.category, c.name";

        $rows = Database::fetchAll($sql, $params);
        $now = nairobi_now();
        foreach ($rows as $i => $row) {
            $rows[$i]['schedule_summary'] = NotificationService::describeSchedule($row);
            $rows[$i]['live_now'] = (int) ($row['enabled'] ?? 0) === 1
                && (string) ($row['status'] ?? '') === 'active'
                && NotificationService::isWithinWindow($row, $now);
        }

        $this->view('notifications/index', [
            'activeNav'  => 'notifications',
            'pageTitle'  => 'Notifications',
            'campaigns'  => $rows,
            'filters'    => $filters,
            'categories' => NotificationService::categories(),
            'triggers'   => NotificationService::triggers(),
            'statuses'   => self::STATUSES,
            'checkedAt'  => $now->format('D d M Y, H:i'),
        ]);
    }

    /* -------------------------------------------------------------- calendar */

    /**
     * Month view of the schedule WINDOWS (starts_on..ends_on plus the chosen days),
     * not a list of sends. A rule with no window simply shows on every day.
     */
    public function calendar(Request $request): void
    {
        $this->guard('notifications.create');

        $now = nairobi_now();
        $requested = trim((string) $request->get('month', ''));
        $monthKey = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested) === 1
            ? $requested
            : $now->format('Y-m');

        $first = NotificationService::parseDate($monthKey . '-01');
        if ($first === null) {
            $first = NotificationService::parseDate($now->format('Y-m-01'));
        }
        // parseDate cannot fail for a self-generated Y-m-01, but keep the type honest.
        if ($first === null) {
            $first = $now->setTime(0, 0);
        }

        $rules = Database::fetchAll(
            'SELECT * FROM ' . $this->table() . "
              WHERE status IN ('active','draft') ORDER BY category, name"
        );
        foreach ($rules as $i => $rule) {
            $rules[$i]['schedule_summary'] = NotificationService::describeSchedule($rule);
        }

        $daysInMonth = (int) $first->format('t');
        $today = $now->format('Y-m-d');
        $cells = [];
        for ($lead = (int) $first->format('N') - 1; $lead > 0; $lead--) {
            $cells[] = null;
        }
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
            $items = [];
            foreach ($rules as $rule) {
                // Day-level view: ignore the time-of-day window so a 19:00-21:00 rule
                // still marks the days it is allowed on.
                $dateOnly = $rule;
                $dateOnly['allowed_time_start'] = '';
                $dateOnly['allowed_time_end'] = '';
                if (NotificationService::isWithinWindow($dateOnly, $date)) {
                    $items[] = $rule;
                }
            }
            $cells[] = [
                'day'   => $day,
                'date'  => $date->format('Y-m-d'),
                'today' => $date->format('Y-m-d') === $today,
                'items' => $items,
            ];
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        $this->view('notifications/calendar', [
            'activeNav'  => 'notifications',
            'pageTitle'  => 'Notification schedule',
            'weeks'      => array_chunk($cells, 7),
            'monthLabel' => $first->format('F Y'),
            'prevMonth'  => $first->modify('-1 month')->format('Y-m'),
            'nextMonth'  => $first->modify('+1 month')->format('Y-m'),
            'thisMonth'  => $now->format('Y-m'),
            'rules'      => $rules,
            'dayNames'   => NotificationService::DAY_NAMES,
        ]);
    }

    /* ------------------------------------------------------------------ form */

    public function create(Request $request): void
    {
        $this->guard('notifications.create');
        $this->renderForm(null, true, []);
    }

    public function edit(Request $request, string $id): void
    {
        $this->guard('notifications.create');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) {
            Flash::error('Notification rule not found.');
            $this->redirect('/notifications');
        }
        $this->renderForm($row, false, $this->variationsOf((int) $id));
    }

    private function variationsOf(int $campaignId): array
    {
        return Database::fetchAll(
            'SELECT * FROM ' . $this->varTable() . ' WHERE campaign_id = ? ORDER BY sort_order, id',
            [$campaignId]
        );
    }

    private function renderForm(?array $row, bool $isNew, array $variations, array $preview = []): void
    {
        $this->view('notifications/form', [
            'activeNav'  => 'notifications',
            'pageTitle'  => $isNew ? 'New notification' : 'Edit notification',
            'c'          => $row,
            'isNew'      => $isNew,
            'variations' => $variations,
            'preview'    => $preview,
            'categories' => NotificationService::categories(),
            'triggers'   => NotificationService::triggers(),
            'variables'  => NotificationService::variables(),
            'events'     => Database::fetchAll(
                'SELECT event_key, label FROM ' . Database::table('sms_event_types') . '
                  WHERE enabled = 1 ORDER BY sort_order, event_key'
            ),
            'offers'     => Database::fetchAll(
                'SELECT offer_id, name, category FROM ' . Database::table('offers') . "
                  WHERE status='active' ORDER BY category, name"
            ),
            'priorities' => self::PRIORITIES,
            'dayNames'   => NotificationService::DAY_NAMES,
        ]);
    }

    /* ------------------------------------------------------------------ save */

    public function save(Request $request): void
    {
        Csrf::check($request);
        $this->guard('notifications.create');

        $isNew = $request->post('is_new') === '1';
        $id = (int) $request->post('id', 0);
        $backTo = $isNew ? '/notifications/new' : '/notifications/' . $id . '/edit';

        $categories = NotificationService::categories();
        $triggers   = NotificationService::triggers();
        $variables  = NotificationService::variables();

        $input      = $this->collectInput($request);
        $rawDays    = $request->post('days', []);
        $variations = $this->collectVariations($request);

        $v = Validator::make($input);
        $v->validate(['name' => 'required|max:120']);

        if (!isset($categories[$input['category']])) {
            $v->add('category', 'Choose what kind of message this is.');
        }
        if (!isset($triggers[$input['trigger_type']])) {
            $v->add('trigger_type', 'Choose when this message is allowed to appear.');
        } elseif ((int) $triggers[$input['trigger_type']]['needs_event'] === 1) {
            if ($input['trigger_event'] === '') {
                $v->add('trigger_event', 'Choose the phone message that sets this off.');
            } else {
                $exists = Database::scalar(
                    'SELECT COUNT(*) FROM ' . Database::table('sms_event_types') . ' WHERE event_key = ?',
                    [$input['trigger_event']]
                );
                if ((int) $exists === 0) {
                    $v->add('trigger_event', 'That phone-message event no longer exists.');
                }
            }
        } else {
            $input['trigger_event'] = '';
        }

        // Unsupported {{tokens}}: nothing on the device could ever fill them in.
        $known = array_keys($variables);
        $bad = [];
        foreach ($variations as $variation) {
            foreach (NotificationService::unsupportedTokens($variation['title'] . ' ' . $variation['body'], $known) as $token) {
                $bad[$token] = true;
            }
        }
        if ($bad !== []) {
            $supported = $known === []
                ? 'none are configured yet'
                : implode(', ', array_map(static fn($k) => '{{' . $k . '}}', $known));
            $v->add(
                'variations',
                'Unknown wording variable ' . implode(', ', array_map(static fn($k) => '{{' . $k . '}}', array_keys($bad)))
                . '. Supported: ' . $supported . '.'
            );
        }

        if ($variations === [] && $input['status'] === 'active') {
            $v->add('variations', 'Write at least one wording before making this notification active.');
        }

        foreach (NotificationService::validateSchedule([
            'starts_on'          => $input['starts_on'],
            'ends_on'            => $input['ends_on'],
            'days'               => $rawDays,
            'allowed_time_start' => $input['allowed_time_start'],
            'allowed_time_end'   => $input['allowed_time_end'],
            'cooldown_minutes'   => $request->post('cooldown_minutes', 0),
        ]) as $i => $message) {
            $v->add('schedule_' . $i, $message);
        }

        if ($v->fails()) {
            Flash::error('Fix: ' . implode(' ', array_values($v->firstErrors())));
            Flash::keepOld(array_merge($input, [
                'days'            => NotificationService::dayList($rawDays),
                'variation_title' => array_column($variations, 'title'),
                'variation_body'  => array_column($variations, 'body'),
                '_errors'         => $v->firstErrors(),
            ]));
            $this->redirect($backTo);
        }

        $before = null;
        if (!$isNew) {
            $before = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
            if (!$before) {
                Flash::error('Notification rule not found.');
                $this->redirect('/notifications');
            }
            $before['variations'] = $this->variationsOf($id);
        }

        // The campaign's own title/body mirror the FIRST wording so older readers (and
        // the PublishingService fallback) never see an empty notification.
        $first = $variations[0] ?? ['title' => '', 'body' => ''];
        $cols = [
            'name'                     => mb_substr($input['name'], 0, 120),
            'title'                    => mb_substr($first['title'], 0, 120),
            'body'                     => mb_substr($first['body'], 0, 255),
            'deep_link'                => mb_substr($input['deep_link'], 0, 120),
            'linked_offer_id'          => $input['linked_offer_id'],
            'category'                 => $input['category'],
            'trigger_type'             => $input['trigger_type'],
            'trigger_event'            => $input['trigger_event'],
            'starts_on'                => $input['starts_on'] !== '' ? $input['starts_on'] : null,
            'ends_on'                  => $input['ends_on'] !== '' ? $input['ends_on'] : null,
            'days_of_week'             => $input['days_of_week'],
            'allowed_time_start'       => $input['allowed_time_start'],
            'allowed_time_end'         => $input['allowed_time_end'],
            'cooldown_minutes'         => $input['cooldown_minutes'],
            'expires_at'               => $input['expires_at'],
            'priority'                 => $input['priority'],
            'respect_quiet_hours'      => $input['respect_quiet_hours'],
            'frequency_cap'            => $input['frequency_cap'],
            'suppress_recent_purchase' => $input['suppress_recent_purchase'],
            'enabled'                  => $input['enabled'],
            'notes'                    => mb_substr($input['notes'], 0, 255),
            'status'                   => $input['status'],
        ];

        $actor = $this->actor();
        $savedId = Database::transaction(function () use ($cols, $variations, $isNew, $id, $actor) {
            $fields = array_keys($cols);
            if ($isNew) {
                Database::run(
                    'INSERT INTO ' . $this->table() . ' (' . implode(', ', $fields) . ',
                        row_version, created_at, updated_at, updated_by)
                     VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ',
                        1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)',
                    array_merge(array_values($cols), [$actor])
                );
                $id = (int) Database::pdo()->lastInsertId();
            } else {
                $set = [];
                foreach ($fields as $field) {
                    $set[] = $field . ' = ?';
                }
                Database::run(
                    'UPDATE ' . $this->table() . ' SET ' . implode(', ', $set) . ',
                        row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ?
                      WHERE id = ?',
                    array_merge(array_values($cols), [$actor, $id])
                );
            }
            $this->rewriteVariations($id, $variations);
            return $id;
        });

        $after = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$savedId]);
        if ($after !== null) {
            $after['variations'] = $this->variationsOf($savedId);
        }
        Audit::log([
            'action'      => $isNew ? 'notification.create' : 'notification.update',
            'entity_type' => 'notification',
            'entity_id'   => (string) $savedId,
            'before'      => $before,
            'after'       => $after,
        ]);
        Flash::success('Saved. Publish to apply.');
        $this->redirect('/notifications');
    }

    /** Delete the campaign's wordings and insert the submitted set in form order. */
    private function rewriteVariations(int $campaignId, array $variations): void
    {
        Database::run('DELETE FROM ' . $this->varTable() . ' WHERE campaign_id = ?', [$campaignId]);
        if ($variations === []) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . $this->varTable() . '
                (campaign_id, title, body, sort_order, enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        foreach ($variations as $i => $variation) {
            $stmt->execute([
                $campaignId,
                mb_substr($variation['title'], 0, 120),
                mb_substr($variation['body'], 0, 255),
                $i,
            ]);
        }
    }

    /* --------------------------------------------------------------- preview */

    /**
     * Render the submitted wordings with catalogue sample values and answer "would this
     * show right now?". Renders the form again with everything the operator typed —
     * a preview must never throw away unsaved work.
     */
    public function preview(Request $request): void
    {
        Csrf::check($request);
        $this->guard('notifications.create');

        $isNew = $request->post('is_new') === '1';
        $id = (int) $request->post('id', 0);
        $input = $this->collectInput($request);
        $variations = $this->collectVariations($request);
        $variables = NotificationService::variables();

        $map = [];
        foreach ($variables as $key => $row) {
            $map[$key] = (string) ($row['sample_value'] ?? '');
        }
        $known = array_keys($variables);

        $rendered = [];
        foreach ($variations as $i => $variation) {
            $rendered[] = [
                'number'      => $i + 1,
                'title'       => NotificationService::render($variation['title'], $map),
                'body'        => NotificationService::render($variation['body'], $map),
                'unsupported' => NotificationService::unsupportedTokens(
                    $variation['title'] . ' ' . $variation['body'],
                    $known
                ),
            ];
        }

        $scheduleErrors = NotificationService::validateSchedule([
            'starts_on'          => $input['starts_on'],
            'ends_on'            => $input['ends_on'],
            'days'               => $request->post('days', []),
            'allowed_time_start' => $input['allowed_time_start'],
            'allowed_time_end'   => $input['allowed_time_end'],
            'cooldown_minutes'   => $request->post('cooldown_minutes', 0),
        ]);

        $now = nairobi_now();
        $preview = [
            'variations' => $rendered,
            'schedule'   => NotificationService::describeSchedule($input),
            'errors'     => $scheduleErrors,
            'live_now'   => $scheduleErrors === [] && NotificationService::isWithinWindow($input, $now),
            'checked_at' => $now->format('D d M Y, H:i'),
        ];

        // Rebuild the row from the submission so nothing typed is lost.
        $row = $input;
        $row['id'] = $id;
        $row['row_version'] = (int) $request->post('row_version', 0);
        $this->renderForm($row, $isNew, $variations, $preview);
    }

    /* ----------------------------------------------------------- row actions */

    public function toggle(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('notifications.create');
        $row = $this->requireRow((int) $id);
        $next = (int) ($row['enabled'] ?? 0) === 1 ? 0 : 1;

        Database::run(
            'UPDATE ' . $this->table() . ' SET enabled = ?, row_version = row_version + 1,
                updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ?',
            [$next, $this->actor(), (int) $id]
        );
        Audit::log([
            'action'      => $next === 1 ? 'notification.enable' : 'notification.disable',
            'entity_type' => 'notification',
            'entity_id'   => (string) (int) $id,
            'before'      => ['enabled' => (int) ($row['enabled'] ?? 0)],
            'after'       => ['enabled' => $next],
        ]);
        Flash::success($next === 1
            ? 'Notification switched on. Publish to apply.'
            : 'Notification switched off. Publish to apply.');
        $this->redirect('/notifications');
    }

    public function duplicate(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('notifications.create');
        $src = $this->requireRow((int) $id);
        $actor = $this->actor();

        $newId = Database::transaction(function () use ($src, $actor) {
            Database::run(
                'INSERT INTO ' . $this->table() . '
                    (name, type, title, body, deep_link, audience_rule, linked_offer_id, category,
                     trigger_type, trigger_event, starts_on, ends_on, days_of_week,
                     allowed_time_start, allowed_time_end, cooldown_minutes, expires_at, priority,
                     respect_quiet_hours, frequency_cap, suppress_recent_purchase, notes,
                     enabled, status, row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                         0, \'draft\', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)',
                [
                    mb_substr((string) $src['name'] . ' (copy)', 0, 120),
                    (string) ($src['type'] ?? 'manual'),
                    (string) ($src['title'] ?? ''),
                    (string) ($src['body'] ?? ''),
                    (string) ($src['deep_link'] ?? ''),
                    (string) ($src['audience_rule'] ?? 'all'),
                    $src['linked_offer_id'],
                    (string) ($src['category'] ?? ''),
                    (string) ($src['trigger_type'] ?? 'manual'),
                    (string) ($src['trigger_event'] ?? ''),
                    $src['starts_on'] ?: null,
                    $src['ends_on'] ?: null,
                    (string) ($src['days_of_week'] ?? ''),
                    (string) ($src['allowed_time_start'] ?? ''),
                    (string) ($src['allowed_time_end'] ?? ''),
                    (int) ($src['cooldown_minutes'] ?? 0),
                    $src['expires_at'],
                    (string) ($src['priority'] ?? 'normal'),
                    (int) ($src['respect_quiet_hours'] ?? 1),
                    (int) ($src['frequency_cap'] ?? 1),
                    (int) ($src['suppress_recent_purchase'] ?? 1),
                    (string) ($src['notes'] ?? ''),
                    $actor,
                ]
            );
            $copyId = (int) Database::pdo()->lastInsertId();
            $this->rewriteVariations($copyId, array_map(
                static fn(array $r) => ['title' => (string) $r['title'], 'body' => (string) $r['body']],
                $this->variationsOf((int) $src['id'])
            ));
            return $copyId;
        });

        Audit::log([
            'action'      => 'notification.duplicate',
            'entity_type' => 'notification',
            'entity_id'   => (string) $newId,
            'after'       => ['copied_from' => (int) $src['id'], 'status' => 'draft', 'enabled' => 0],
        ]);
        Flash::success('Copied as a switched-off draft. Edit it, then publish to apply.');
        $this->redirect('/notifications/' . $newId . '/edit');
    }

    /** Retire a rule. It stops being published; already-shown notifications cannot be recalled. */
    public function cancel(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('notifications.create');
        $row = $this->requireRow((int) $id);

        Database::run(
            'UPDATE ' . $this->table() . " SET status='cancelled', row_version = row_version + 1,
                updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ?",
            [$this->actor(), (int) $id]
        );
        Database::run(
            'INSERT INTO ' . Database::table('notification_events') . "
                (campaign_id, event_type, count, created_at) VALUES (?, 'cancelled', 0, UTC_TIMESTAMP())",
            [(int) $id]
        );
        Audit::log([
            'action'      => 'notification.cancel',
            'entity_type' => 'notification',
            'entity_id'   => (string) (int) $id,
            'before'      => ['status' => (string) ($row['status'] ?? '')],
            'after'       => ['status' => 'cancelled'],
        ]);
        Flash::success('Notification retired. Publish to remove it from the app. (Notifications already shown cannot be recalled.)');
        $this->redirect('/notifications');
    }

    public function delete(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('notifications.create');
        $row = $this->requireRow((int) $id);

        // A switched-on rule may be live on phones right now: require an explicit confirm.
        if ((int) ($row['enabled'] ?? 0) === 1 && (string) $request->post('confirm', '') !== '1') {
            Flash::error('This notification is switched on. Switch it off or confirm the deletion first.');
            $this->redirect('/notifications');
        }

        Database::transaction(function () use ($id) {
            Database::run('DELETE FROM ' . $this->varTable() . ' WHERE campaign_id = ?', [(int) $id]);
            Database::run('DELETE FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        });
        Audit::log([
            'action'      => 'notification.delete',
            'entity_type' => 'notification',
            'entity_id'   => (string) (int) $id,
            'before'      => $row,
        ]);
        Flash::success('Notification deleted. Publish to apply.');
        $this->redirect('/notifications');
    }

    public function testSend(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('notifications.create');
        $this->requireRow((int) $id);
        // A test only records intent for authorised test installations — never a mass send.
        Database::run(
            'INSERT INTO ' . Database::table('notification_events') . "
                (campaign_id, event_type, count, created_at) VALUES (?, 'test', 1, UTC_TIMESTAMP())",
            [(int) $id]
        );
        Audit::log([
            'action'      => 'notification.test',
            'entity_type' => 'notification',
            'entity_id'   => (string) (int) $id,
        ]);
        Flash::info('Test queued to authorised test installations only.');
        $this->redirect('/notifications');
    }

    /* --------------------------------------------------------------- helpers */

    private function requireRow(int $id): array
    {
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        if (!$row) {
            Flash::error('Notification rule not found.');
            $this->redirect('/notifications');
        }
        return (array) $row;
    }

    /** Everything the form submits except the wordings, shaped like a campaign row. */
    private function collectInput(Request $request): array
    {
        $priority = (string) $request->post('priority', 'normal');
        $linkedOffer = trim((string) $request->post('linked_offer_id', ''));

        return [
            'name'                     => trim((string) $request->post('name', '')),
            'category'                 => trim((string) $request->post('category', '')),
            'trigger_type'             => trim((string) $request->post('trigger_type', 'manual')),
            'trigger_event'            => trim((string) $request->post('trigger_event', '')),
            'notes'                    => trim((string) $request->post('notes', '')),
            'deep_link'                => trim((string) $request->post('deep_link', '')),
            'linked_offer_id'          => $linkedOffer !== '' ? $linkedOffer : null,
            'priority'                 => array_key_exists($priority, self::PRIORITIES) ? $priority : 'normal',
            'starts_on'                => trim((string) $request->post('starts_on', '')),
            'ends_on'                  => trim((string) $request->post('ends_on', '')),
            'days_of_week'             => implode(',', NotificationService::dayList($request->post('days', []))),
            'allowed_time_start'       => trim((string) $request->post('allowed_time_start', '')),
            'allowed_time_end'         => trim((string) $request->post('allowed_time_end', '')),
            'cooldown_minutes'         => max(0, min(
                NotificationService::MAX_COOLDOWN_MINUTES,
                (int) $request->post('cooldown_minutes', 0)
            )),
            'frequency_cap'            => max(0, (int) $request->post('frequency_cap', 1)),
            'respect_quiet_hours'      => $request->post('respect_quiet_hours') ? 1 : 0,
            'suppress_recent_purchase' => $request->post('suppress_recent_purchase') ? 1 : 0,
            'expires_at'               => $this->toUtc($request->post('expires_at')),
            'enabled'                  => $request->post('enabled') ? 1 : 0,
            'status'                   => $request->post('active') ? 'active' : 'draft',
        ];
    }

    /**
     * The repeated wording rows. A row with an empty title AND an empty body is dropped,
     * so the operator can leave spare rows on the form.
     *
     * @return array<int,array{title:string, body:string}>
     */
    private function collectVariations(Request $request): array
    {
        $titles = $request->post('variation_title', []);
        $bodies = $request->post('variation_body', []);
        $titles = is_array($titles) ? array_values($titles) : [];
        $bodies = is_array($bodies) ? array_values($bodies) : [];

        $out = [];
        $count = max(count($titles), count($bodies));
        for ($i = 0; $i < $count; $i++) {
            $title = trim((string) ($titles[$i] ?? ''));
            $body  = trim((string) ($bodies[$i] ?? ''));
            if ($title === '' && $body === '') {
                continue;
            }
            $out[] = ['title' => $title, 'body' => $body];
        }
        return $out;
    }

    private function toUtc($local): ?string
    {
        $local = trim((string) $local);
        if ($local === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($local, new \DateTimeZone('Africa/Nairobi')))
                ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
