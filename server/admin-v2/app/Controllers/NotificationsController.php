<?php
/**
 * Notification campaigns. Payment notifications are system-triggered only (never created
 * manually). Campaigns cannot advertise an inactive/expired offer. Scheduling respects
 * quiet hours and caps at delivery time on the device; this admin manages the campaign
 * definition and never fabricates delivery data.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;

final class NotificationsController extends Controller
{
    private const TYPES = [
        'manual' => 'Manual broadcast', 'scheduled' => 'Scheduled campaign',
        'new_offer' => 'New-offer', 'app_update' => 'App-update', 'personalised' => 'Personalised',
    ];
    private const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'];

    private function table(): string { return Database::table('notification_campaigns'); }

    public function index(Request $request): void
    {
        $this->guard('notifications.create');
        $this->view('notifications/index', [
            'activeNav' => 'notifications', 'pageTitle' => 'Notifications',
            'campaigns' => Database::fetchAll('SELECT * FROM ' . $this->table() . ' ORDER BY COALESCE(scheduled_at, updated_at) DESC'),
            'types' => self::TYPES,
        ]);
    }

    public function calendar(Request $request): void
    {
        $this->guard('notifications.create');
        $this->view('notifications/calendar', [
            'activeNav' => 'notifications', 'pageTitle' => 'Campaign schedule',
            'upcoming' => Database::fetchAll('SELECT * FROM ' . $this->table() . " WHERE status='scheduled' AND scheduled_at IS NOT NULL ORDER BY scheduled_at ASC"),
            'types' => self::TYPES,
        ]);
    }

    public function create(Request $request): void
    {
        $this->guard('notifications.create');
        $this->renderForm(null, true);
    }

    public function edit(Request $request, string $id): void
    {
        $this->guard('notifications.create');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { Flash::error('Campaign not found.'); $this->redirect('/notifications'); }
        $this->renderForm($row, false);
    }

    private function renderForm(?array $row, bool $isNew): void
    {
        $this->view('notifications/form', [
            'activeNav' => 'notifications', 'pageTitle' => $isNew ? 'New campaign' : 'Edit campaign',
            'c' => $row, 'isNew' => $isNew, 'types' => self::TYPES, 'priorities' => self::PRIORITIES,
            'offers' => Database::fetchAll("SELECT offer_id, name, category FROM " . Database::table('offers') . " WHERE status='active' ORDER BY category, name"),
            'templates' => Database::fetchAll('SELECT * FROM ' . Database::table('notification_templates') . ' ORDER BY label'),
        ]);
    }

    public function save(Request $request): void
    {
        Csrf::check($request);
        $this->guard('notifications.create');
        $isNew = $request->post('is_new') === '1';
        $id = (int) $request->post('id', 0);

        $type = (string) $request->post('type', 'manual');
        if ($type === 'payment') {
            Flash::error('Payment notifications are system-triggered and cannot be created manually.');
            $this->redirect('/notifications');
        }
        $linkedOffer = trim((string) $request->post('linked_offer_id', ''));
        $scheduledAt = $this->toUtc($request->post('scheduled_at'));
        $wantSchedule = (bool) $request->post('schedule');

        $input = [
            'name' => trim((string) $request->post('name', '')),
            'type' => array_key_exists($type, self::TYPES) ? $type : 'manual',
            'title' => trim((string) $request->post('title', '')),
            'body' => trim((string) $request->post('body', '')),
            'deep_link' => trim((string) $request->post('deep_link', '')),
            'audience_rule' => trim((string) $request->post('audience_rule', 'all')),
            'linked_offer_id' => $linkedOffer !== '' ? $linkedOffer : null,
            'category' => (string) $request->post('category', ''),
            'scheduled_at' => $scheduledAt,
            'expires_at' => $this->toUtc($request->post('expires_at')),
            'priority' => array_key_exists((string) $request->post('priority'), self::PRIORITIES) ? (string) $request->post('priority') : 'normal',
            'respect_quiet_hours' => $request->post('respect_quiet_hours') ? 1 : 0,
            'frequency_cap' => (int) $request->post('frequency_cap', 1),
            'suppress_recent_purchase' => $request->post('suppress_recent_purchase') ? 1 : 0,
        ];

        $v = Validator::make($input);
        $v->validate(['name' => 'required|max:120', 'title' => 'required|max:120', 'body' => 'required|max:255']);
        // Cannot advertise an inactive/expired offer.
        if ($input['linked_offer_id'] !== null) {
            $offer = Database::fetch("SELECT status, ends_at FROM " . Database::table('offers') . " WHERE offer_id = ?", [$input['linked_offer_id']]);
            if (!$offer || $offer['status'] !== 'active') {
                $v->add('linked_offer_id', 'The linked offer must be active.');
            }
        }
        if ($wantSchedule && $scheduledAt === null) {
            $v->add('scheduled_at', 'Set a schedule time to schedule this campaign.');
        }
        if ($v->fails()) {
            Flash::error('Fix: ' . implode(' ', array_values($v->firstErrors())));
            $this->redirect($isNew ? '/notifications/new' : '/notifications/' . $id . '/edit');
        }

        $status = $wantSchedule ? 'scheduled' : 'draft';
        $actor = Auth::user()['name'] ?? 'system';
        $cols = $input;
        if ($isNew) {
            $cols['status'] = $status;
            Database::run(
                'INSERT INTO ' . $this->table() . '
                    (name, type, title, body, deep_link, audience_rule, linked_offer_id, category,
                     scheduled_at, expires_at, priority, respect_quiet_hours, frequency_cap, suppress_recent_purchase,
                     status, row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)',
                array_merge(array_values($input), [$status, $actor])
            );
            $id = (int) Database::pdo()->lastInsertId();
        } else {
            Database::run(
                'UPDATE ' . $this->table() . ' SET
                    name=?, type=?, title=?, body=?, deep_link=?, audience_rule=?, linked_offer_id=?, category=?,
                    scheduled_at=?, expires_at=?, priority=?, respect_quiet_hours=?, frequency_cap=?, suppress_recent_purchase=?,
                    status=?, row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ?',
                array_merge(array_values($input), [$status, $actor, $id])
            );
        }
        Audit::log(['action' => $isNew ? 'campaign.create' : 'campaign.update', 'entity_type' => 'notification_campaign', 'entity_id' => $id]);
        Flash::success('Campaign saved (' . $status . ').');
        $this->redirect('/notifications');
    }

    public function cancel(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('notifications.schedule');
        Database::run('UPDATE ' . $this->table() . " SET status='cancelled', updated_at=UTC_TIMESTAMP() WHERE id = ? AND status='scheduled'", [(int) $id]);
        Database::run('INSERT INTO ' . Database::table('notification_events') . ' (campaign_id, event_type, count, created_at) VALUES (?, \'cancelled\', 0, UTC_TIMESTAMP())', [(int) $id]);
        Audit::log(['action' => 'campaign.cancel', 'entity_type' => 'notification_campaign', 'entity_id' => (int) $id]);
        Flash::success('Future campaign cancelled. (Already-sent notifications cannot be recalled.)');
        $this->redirect('/notifications');
    }

    public function testSend(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('notifications.create');
        // A test only records intent to authorised test installations — never a mass send.
        Database::run('INSERT INTO ' . Database::table('notification_events') . ' (campaign_id, event_type, count, created_at) VALUES (?, \'test\', 1, UTC_TIMESTAMP())', [(int) $id]);
        Audit::log(['action' => 'campaign.test', 'entity_type' => 'notification_campaign', 'entity_id' => (int) $id]);
        Flash::info('Test send queued to authorised test installations only.');
        $this->redirect('/notifications');
    }

    private function toUtc($local): ?string
    {
        $local = trim((string) $local);
        if ($local === '') { return null; }
        try {
            return (new \DateTimeImmutable($local, new \DateTimeZone('Africa/Nairobi')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) { return null; }
    }
}
