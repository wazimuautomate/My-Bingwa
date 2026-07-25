<?php
/**
 * Payment/delivery gateway settings that drive the PAYMENT SERVER (stk.php etc.).
 * These are operational server settings, NOT app configuration: they are never included
 * in the app sync snapshot. The SMS API key is stored encrypted at rest.
 *
 * The legacy payment API can read these via cutover/gateway_bridge.php.
 */

namespace App\Services;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Database;

final class GatewayService
{
    public static function ensureRow(): void
    {
        $t = Database::table('gateway_config');
        if (!Database::fetch("SELECT id FROM {$t} WHERE id = 1")) {
            Database::run(
                "INSERT INTO {$t} (id, updated_at, updated_by) VALUES (1, UTC_TIMESTAMP(), 'system')"
            );
        }
    }

    /** Raw row (sms key still encrypted). */
    public static function row(): array
    {
        self::ensureRow();
        return Database::fetch('SELECT * FROM ' . Database::table('gateway_config') . ' WHERE id = 1') ?: [];
    }

    /** Resolved values with the SMS key decrypted — used by the legacy payment bridge. */
    public static function resolved(): array
    {
        $r = self::row();
        return [
            'daraja_env'        => $r['daraja_env'] ?? 'production',
            'transaction_type'  => $r['self_transaction_type'] ?? 'CustomerBuyGoodsOnline',
            'business_shortcode'=> $r['business_shortcode'] ?? '',
            'party_b'           => $r['party_b'] ?? '',
            'paybill_shortcode' => $r['paybill_shortcode'] ?? '',
            'callback_url'      => $r['callback_url'] ?? '',
            'fulfilment_phone'  => $r['fulfilment_phone'] ?? '',
            'business_name'     => $r['business_name'] ?? 'MyBingwa',
            'sms_api_url'       => $r['sms_api_url'] ?? '',
            'sms_sender_id'     => $r['sms_sender_id'] ?? '',
            'sms_api_key'       => Crypto::decrypt((string) ($r['sms_api_key_enc'] ?? '')),
        ];
    }

    /** True if an SMS API key is stored (without revealing it). */
    public static function hasSmsKey(): bool
    {
        return trim((string) (self::row()['sms_api_key_enc'] ?? '')) !== '';
    }

    /**
     * Save. $smsApiKey === null keeps the existing stored key; a non-empty string
     * replaces it; an empty string clears it.
     */
    public static function save(array $data, ?string $smsApiKey): void
    {
        self::ensureRow();
        $t = Database::table('gateway_config');
        $smsEnc = null;
        $updateSms = false;
        if ($smsApiKey !== null) {
            $updateSms = true;
            $smsEnc = $smsApiKey === '' ? '' : Crypto::encrypt($smsApiKey);
        }
        $sql = "UPDATE {$t} SET
                    daraja_env=?, self_transaction_type=?, business_shortcode=?, party_b=?, paybill_shortcode=?,
                    callback_url=?, fulfilment_phone=?, business_name=?, sms_api_url=?, sms_sender_id=?"
             . ($updateSms ? ', sms_api_key_enc=?' : '')
             . ", row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = 1";
        $params = [
            $data['daraja_env'], $data['self_transaction_type'], $data['business_shortcode'], $data['party_b'],
            $data['paybill_shortcode'], $data['callback_url'], $data['fulfilment_phone'], $data['business_name'],
            $data['sms_api_url'], $data['sms_sender_id'],
        ];
        if ($updateSms) {
            $params[] = $smsEnc;
        }
        $params[] = Auth::user()['name'] ?? 'system';
        Database::run($sql, $params);
    }
}
