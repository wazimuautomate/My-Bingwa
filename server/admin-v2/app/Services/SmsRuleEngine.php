<?php
/**
 * The SMS rule engine: how the server decides what a Safaricom message means.
 *
 * The Android app must never hardcode message formats. The server owns editable rules and
 * publishes them; the app evaluates them locally, offline. This class is the reference
 * implementation the admin panel tests against, so an operator can see exactly what the
 * phone will conclude before publishing.
 *
 * Two hard rules shape this file:
 *
 *  1. Event types come from the {p}sms_event_types table, never from PHP. Adding a new
 *     event is a data change. Pattern types come from {p}sms_pattern_types; the semantics
 *     of each pattern type are necessarily code, so a type key the matcher does not
 *     implement is rejected loudly instead of silently never matching.
 *  2. match() and evaluate() are pure — no database, no session, no clock. Only
 *     patternTypes() and eventTypes() read the catalogue tables. That keeps matching
 *     testable without a database and identical to the app's local evaluation.
 *
 * A match only ever means "a recognised message arrived on this device". It is never
 * authoritative proof that a bundle was delivered.
 */

namespace App\Services;

use App\Core\Database;
use Throwable;

final class SmsRuleEngine
{
    /** Messages longer than this are cut before matching (ReDoS / cost guard). */
    public const MAX_BODY_LENGTH = 1000;

    /** Longest pattern text accepted, matching TemplateMatcher's regex cap. */
    private const MAX_PATTERN_LENGTH = 500;

    /**
     * The pattern types this matcher actually implements. This is NOT the catalogue —
     * the catalogue lives in the database and decides what an operator may choose. This
     * list is (a) the fallback shown when the catalogue table is empty or unreadable, and
     * (b) the guard that stops a rule being saved with semantics nothing can evaluate.
     */
    private const SUPPORTED = [
        'regex'       => ['label' => 'Regular expression',  'description' => 'Full PCRE pattern. Capture groups can extract values.',              'sort_order' => 10],
        'contains'    => ['label' => 'Contains',            'description' => 'Matches when the message contains this text anywhere.',              'sort_order' => 20],
        'starts_with' => ['label' => 'Starts with',         'description' => 'Matches when the message begins with this text.',                    'sort_order' => 30],
        'ends_with'   => ['label' => 'Ends with',           'description' => 'Matches when the message ends with this text.',                      'sort_order' => 40],
        'exact'       => ['label' => 'Exact match',         'description' => 'Matches only when the whole message is exactly this text.',          'sort_order' => 50],
        'keywords'    => ['label' => 'Keyword combination', 'description' => 'Every keyword (one per line, or comma separated) must appear.',      'sort_order' => 60],
    ];

    /** @var array<string,array>|null */
    private static ?array $patternTypeCache = null;
    /** @var array<string,array>|null */
    private static ?array $eventTypeCache = null;

    /* ------------------------------------------------------------------ catalogues */

    /**
     * Enabled pattern types, keyed by type_key, in catalogue order.
     * Falls back to the implemented set if the table is empty or unavailable, so the
     * admin form is never an unusable empty dropdown.
     *
     * @return array<string,array>
     */
    public static function patternTypes(): array
    {
        if (self::$patternTypeCache !== null) {
            return self::$patternTypeCache;
        }
        $rows = self::catalogue('sms_pattern_types', 'type_key');
        if ($rows === []) {
            foreach (self::SUPPORTED as $key => $meta) {
                $rows[$key] = [
                    'type_key'    => $key,
                    'label'       => $meta['label'],
                    'description' => $meta['description'],
                    'enabled'     => 1,
                    'sort_order'  => $meta['sort_order'],
                ];
            }
        }
        return self::$patternTypeCache = $rows;
    }

    /**
     * Enabled event types, keyed by event_key, in catalogue order.
     * No PHP fallback on purpose: the event vocabulary is data the operator owns, and
     * inventing one here would quietly re-introduce a hardcoded list.
     *
     * @return array<string,array>
     */
    public static function eventTypes(): array
    {
        if (self::$eventTypeCache !== null) {
            return self::$eventTypeCache;
        }
        return self::$eventTypeCache = self::catalogue('sms_event_types', 'event_key');
    }

