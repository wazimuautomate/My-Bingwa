<?php
/**
 * Payment gateway settings (Super Admin only). Manages the SERVER-side payment/delivery
 * configuration the user wants to edit from the dashboard: the buy-for-myself Till route,
 * the buy-for-another Paybill route, the fulfilment SMS phone/business name, and the SMS
 * provider (key encrypted at rest). Every save requires re-authentication and is audited.
 *
 * These values are NOT app configuration and are never synced to the app. The live
 * payment endpoints read them only after the documented gateway bridge is enabled
 * (see cutover/gateway_bridge.php) — until then this page is the management surface.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Rbac;
use App\Core\Request;
use App\Core\Validator;
use App\Services\GatewayService;

final class GatewayController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAuth();
        Rbac::requireSuperAdmin();
        $this->view('gateway/index', [
            'activeNav' => 'support', 'pageTitle' => 'Payment gateway',
            'g' => GatewayService::row(),
            'hasSmsKey' => GatewayService::hasSmsKey(),
        ]);
    }

    public function save(Request $request): void
    {
        Csrf::check($request);
        $this->requireAuth();
        Rbac::requireSuperAdmin();

        // Payment-route change → re-authentication.
        if (!Auth::reauthenticate((string) $request->post('reauth_password', ''), (string) $request->post('reauth_totp', ''))) {
            Flash::error('Re-authentication failed. Gateway settings were not changed.');
            $this->redirect('/gateway');
        }

        $data = [
            'daraja_env' => in_array($request->post('daraja_env'), ['sandbox', 'production'], true) ? (string) $request->post('daraja_env') : 'production',
            'self_transaction_type' => in_array($request->post('self_transaction_type'), ['CustomerBuyGoodsOnline', 'CustomerPayBillOnline'], true) ? (string) $request->post('self_transaction_type') : 'CustomerBuyGoodsOnline',
            'business_shortcode' => trim((string) $request->post('business_shortcode', '')),
            'party_b' => trim((string) $request->post('party_b', '')),
            'paybill_shortcode' => trim((string) $request->post('paybill_shortcode', '')),
            'callback_url' => trim((string) $request->post('callback_url', '')),
            'fulfilment_phone' => $this->normalisePhone((string) $request->post('fulfilment_phone', '')),
            'business_name' => trim((string) $request->post('business_name', 'MyBingwa')),
            'sms_api_url' => trim((string) $request->post('sms_api_url', '')),
            'sms_sender_id' => trim((string) $request->post('sms_sender_id', '')),
        ];

        $v = Validator::make($data);
        $v->validate([
            'business_shortcode' => 'max:16',
            'party_b' => 'max:16',
            'paybill_shortcode' => 'max:16',
            'fulfilment_phone' => 'msisdn|max:20',
            'callback_url' => 'max:200',
        ]);
        // Paybill STK requires BusinessShortCode == PartyB; Buy Goods needs both present.
        if ($data['self_transaction_type'] === 'CustomerPayBillOnline'
            && $data['business_shortcode'] !== '' && $data['party_b'] !== ''
            && $data['business_shortcode'] !== $data['party_b']) {
            $v->add('self_transaction_type', 'For a Paybill (CustomerPayBillOnline) the shortcode and PartyB must be the SAME paybill number.');
        }
        if ($v->fails()) {
            Flash::error(implode(' ', array_values($v->firstErrors())));
            $this->redirect('/gateway');
        }

        // SMS key: blank submission keeps the stored key; "__CLEAR__" clears it.
        $smsRaw = (string) $request->post('sms_api_key', '');
        $smsApiKey = null;
        if ($smsRaw === '__CLEAR__') {
            $smsApiKey = '';
        } elseif ($smsRaw !== '') {
            $smsApiKey = $smsRaw;
        }

        GatewayService::save($data, $smsApiKey);
        Audit::log([
            'action' => 'gateway.update', 'entity_type' => 'gateway_config', 'entity_id' => 1,
            'after' => array_merge($data, ['sms_api_key' => $smsApiKey === null ? '(unchanged)' : '••••••']),
            'reason' => 'Payment gateway change',
        ]);
        Flash::success('Payment gateway settings saved. If the gateway bridge is enabled on the payment API, they are live immediately.');
        $this->redirect('/gateway');
    }

    private function normalisePhone(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw);
        if ($d === '') {
            return '';
        }
        // Accept 07XXXXXXXX / 2547XXXXXXXX / 7XXXXXXXX → store as 07XXXXXXXX.
        if (strpos($d, '254') === 0) {
            $d = '0' . substr($d, 3);
        } elseif (strlen($d) === 9 && ($d[0] === '7' || $d[0] === '1')) {
            $d = '0' . $d;
        }
        return $d;
    }
}
