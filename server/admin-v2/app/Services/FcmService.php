<?php
/**
 * Firebase Cloud Messaging (FCM HTTP v1) Service.
 *
 * Authenticates with Google Cloud OAuth2 using a Service Account JSON private key
 * (RS256 JWT assertion), exchanges it for a short-lived access token, and calls the
 * FCM v1 REST endpoint:
 *   POST https://fcm.googleapis.com/v1/projects/{project_id}/messages:send
 *
 * Zero external Composer dependencies required (uses built-in OpenSSL & cURL).
 */

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Throwable;

final class FcmService
{
    private static ?string $cachedAccessToken = null;
    private static int $tokenExpiresAt = 0;

    /**
     * Locate and decode the Firebase Service Account JSON credentials.
     */
    public static function loadCredentials(): ?array
    {
        $customPath = Config::get('fcm.service_account_file', '');
        $candidates = [
            $customPath,
            dirname(__DIR__, 2) . '/config/firebase-service-account.json',
            dirname(__DIR__, 3) . '/my-bingwa-b538e0f6c645.json',
            dirname(__DIR__, 4) . '/my-bingwa-b538e0f6c645.json',
            '/home/' . get_current_user() . '/my-bingwa-b538e0f6c645.json',
        ];

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path) && is_readable($path)) {
                $data = json_decode((string) file_get_contents($path), true);
                if (is_array($data) && !empty($data['private_key']) && !empty($data['client_email'])) {
                    return $data;
                }
            }
        }

        return null;
    }

    /**
     * Check if FCM service account credentials are valid and available.
     */
    public static function isConfigured(): bool
    {
        return self::loadCredentials() !== null;
    }

    /**
     * Base64URL encoding helper.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Acquire a Google OAuth2 access token for the firebase.messaging scope.
     */
    public static function getAccessToken(): ?string
    {
        $now = time();
        if (self::$cachedAccessToken !== null && self::$tokenExpiresAt > ($now + 120)) {
            return self::$cachedAccessToken;
        }

        $credentials = self::loadCredentials();
        if ($credentials === null) {
            return null;
        }

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss'   => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]);

        $encodedHeader = self::base64UrlEncode((string) $header);
        $encodedPayload = self::base64UrlEncode((string) $payload);
        $signingInput = $encodedHeader . '.' . $encodedPayload;

        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if (!$privateKey) {
            return null;
        }

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $privateKey, 'SHA256')) {
            return null;
        }

        $jwt = $signingInput . '.' . self::base64UrlEncode($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($response)) {
            return null;
        }

        $tokenData = json_decode($response, true);
        if (!is_array($tokenData) || empty($tokenData['access_token'])) {
            return null;
        }

        self::$cachedAccessToken = (string) $tokenData['access_token'];
        self::$tokenExpiresAt = $now + (int) ($tokenData['expires_in'] ?? 3600);

        return self::$cachedAccessToken;
    }

    /**
     * Send a push message to a specific device token.
     */
    public static function sendToToken(string $token, string $title, string $body, string $route = 'notifications'): bool
    {
        $credentials = self::loadCredentials();
        if ($credentials === null) return false;

        $projectId = (string) ($credentials['project_id'] ?? 'my-bingwa');
        $accessToken = self::getAccessToken();
        if ($accessToken === null) return false;

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $messagePayload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => [
                    'title' => $title,
                    'body'  => $body,
                    'route' => $route,
                ],
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'news_channel',
                        'sound'      => 'default',
                        'color'      => '#00C853',
                    ],
                ],
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($messagePayload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Broadcast a push notification to all registered customers with FCM tokens.
     *
     * @return array{success: bool, total_targets: int, sent_count: int, failed_count: int, error: ?string}
     */
    public static function broadcast(string $title, string $body, string $route = 'notifications', string $createdBy = 'admin'): array
    {
        if (!self::isConfigured()) {
            return [
                'success'       => false,
                'total_targets' => 0,
                'sent_count'    => 0,
                'failed_count'  => 0,
                'error'         => 'Firebase Service Account credentials not found on server.',
            ];
        }

        $accessToken = self::getAccessToken();
        if ($accessToken === null) {
            return [
                'success'       => false,
                'total_targets' => 0,
                'sent_count'    => 0,
                'failed_count'  => 0,
                'error'         => 'Failed to authenticate with Google OAuth2 using service account.',
            ];
        }

        // Fetch registered customer tokens from database
        try {
            $table = Database::table('customers');
            $rows = Database::fetchAll("SELECT DISTINCT fcm_token FROM {$table} WHERE fcm_token IS NOT NULL AND fcm_token != ''");
        } catch (Throwable $e) {
            $rows = [];
        }

        $tokens = array_column($rows, 'fcm_token');
        $total = count($tokens);

        if ($total === 0) {
            // Also attempt sending to topic 'all_users' as fallback
            $sent = self::sendToTopic('all_users', $title, $body, $route);
            self::logBroadcast($title, $body, $route, 0, $sent ? 1 : 0, $sent ? 0 : 1, $createdBy);
            return [
                'success'       => $sent,
                'total_targets' => 0,
                'sent_count'    => $sent ? 1 : 0,
                'failed_count'  => $sent ? 0 : 1,
                'error'         => $sent ? null : 'No customer device tokens registered yet.',
            ];
        }

        $success = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            if (self::sendToToken($token, $title, $body, $route)) {
                $success++;
            } else {
                $failed++;
            }
        }

        self::logBroadcast($title, $body, $route, $total, $success, $failed, $createdBy);

        return [
            'success'       => $success > 0,
            'total_targets' => $total,
            'sent_count'    => $success,
            'failed_count'  => $failed,
            'error'         => null,
        ];
    }

    /**
     * Send push notification to a topic (e.g. 'all_users').
     */
    public static function sendToTopic(string $topic, string $title, string $body, string $route = 'notifications'): bool
    {
        $credentials = self::loadCredentials();
        if ($credentials === null) return false;

        $projectId = (string) ($credentials['project_id'] ?? 'my-bingwa');
        $accessToken = self::getAccessToken();
        if ($accessToken === null) return false;

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $messagePayload = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => [
                    'title' => $title,
                    'body'  => $body,
                    'route' => $route,
                ],
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'news_channel',
                        'sound'      => 'default',
                        'color'      => '#00C853',
                    ],
                ],
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($messagePayload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    private static function logBroadcast(
        string $title,
        string $body,
        string $route,
        int $targets,
        int $success,
        int $failed,
        string $createdBy
    ): void {
        try {
            $table = Database::table('push_broadcasts');
            Database::query(
                "INSERT INTO {$table} (title, body, deep_link_route, recipients_count, success_count, failure_count, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [$title, $body, $route, $targets, $success, $failed, $createdBy]
            );
        } catch (Throwable $e) {
            // Non-critical audit logging failure ignored
        }
    }
}
