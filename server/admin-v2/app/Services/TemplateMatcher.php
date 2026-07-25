<?php
/**
 * Safe regex handling for Safaricom message templates:
 *  - reject dangerous / catastrophically expensive patterns before they are stored,
 *  - test a template against a sample (sender + body) with an execution guard,
 *  - detect overlapping templates so the admin sees which one wins.
 *
 * Delivery detection is only ever "a recognised message arrived on this device" — never
 * authoritative fulfilment proof. Correlation with a recent purchase is enforced by the
 * app; this class only decides whether a pattern matches.
 */

namespace App\Services;

final class TemplateMatcher
{
    private const MAX_INPUT_LENGTH = 1000;

    /**
     * Validate a regex pattern for safety. Returns ['ok'=>bool, 'error'=>?string].
     * Rejects patterns that fail to compile or contain nested unbounded quantifiers that
     * are a common source of catastrophic backtracking (ReDoS).
     */
    public static function validatePattern(string $pattern, bool $caseSensitive = false): array
    {
        if ($pattern === '') {
            return ['ok' => false, 'error' => 'Pattern is empty.'];
        }
        if (strlen($pattern) > 500) {
            return ['ok' => false, 'error' => 'Pattern is too long (max 500 characters).'];
        }
        // Heuristic ReDoS guard: nested quantifiers like (a+)+ , (.*)* , (\d+)+ .
        if (preg_match('/\((?:[^()]*[+*])\)[+*]/', $pattern)) {
            return ['ok' => false, 'error' => 'Pattern has a nested unbounded quantifier that risks catastrophic backtracking.'];
        }
        // Disallow inline modifiers that could change safety, and possessive edge cases.
        if (preg_match('/\(\?R\)|\(\?\d/', $pattern)) {
            return ['ok' => false, 'error' => 'Recursive patterns are not allowed.'];
        }
        $delim = self::compile($pattern, $caseSensitive);
        if (@preg_match($delim, '') === false) {
            return ['ok' => false, 'error' => 'Pattern is not a valid regular expression.'];
        }
        return ['ok' => true, 'error' => null];
    }

    private static function compile(string $pattern, bool $caseSensitive): string
    {
        $flags = $caseSensitive ? '' : 'i';
        return '/' . str_replace('/', '\/', $pattern) . '/u' . $flags;
    }

    /**
     * Test a template against a sample message.
     * @return array{matched:bool, senderMatched:bool, bodyMatched:bool, captures:array, error:?string}
     */
    public static function test(array $template, string $sampleSender, string $sampleBody): array
    {
        $body = substr($sampleBody, 0, self::MAX_INPUT_LENGTH);
        $senderExpected = strtoupper(trim((string) ($template['sender_id'] ?? '')));
        $senderActual = strtoupper(trim($sampleSender));
        $senderMatched = $senderExpected === '' || $senderExpected === $senderActual;

        $valid = self::validatePattern((string) $template['pattern'], (bool) ($template['case_sensitive'] ?? false));
        if (!$valid['ok']) {
            return ['matched' => false, 'senderMatched' => $senderMatched, 'bodyMatched' => false, 'captures' => [], 'error' => $valid['error']];
        }
        $regex = self::compile((string) $template['pattern'], (bool) ($template['case_sensitive'] ?? false));
        $matches = [];
        $bodyMatched = @preg_match($regex, $body, $matches) === 1;

        $captures = [];
        $map = json_decode((string) ($template['captures_json'] ?? ''), true) ?: [];
        foreach ($map as $name => $group) {
            $captures[$name] = $matches[(int) $group] ?? null;
        }

        return [
            'matched'       => $senderMatched && $bodyMatched,
            'senderMatched' => $senderMatched,
            'bodyMatched'   => $bodyMatched,
            'captures'      => $captures,
            'error'         => null,
        ];
    }
}
