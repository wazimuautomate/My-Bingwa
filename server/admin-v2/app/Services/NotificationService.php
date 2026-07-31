<?php
/**
 * Notification RULES, not sends.
 *
 * A notification is a rule the offline-first Android app evaluates locally: a category,
 * a trigger, an optional schedule window, a cooldown and several wording variations the
 * app picks from at random. The app substitutes {{variables}} on-device, so no name,
 * number, balance or purchase history ever reaches this server.
 *
 * Categories, triggers and variables live in catalogue tables — adding a new one is an
 * administrator data change, never a code change.
 *
 * Everything except categories()/triggers()/variables() is PURE (no database), so the
 * scheduling and wording logic is unit-testable in tests/cases/notifications.php.
 */

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;

final class NotificationService
{
    /** A {{token}} in wording copy. Keys are catalogue `var_key` values. */
    public const TOKEN_PATTERN = '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/';

    /** A cooldown may never exceed one week. */
    public const MAX_COOLDOWN_MINUTES = 10080;

    public const TIMEZONE = 'Africa/Nairobi';

    /** ISO-8601 day numbers: Monday = 1 … Sunday = 7. */
    public const DAY_NAMES = [
        1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun',
    ];

    /* ------------------------------------------------------------- catalogues */

    /** Enabled notification categories, keyed by category_key. */
    public static function categories(): array
    {
        return self::catalogue('notification_categories', 'category_key');
    }

    /** Enabled trigger types, keyed by trigger_key. */
    public static function triggers(): array
    {
        return self::catalogue('notification_trigger_types', 'trigger_key');
    }

    /** Enabled {{variables}}, keyed by var_key. */
    public static function variables(): array
    {
        return self::catalogue('notification_variables', 'var_key');
    }