    /** Read one catalogue table, keyed by its natural key. Never throws. */
    private static function catalogue(string $table, string $keyColumn): array
    {
        try {
            $rows = Database::fetchAll(
                'SELECT * FROM ' . Database::table($table) . '
                  WHERE enabled = 1 ORDER BY sort_order ASC, ' . $keyColumn . ' ASC'
            );
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $key = (string) ($row[$keyColumn] ?? '');
            if ($key !== '') {
                $out[$key] = $row;
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------------ validation */

    /**
     * Validate a pattern for its type before it is stored.
     * Regex delegates to TemplateMatcher so the ReDoS guard lives in exactly one place.
     *
     * @return array{ok:bool, error:?string}
     */
    public static function validatePattern(string $patternType, string $pattern, bool $caseSensitive = false): array
    {
        $type = trim($patternType);
        if ($type === '') {
            return ['ok' => false, 'error' => 'Choose a pattern type.'];
        }
        if (!isset(self::SUPPORTED[$type])) {
            return ['ok' => false, 'error' => 'The matcher does not understand the pattern type “' . $type . '”.'];
        }
        if ($type === 'regex') {
            return TemplateMatcher::validatePattern($pattern, $caseSensitive);
        }
        if (trim($pattern) === '') {
            return ['ok' => false, 'error' => 'Pattern is empty.'];
        }
        if (mb_strlen($pattern) > self::MAX_PATTERN_LENGTH) {
            return ['ok' => false, 'error' => 'Pattern is too long (max ' . self::MAX_PATTERN_LENGTH . ' characters).'];
        }
        if ($type === 'keywords' && self::keywords($pattern) === []) {
            return ['ok' => false, 'error' => 'Add at least one keyword.'];
        }
        return ['ok' => true, 'error' => null];
    }

    /* --------------------------------------------------------------------- matching */

    /**
     * Test ONE rule against one message. Pure: no database, no clock.
     *
     * `reason` is a short sentence written for a non-developer and is shown verbatim in
     * the rule tester, so it must always explain the outcome — including the sender.
     *
     * @param array $rule   sms_rules row (sender_id, pattern_type, pattern, case_sensitive, captures_json)
     * @return array{matched:bool, senderMatched:bool, bodyMatched:bool, captures:array, reason:string, error:?string}
     */
    public static function match(array $rule, string $sender, string $body): array
    {
        $type          = trim((string) ($rule['pattern_type'] ?? 'regex'));
        $pattern       = (string) ($rule['pattern'] ?? '');
        $caseSensitive = self::truthy($rule['case_sensitive'] ?? 0);
        $wantSender    = trim((string) ($rule['sender_id'] ?? ''));
        $gotSender     = trim($sender);
        $senderMatched = ($wantSender === '' || strtoupper($wantSender) === strtoupper($gotSender));
        $body          = self::clip($body);

        // A rule whose pattern is unsafe or unsupported never matches; say so plainly.
        $valid = self::validatePattern($type, $pattern, $caseSensitive);
        if (!$valid['ok']) {
            return self::result(
                false,
                $senderMatched,
                false,
                [],
                'This rule cannot run: ' . self::lowerFirst((string) $valid['error']),
                $valid['error']
            );
        }

        if (!$senderMatched) {
            return self::result(
                false,
                false,
                false,
                [],
                'Sender is ' . ($gotSender === '' ? 'blank' : $gotSender)
                    . ' but this rule only accepts ' . $wantSender . '.',
                null
            );
        }

        $senderPhrase = $wantSender === '' ? 'This rule accepts any sender' : 'Sender matched';
        $captures     = [];
        $extra        = '';

        switch ($type) {
            case 'regex':
                // Reuse TemplateMatcher: it owns compilation, the ReDoS guard and the
                // capture-group map. Sender was already decided above, so pass none.
                $res = TemplateMatcher::test(
                    [
                        'sender_id'     => '',
                        'pattern'       => $pattern,
                        'case_sensitive' => $caseSensitive,
                        'captures_json' => $rule['captures_json'] ?? '',
                    ],
                    '',
                    $body
                );
                $bodyMatched = (bool) $res['bodyMatched'];
                if ($bodyMatched) {
                    $captures = is_array($res['captures']) ? $res['captures'] : [];
                }
                $phrase = $bodyMatched
                    ? 'the pattern matched the message'
                    : 'the pattern did not match the message';
                if ($bodyMatched && $captures !== []) {
                    $extra = ' Extracted: ' . implode(', ', array_keys($captures)) . '.';
                }
                break;

            case 'contains':
                $needle = trim($pattern);
                $bodyMatched = self::contains($body, $needle, $caseSensitive);
                $phrase = $bodyMatched
                    ? 'the message contains “' . self::snip($needle) . '”'
                    : 'the message does not contain “' . self::snip($needle) . '”';
                break;

            case 'starts_with':
                $needle = trim($pattern);
                $bodyMatched = self::startsWith(ltrim($body), $needle, $caseSensitive);
                $phrase = $bodyMatched
                    ? 'the message begins with “' . self::snip($needle) . '”'
                    : 'the message does not begin with “' . self::snip($needle) . '”';
                break;

            case 'ends_with':
                $needle = trim($pattern);
                $bodyMatched = self::endsWith(rtrim($body), $needle, $caseSensitive);
                $phrase = $bodyMatched
                    ? 'the message ends with “' . self::snip($needle) . '”'
                    : 'the message does not end with “' . self::snip($needle) . '”';
                break;

            case 'exact':
                $needle = trim($pattern);
                $bodyMatched = self::same(trim($body), $needle, $caseSensitive);
                $phrase = $bodyMatched
                    ? 'the whole message is exactly “' . self::snip($needle) . '”'
                    : 'the whole message is not exactly “' . self::snip($needle) . '”';
                break;

            case 'keywords':
            default:
                $keywords = self::keywords($pattern);
                $missing = [];
                foreach ($keywords as $keyword) {
                    if (!self::contains($body, $keyword, $caseSensitive)) {
                        $missing[] = $keyword;
                    }
                }
                $bodyMatched = ($missing === []);
                $total = count($keywords);
                if ($bodyMatched) {
                    $phrase = $total === 1
                        ? 'the keyword “' . self::snip($keywords[0]) . '” was found'
                        : 'all ' . $total . ' keywords were found';
                } else {
                    $phrase = count($missing) . ' of ' . $total . ' '
                        . ($total === 1 ? 'keyword' : 'keywords') . ' '
                        . (count($missing) === 1 ? 'was' : 'were') . ' missing: “'
                        . self::snip(implode('”, “', $missing), 60) . '”';
                }
                break;
        }

        return self::result(
            $bodyMatched,
            true,
            $bodyMatched,
            $captures,
            $senderPhrase . ' and ' . $phrase . '.' . $extra,
            null
        );
    }

    /**
     * Evaluate many rules against one message. Pure: no database.
     *
     * The winner is the highest `priority` that matched; ties break on rule_key ascending
     * so the outcome is stable and reproducible on the phone. A rule that carries an
     * explicit `enabled` of 0 is still reported as a candidate (so the operator can see it
     * would have matched) but can never win.
     *
     * @param array<int,array> $rules
     * @return array{winner:?array, events:string[], captures:array, candidates:array<int,array{rule:array,result:array}>}
     */
    public static function evaluate(array $rules, string $sender, string $body): array
    {
        $candidates = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $candidates[] = ['rule' => $rule, 'result' => self::match($rule, $sender, $body)];
        }

        usort($candidates, static function (array $a, array $b): int {
            $pa = (int) ($a['rule']['priority'] ?? 0);
            $pb = (int) ($b['rule']['priority'] ?? 0);
            if ($pa !== $pb) {
                return $pb <=> $pa; // higher priority first
            }
            return strcmp((string) ($a['rule']['rule_key'] ?? ''), (string) ($b['rule']['rule_key'] ?? ''));
        });

        $winner = null;
        $captures = [];
        foreach ($candidates as $candidate) {
            if (!$candidate['result']['matched']) {
                continue;
            }
            if (array_key_exists('enabled', $candidate['rule']) && !self::truthy($candidate['rule']['enabled'])) {
                continue; // disabled rules are shown, never applied
            }
            $winner = $candidate['rule'];
            $captures = $candidate['result']['captures'];
            break;
        }

        return [
            'winner'     => $winner,
            'events'     => $winner === null ? [] : self::eventsFor($winner),
            'captures'   => $captures,
            'candidates' => $candidates,
        ];
    }

    /**
     * Every event a rule raises: its primary event first, then its secondary events in
     * the order they were written. Duplicates and blanks are dropped.
     *
     * @return string[]
     */
    public static function eventsFor(array $rule): array
    {
        $events = [];
        $primary = trim((string) ($rule['event_type'] ?? ''));
        if ($primary !== '') {
            $events[] = $primary;
        }
        foreach (explode(',', (string) ($rule['secondary_events'] ?? '')) as $event) {
            $event = trim($event);
            if ($event !== '' && !in_array($event, $events, true)) {
                $events[] = $event;
            }
        }
        return $events;
    }

    /**
     * Split a keyword pattern on newlines OR commas, trim each and drop empties.
     * "data, bundle" and "data\nbundle" are the same three-step instruction.
     *
     * @return string[]
     */
    public static function keywords(string $pattern): array
    {
        $parts = preg_split('/[\r\n,]+/', $pattern) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return $out;
    }

    /* ---------------------------------------------------------------------- helpers */

    private static function result(
        bool $matched,
        bool $senderMatched,
        bool $bodyMatched,
        array $captures,
        string $reason,
        ?string $error
    ): array {
        return [
            'matched'       => $matched,
            'senderMatched' => $senderMatched,
            'bodyMatched'   => $bodyMatched,
            'captures'      => $captures,
            'reason'        => $reason,
            'error'         => $error,
        ];
    }

    /** Cut an incoming message to a bounded size before any matching work. */
    private static function clip(string $body): string
    {
        return mb_strlen($body) > self::MAX_BODY_LENGTH
            ? mb_substr($body, 0, self::MAX_BODY_LENGTH)
            : $body;
    }

    private static function contains(string $haystack, string $needle, bool $caseSensitive): bool
    {
        if ($needle === '') {
            return false;
        }
        return $caseSensitive
            ? mb_strpos($haystack, $needle) !== false
            : mb_stripos($haystack, $needle) !== false;
    }

    private static function startsWith(string $haystack, string $needle, bool $caseSensitive): bool
    {
        if ($needle === '') {
            return false;
        }
        $head = mb_substr($haystack, 0, mb_strlen($needle));
        return self::same($head, $needle, $caseSensitive);
    }

    private static function endsWith(string $haystack, string $needle, bool $caseSensitive): bool
    {
        if ($needle === '' || mb_strlen($needle) > mb_strlen($haystack)) {
            return false;
        }
        $tail = mb_substr($haystack, -mb_strlen($needle));
        return self::same($tail, $needle, $caseSensitive);
    }

    private static function same(string $a, string $b, bool $caseSensitive): bool
    {
        return $caseSensitive ? $a === $b : mb_strtolower($a) === mb_strtolower($b);
    }

    /** Accepts 1, '1', true and 'on' — form posts and database rows both arrive here. */
    private static function truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /** Shorten a fragment quoted inside a reason sentence. */
    private static function snip(string $text, int $width = 40): string
    {
        return mb_strlen($text) > $width ? mb_substr($text, 0, $width) . '…' : $text;
    }

    /** Lowercase only the first character, leaving acronyms elsewhere alone. */
    private static function lowerFirst(string $text): string
    {
        return $text === '' ? $text : mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}
