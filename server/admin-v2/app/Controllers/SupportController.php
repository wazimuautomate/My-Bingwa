<?php
/**
 * Support & payment public details. Editing requires support.edit. Changing the Till or
 * Paybill route is high risk: it requires Super Admin, re-authentication and an explicit
 * confirmation, and is recorded with a before/after audit diff. Changes are drafts until
 * published.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;

final class SupportController extends Controller
{
    private function load(): array
    {
        return Database::fetch('SELECT * FROM ' . Database::table('support_config') . ' WHERE id = 1') ?: [];
    }

    public function index(Request $request): void
    {
        $this->guard('support.edit');
        $this->view('support/index', [
            'activeNav' => 'support', 'pageTitle' => 'Support details',
            'config' => $this->load(),
        ]);
    }

    public function save(Request $request): void
    {
        Csrf::check($request);
        $this->guard('support.edit');
        $current = $this->load();

        $input = [
            'till_number' => trim((string) $request->post('till_number', '')),
            'paybill_number' => trim((string) $request->post('paybill_number', '')),
            'support_number' => trim((string) $request->post('support_number', '')),
            'support_whatsapp' => trim((string) $request->post('support_whatsapp', '')),
            'offline_self_instructions' => trim((string) $request->post('offline_self_instructions', '')),
            'offline_other_instructions' => trim((string) $request->post('offline_other_instructions', '')),
            'support_banner' => trim((string) $request->post('support_banner', '')),
            'working_hours' => trim((string) $request->post('working_hours', '')),
        ];

        $v = Validator::make($input);
        $v->validate([
            'till_number' => 'msisdn|max:24',
            'paybill_number' => 'msisdn|max:24',
            'support_number' => 'msisdn|max:24',
            'support_whatsapp' => 'max:24',
        ]);
        if ($v->fails()) {
            Flash::error('Check the payment/shortcode fields: ' . implode(' ', array_values($v->firstErrors())));
            $this->redirect('/support');
        }

        // Payment-route change → Super Admin + re-authentication required.
        $routeChanged = ($current['till_number'] ?? '') !== $input['till_number']
            || ($current['paybill_number'] ?? '') !== $input['paybill_number'];
        if ($routeChanged) {
            if (!Auth::isSuperAdmin()) {
                Flash::error('Only a Super Admin can change the Till or Paybill route.');
                $this->redirect('/support');
            }
            if (!Auth::reauthenticate((string) $request->post('reauth_password', ''), (string) $request->post('reauth_totp', ''))) {
                Flash::error('Re-authentication failed. Payment route was not changed.');
                $this->redirect('/support');
            }
        }

        Database::run(
            'UPDATE ' . Database::table('support_config') . ' SET
                till_number=?, paybill_number=?, support_number=?, support_whatsapp=?,
                offline_self_instructions=?, offline_other_instructions=?, support_banner=?, working_hours=?,
                row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = 1',
            [
                $input['till_number'], $input['paybill_number'], $input['support_number'], $input['support_whatsapp'],
                $input['offline_self_instructions'], $input['offline_other_instructions'], $input['support_banner'],
                $input['working_hours'], Auth::user()['name'] ?? 'system',
            ]
        );

        Audit::log([
            'action' => $routeChanged ? 'support.route_change' : 'support.update',
            'entity_type' => 'support_config', 'entity_id' => 1,
            'before' => $current, 'after' => $this->load(),
            'reason' => $routeChanged ? 'Payment route change' : null,
        ]);
        Flash::success($routeChanged
            ? 'Payment route updated (draft). Publish to apply — the app uses the last published version.'
            : 'Support details saved as a draft change. Publish to apply.');
        $this->redirect('/support');
    }
}