    private static function catalogue(string $table, string $keyColumn): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM ' . Database::table($table) . '
              WHERE enabled = 1 ORDER BY sort_order, ' . $keyColumn
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row[$keyColumn]] = $row;
        }
        return $out;
    }

    /* ------------------------------------------------------------- wording (pure) */

    /**
     * Substitute {{var}} using $map (var_key => value).
     *
     * A token that is not in $map is LEFT INTACT: the app resolves the rest on-device
     * from data this server never sees. unsupportedTokens() is what refuses a token
     * nobody can ever resolve.
     */
    public static function render(string $text, array $map): string
    {
        $out = preg_replace_callback(
            self::TOKEN_PATTERN,
            static function (array $m) use ($map) {
                return array_key_exists($m[1], $map) ? (string) $map[$m[1]] : $m[0];
            },
            $text
        );
        return $out === null ? $text : $out;
    }

    /**
     * Tokens used in $text that the notification_variables catalogue does not know.
     *
     * $known accepts either a list of keys (['first_name', …]) or the catalogue map
     * returned by variables() (['first_name' => [row], …]).
     *
     * @return string[] unique, in the order they first appear
     */
    public static function unsupportedTokens(string $text, array $known): array
    {
        $allowed = self::keySet($known);
        preg_match_all(self::TOKEN_PATTERN, $text, $matches);
        $bad = [];
        foreach ($matches[1] as $token) {
            if (!isset($allowed[$token])) {
                $bad[] = $token;
            }
        }
        return array_values(array_unique($bad));
    }

    /** Accept both a key=>row map and a plain list of keys. */
    private static function keySet(array $known): array
    {
        $set = [];
        foreach ($known as $key => $value) {
            if (is_string($key)) {
                $set[$key] = true;
            } elseif (is_string($value)) {
                $set[$value] = true;
            }
        }
        return $set;
    }

    /** Every token used in $text, unique and in order. */
    public static function tokensUsed(string $text): array
    {
        preg_match_all(self::TOKEN_PATTERN, $text, $matches);
        return array_values(array_unique($matches[1]));
    }

    /* ------------------------------------------------------------ schedule (pure) */

    /**
     * Normalise a day selection into a sorted, unique list of 1..7 (Mon = 1).
     * Accepts an array (`days[]` checkboxes) or a comma string ('1,2,5').
     *
     * @param array|string|null $value
     * @return int[]
     */
    public static function dayList($value): array
    {
        $raw = is_array($value) ? $value : explode(',', (string) $value);
        $days = [];
        foreach ($raw as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $day = (int) $item;
            if ($day >= 1 && $day <= 7 && !in_array($day, $days, true)) {
                $days[] = $day;
            }
        }
        sort($days);
        return $days;
    }

    /**
     * Validate a schedule window.
     *
     * Rules: ends_on must not precede starts_on; a time window needs BOTH ends or
     * neither; times must be HH:MM within 00:00-23:59; every chosen day must be 1..7;
     * the cooldown must be 0..10080 minutes.
     *
     * @param array $input starts_on, ends_on, days (array|string), allowed_time_start,
     *                     allowed_time_end, cooldown_minutes
     * @return string[] human error messages; empty means valid
     */
    public static function validateSchedule(array $input): array
    {
        $errors = [];

        $startRaw = trim((string) ($input['starts_on'] ?? ''));
        $endRaw   = trim((string) ($input['ends_on'] ?? ''));
        $startDate = $startRaw !== '' ? self::parseDate($startRaw) : null;
        $endDate   = $endRaw !== '' ? self::parseDate($endRaw) : null;

        if ($startRaw !== '' && $startDate === null) {
            $errors[] = 'The start date is not a real date (use the date picker, e.g. 2026-08-01).';
        }
        if ($endRaw !== '' && $endDate === null) {
            $errors[] = 'The end date is not a real date (use the date picker, e.g. 2026-08-31).';
        }
        if ($startDate !== null && $endDate !== null && $endRaw < $startRaw) {
            $errors[] = 'The end date cannot be before the start date.';
        }

        $timeStart = trim((string) ($input['allowed_time_start'] ?? ''));
        $timeEnd   = trim((string) ($input['allowed_time_end'] ?? ''));
        if ($timeStart !== '' && !self::isTime($timeStart)) {
            $errors[] = 'The start time must look like 07:00 on a 24-hour clock.';
        }
        if ($timeEnd !== '' && !self::isTime($timeEnd)) {
            $errors[] = 'The end time must look like 21:00 on a 24-hour clock.';
        }
        if (($timeStart === '') !== ($timeEnd === '')) {
            $errors[] = 'Set both a start time and an end time, or leave both empty for any time of day.';
        }

        $daysRaw = $input['days'] ?? ($input['days_of_week'] ?? '');
        $dayItems = is_array($daysRaw) ? $daysRaw : explode(',', (string) $daysRaw);
        foreach ($dayItems as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if (preg_match('/^[1-7]$/', $item) !== 1) {
                $errors[] = 'Choose days between Monday (1) and Sunday (7).';
                break;
            }
        }

        $cooldown = trim((string) ($input['cooldown_minutes'] ?? '0'));
        if ($cooldown === '') {
            $cooldown = '0';
        }
        if (preg_match('/^-?\d+$/', $cooldown) !== 1) {
            $errors[] = 'The rest period must be a whole number of minutes.';
        } elseif ((int) $cooldown < 0 || (int) $cooldown > self::MAX_COOLDOWN_MINUTES) {
            $errors[] = 'The rest period must be between 0 and ' . self::MAX_COOLDOWN_MINUTES . ' minutes (7 days).';
        }

        return array_values(array_unique($errors));
    }

    /**
     * Would this rule be ALLOWED to show at the given Africa/Nairobi moment?
     *
     * This answers the admin's "is it live right now?" question only. The device still
     * applies the trigger, cooldown, quiet hours and frequency cap locally — the server
     * never decides that anyone actually sees anything.
     *
     * @param array $rule starts_on, ends_on, days_of_week, allowed_time_start, allowed_time_end
     */
    public static function isWithinWindow(array $rule, DateTimeImmutable $nairobiNow): bool
    {
        $today = $nairobiNow->format('Y-m-d');

        $startsOn = trim((string) ($rule['starts_on'] ?? ''));
        if ($startsOn !== '' && $today < $startsOn) {
            return false;
        }
        $endsOn = trim((string) ($rule['ends_on'] ?? ''));
        if ($endsOn !== '' && $today > $endsOn) {
            return false;
        }

        $days = self::dayList($rule['days_of_week'] ?? ($rule['days'] ?? ''));
        if ($days !== [] && !in_array((int) $nairobiNow->format('N'), $days, true)) {
            return false;
        }

        $timeStart = trim((string) ($rule['allowed_time_start'] ?? ''));
        $timeEnd   = trim((string) ($rule['allowed_time_end'] ?? ''));
        if ($timeStart === '' || $timeEnd === '' || !self::isTime($timeStart) || !self::isTime($timeEnd)) {
            return true; // no usable time window = any time of day
        }

        $now = $nairobiNow->format('H:i');
        if ($timeStart <= $timeEnd) {
            return $now >= $timeStart && $now <= $timeEnd;
        }
        // The window crosses midnight, e.g. 21:00-06:00.
        return $now >= $timeStart || $now <= $timeEnd;
    }

    /**
     * Plain-English summary of a schedule, e.g. "Weekdays, 07:00-21:00, from 1 Aug 2026".
     * Accepts a campaign row or the same keys straight from a form submission.
     */
    public static function describeSchedule(array $row): string
    {
        $parts = [];

        $days = self::dayList($row['days_of_week'] ?? ($row['days'] ?? ''));
        if ($days === [] || count($days) === 7) {
            $parts[] = 'Every day';
        } elseif ($days === [1, 2, 3, 4, 5]) {
            $parts[] = 'Weekdays';
        } elseif ($days === [6, 7]) {
            $parts[] = 'Weekends';
        } else {
            $names = [];
            foreach ($days as $day) {
                $names[] = self::DAY_NAMES[$day];
            }
            $parts[] = implode(', ', $names);
        }

        $timeStart = trim((string) ($row['allowed_time_start'] ?? ''));
        $timeEnd   = trim((string) ($row['allowed_time_end'] ?? ''));
        $parts[] = ($timeStart !== '' && $timeEnd !== '') ? ($timeStart . '-' . $timeEnd) : 'any time';

        $startDate = self::parseDate(trim((string) ($row['starts_on'] ?? '')));
        $endDate   = self::parseDate(trim((string) ($row['ends_on'] ?? '')));
        if ($startDate !== null && $endDate !== null) {
            $parts[] = 'from ' . $startDate->format('j M Y') . ' to ' . $endDate->format('j M Y');
        } elseif ($startDate !== null) {
            $parts[] = 'from ' . $startDate->format('j M Y');
        } elseif ($endDate !== null) {
            $parts[] = 'until ' . $endDate->format('j M Y');
        }

        return implode(', ', $parts);
    }

    /* ---------------------------------------------------------------- helpers */

    /** Strict Y-m-d parse in Africa/Nairobi. Returns null for anything else. */
    public static function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        // MySQL DATE columns arrive as 'Y-m-d'; a DATETIME value would carry a time too.
        $value = substr($value, 0, 10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(self::TIMEZONE));
        if ($date === false) {
            return null;
        }
        return $date->format('Y-m-d') === $value ? $date : null;
    }

    /** True for a zero-padded 24-hour HH:MM between 00:00 and 23:59. */
    public static function isTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }
}
