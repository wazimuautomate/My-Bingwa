<?php
/**
 * Controller for Admin Push Notifications (FCM HTTP v1).
 *
 * Allows administrators to compose and instantly send push notifications
 * to all registered app users.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\FcmService;
use Throwable;

final class PushController extends Controller
{
    private function actor(): string
    {
        $user = Auth::user();
        return (string) ($user['name'] ?? 'admin');
    }

    public function index(Request $request): void
    {
        $this->guard('notifications.create');

        $isConfigured = FcmService::isConfigured();
        $tokenCount = 0;
        $history = [];

        try {
            $customerTable = Database::table('customers');
            $countRow = Database::fetchOne("SELECT COUNT(DISTINCT fcm_token) AS c FROM {$customerTable} WHERE fcm_token IS NOT NULL AND fcm_token != ''");
            $tokenCount = (int) ($countRow['c'] ?? 0);
        } catch (Throwable $e) {
            $tokenCount = 0;
        }

        try {
            $pushTable = Database::table('push_broadcasts');
            $history = Database::fetchAll("SELECT * FROM {$pushTable} ORDER BY created_at DESC LIMIT 20");
        } catch (Throwable $e) {
            $history = [];
        }

        $this->view('push/index', [
            'pageTitle'    => 'Instant Push Notifications',
            'activeNav'    => 'push',
            'isConfigured' => $isConfigured,
            'tokenCount'   => $tokenCount,
            'history'      => $history,
            'csrfToken'    => Csrf::token(),
        ]);
    }

    public function send(Request $request): void
    {
        $this->guard('notifications.create');

        if (!Csrf::check((string) $request->post('csrf_token', ''))) {
            Flash::error('Security token expired. Please try again.');
            $this->redirect('/push');
            return;
        }

        $title = trim((string) $request->post('title', ''));
        $body = trim((string) $request->post('body', ''));
        $route = trim((string) $request->post('route', 'notifications'));

        $v = new Validator(['title' => $title, 'body' => $body]);
        $v->rule('required', 'title', 'Notification title is required.');
        $v->rule('max_length', 'title', 'Title must not exceed 120 characters.', 120);
        $v->rule('required', 'body', 'Message body is required.');
        $v->rule('max_length', 'body', 'Body must not exceed 500 characters.', 500);

        if (!$v->validate()) {
            Flash::error($v->firstError() ?? 'Please fill all required fields.');
            $this->back('/push', ['title' => $title, 'body' => $body, 'route' => $route]);
            return;
        }

        $allowedRoutes = ['notifications', 'home', 'offers', 'activity', 'help'];
        if (!in_array($route, $allowedRoutes, true)) {
            $route = 'notifications';
        }

        $result = FcmService::broadcast($title, $body, $route, $this->actor());

        if ($result['success']) {
            Audit::log(
                action: 'push.broadcast',
                resourceType: 'push_notification',
                resourceId: 'fcm_broadcast',
                before: null,
                after: ['title' => $title, 'body' => $body, 'route' => $route, 'sent' => $result['sent_count']],
                actor: $this->actor()
            );
            Flash::success("Push notification dispatched successfully! Reached {$result['sent_count']} target(s).");
        } else {
            $errMsg = $result['error'] ?? 'Unknown dispatch failure';
            Flash::error("Failed to send push notification: {$errMsg}");
        }

        $this->redirect('/push');
    }
}
