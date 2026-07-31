<?php
/**
 * Pure-logic tests for the notification rule engine (App\Services\NotificationService).
 *
 * Only the database-free half is covered here: wording substitution, token safety,
 * schedule validation, the "is it live right now?" window check and the plain-English
 * schedule summary. Required by tests/run.php, which defines test()/ok()/eq().
 *
 * Every moment is constructed with an explicit Africa/Nairobi timezone so the result
 * never depends on the machine's clock settings.
 */

/** Africa/Nairobi moment helper. */
$nb = static function (string $when): DateTimeImmutable {
    return new DateTimeImmutable($when, new DateTimeZone('Africa/Nairobi'));
};

/** The catalogue shape variables() returns: var_key => row. */
$catalogue = [
    'first_name'        => ['var_key' => 'first_name', 'sample_value' => 'James'],
    'bundle_name'       => ['var_key' => 'bundle_name', 'sample_value' => '2GB'],
    'recommended_offer' => ['var_key' => 'recommended_offer', 'sample_value' => '1GB @ KSh 19'],
];

/* ------------------------------------------------------------------ render */

test('render substitutes known variables', function () {
    eq(
        \App\Services\NotificationService::render('Hi {{first_name}}, {{bundle_name}} is ready.', ['first_name' => 'James', 'bundle_name' => '2GB']),
        'Hi James, 2GB is ready.'
    );
});

test('render tolerates spacing inside the braces', function () {
    eq(\App\Services\NotificationService::render('Hi {{ first_name }}!', ['first_name' => 'Ann']), 'Hi Ann!');
});

test('render leaves an unknown token intact for the device to resolve', function () {
    eq(
        \App\Services\NotificationService::render('Hi {{first_name}}, you have {{remaining_data}} left.', ['first_name' => 'James']),
        'Hi James, you have {{remaining_data}} left.'
    );
});

test('render leaves text without tokens untouched', function () {
    eq(\App\Services\NotificationService::render('Nothing to replace here.', ['first_name' => 'James']), 'Nothing to replace here.');
});

/* -------------------------------------------------------- unsupportedTokens */

test('unsupportedTokens reports only tokens outside the catalogue', function () use ($catalogue) {
    eq(\App\Services\NotificationService::unsupportedTokens('{{first_name}} and {{oops}}', $catalogue), ['oops']);
});

test('unsupportedTokens accepts a plain list of keys', function () {
    eq(\App\Services\NotificationService::unsupportedTokens('{{first_name}} {{nope}}', ['first_name', 'bundle_name']), ['nope']);
});

test('unsupportedTokens de-duplicates a repeated bad token', function () use ($catalogue) {
    eq(\App\Services\NotificationService::unsupportedTokens('{{bad}} then {{bad}} again', $catalogue), ['bad']);
});

test('unsupportedTokens returns nothing for clean copy', function () use ($catalogue) {
    eq(\App\Services\NotificationService::unsupportedTokens('Hi {{first_name}}, try {{recommended_offer}}.', $catalogue), []);
});

/* ---------------------------------------------------------- validateSchedule */

test('validateSchedule accepts an empty always-on window', function () {
    eq(\App\Services\NotificationService::validateSchedule([]), []);
});

test('validateSchedule accepts a complete, sane window', function () {
    eq(\App\Services\NotificationService::validateSchedule([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'days' => [1, 3, 5],
        'allowed_time_start' => '07:00',
        'allowed_time_end' => '21:00',
        'cooldown_minutes' => 180,
    ]), []);
});

test('validateSchedule rejects an end date before the start date', function () {
    $errors = \App\Services\NotificationService::validateSchedule(['starts_on' => '2026-08-10', 'ends_on' => '2026-08-01']);
    eq(count($errors), 1);
    ok(strpos($errors[0], 'end date cannot be before') !== false, $errors[0]);
});

test('validateSchedule allows an end date equal to the start date', function () {
    eq(\App\Services\NotificationService::validateSchedule(['starts_on' => '2026-08-10', 'ends_on' => '2026-08-10']), []);
});

test('validateSchedule rejects a date that is not real', function () {
    ok(count(\App\Services\NotificationService::validateSchedule(['starts_on' => '2026-02-31'])) === 1);
    ok(count(\App\Services\NotificationService::validateSchedule(['ends_on' => 'tomorrow'])) === 1);
});

test('validateSchedule rejects half a time window', function () {
    $errors = \App\Services\NotificationService::validateSchedule(['allowed_time_start' => '07:00', 'allowed_time_end' => '']);
    eq(count($errors), 1);
    ok(strpos($errors[0], 'both a start time and an end time') !== false, $errors[0]);
    ok(count(\App\Services\NotificationService::validateSchedule(['allowed_time_start' => '', 'allowed_time_end' => '21:00'])) === 1);
});

test('validateSchedule accepts no time window at all', function () {
    eq(\App\Services\NotificationService::validateSchedule(['allowed_time_start' => '', 'allowed_time_end' => '']), []);
});

test('validateSchedule rejects a malformed or out-of-range time', function () {
    ok(count(\App\Services\NotificationService::validateSchedule(['allowed_time_start' => '7:00', 'allowed_time_end' => '21:00'])) === 1);
    ok(count(\App\Services\NotificationService::validateSchedule(['allowed_time_start' => '24:00', 'allowed_time_end' => '01:00'])) === 1);
    ok(count(\App\Services\NotificationService::validateSchedule(['allowed_time_start' => '07:60', 'allowed_time_end' => '21:00'])) === 1);
});

test('validateSchedule accepts a window that crosses midnight', function () {
    eq(\App\Services\NotificationService::validateSchedule(['allowed_time_start' => '21:00', 'allowed_time_end' => '06:00']), []);
});

test('validateSchedule rejects a day outside Monday..Sunday', function () {
    ok(count(\App\Services\NotificationService::validateSchedule(['days' => [1, 8]])) === 1);
    ok(count(\App\Services\NotificationService::validateSchedule(['days' => [0]])) === 1);
    ok(count(\App\Services\NotificationService::validateSchedule(['days' => ['x']])) === 1);
    eq(\App\Services\NotificationService::validateSchedule(['days' => [1, 2, 3, 4, 5, 6, 7]]), []);
});

test('validateSchedule reads days from a comma string too', function () {
    eq(\App\Services\NotificationService::validateSchedule(['days' => '1,2,5']), []);
    ok(count(\App\Services\NotificationService::validateSchedule(['days' => '1,9'])) === 1);
});

test('validateSchedule bounds the rest period to 0..10080 minutes', function () {
    eq(\App\Services\NotificationService::validateSchedule(['cooldown_minutes' => 0]), []);
    eq(\App\Services\NotificationService::validateSchedule(['cooldown_minutes' => 10080]), []);
    ok(count(\App\Services\NotificationService::validateSchedule(['cooldown_minutes' => 10081])) === 1);
    ok(count(\App\Services\NotificationService::validateSchedule(['cooldown_minutes' => -1])) === 1);
    ok(count(\App\Services\NotificationService::validateSchedule(['cooldown_minutes' => 'soon'])) === 1);
    eq(\App\Services\NotificationService::validateSchedule(['cooldown_minutes' => '']), []);
});

test('validateSchedule collects several problems at once', function () {
    $errors = \App\Services\NotificationService::validateSchedule([
        'starts_on' => '2026-08-10',
        'ends_on' => '2026-08-01',
        'allowed_time_start' => '07:00',
        'allowed_time_end' => '',
        'cooldown_minutes' => 99999,
    ]);
    eq(count($errors), 3);
});

/* ------------------------------------------------------------ isWithinWindow */

test('isWithinWindow is true when nothing is restricted', function () use ($nb) {
    ok(\App\Services\NotificationService::isWithinWindow([], $nb('2026-08-05 12:00')));
});

test('isWithinWindow respects the date range', function () use ($nb) {
    $rule = ['starts_on' => '2026-08-01', 'ends_on' => '2026-08-31'];
    ok(!\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-07-31 23:59')), 'day before the start');
    ok(\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-01 00:00')), 'first day is inclusive');
    ok(\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-31 23:59')), 'last day is inclusive');
    ok(!\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-09-01 00:00')), 'day after the end');
});

test('isWithinWindow handles an open-ended range', function () use ($nb) {
    ok(\App\Services\NotificationService::isWithinWindow(['starts_on' => '2026-08-01', 'ends_on' => null], $nb('2030-01-01 09:00')));
    ok(\App\Services\NotificationService::isWithinWindow(['starts_on' => null, 'ends_on' => '2026-08-31'], $nb('2020-01-01 09:00')));
});

test('isWithinWindow respects the chosen weekdays', function () use ($nb) {
    $wednesday = $nb('2026-08-05 12:00');
    eq($wednesday->format('N'), '3', 'fixture sanity: 5 Aug 2026 is a Wednesday');
    ok(\App\Services\NotificationService::isWithinWindow(['days_of_week' => '1,3,5'], $wednesday), 'Wed is in Mon/Wed/Fri');
    ok(!\App\Services\NotificationService::isWithinWindow(['days_of_week' => '2,4'], $wednesday), 'Wed is not in Tue/Thu');
    ok(\App\Services\NotificationService::isWithinWindow(['days_of_week' => ''], $wednesday), 'empty means every day');
    ok(\App\Services\NotificationService::isWithinWindow(['days_of_week' => '1,2,3,4,5'], $wednesday), 'weekdays include Wed');
});

test('isWithinWindow treats Sunday as day 7', function () use ($nb) {
    $sunday = $nb('2026-08-09 12:00');
    eq($sunday->format('N'), '7', 'fixture sanity: 9 Aug 2026 is a Sunday');
    ok(\App\Services\NotificationService::isWithinWindow(['days_of_week' => '6,7'], $sunday));
    ok(!\App\Services\NotificationService::isWithinWindow(['days_of_week' => '1,2,3,4,5'], $sunday));
});

test('isWithinWindow respects a same-day time window, inclusively', function () use ($nb) {
    $rule = ['allowed_time_start' => '07:00', 'allowed_time_end' => '21:00'];
    ok(!\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 06:59')));
    ok(\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 07:00')), 'start is inclusive');
    ok(\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 12:30')));
    ok(\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 21:00')), 'end is inclusive');
    ok(!\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 21:01')));
});

test('isWithinWindow handles a window that crosses midnight', function () use ($nb) {
    $rule = ['allowed_time_start' => '21:00', 'allowed_time_end' => '06:00'];
    ok(\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 22:30')), 'late evening');
    ok(\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 05:00')), 'early morning');
    ok(!\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 12:00')), 'midday is outside');
});

test('isWithinWindow ignores an incomplete time window', function () use ($nb) {
    ok(\App\Services\NotificationService::isWithinWindow(['allowed_time_start' => '07:00', 'allowed_time_end' => ''], $nb('2026-08-05 03:00')));
    ok(\App\Services\NotificationService::isWithinWindow(['allowed_time_start' => 'bad', 'allowed_time_end' => 'worse'], $nb('2026-08-05 03:00')));
});

test('isWithinWindow needs every condition to pass together', function () use ($nb) {
    $rule = [
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'days_of_week' => '1,2,3,4,5',
        'allowed_time_start' => '07:00',
        'allowed_time_end' => '21:00',
    ];
    ok(\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 08:00')), 'Wednesday morning inside the range');
    ok(!\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-09 08:00')), 'Sunday is excluded by the weekday list');
    ok(!\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-08-05 22:00')), 'outside the time window');
    ok(!\App\Services\NotificationService::isWithinWindow($rule, $nb('2026-09-02 08:00')), 'after the end date');
});

/* ----------------------------------------------------------- describeSchedule */

test('describeSchedule summarises a weekday daytime campaign', function () {
    eq(\App\Services\NotificationService::describeSchedule([
        'days_of_week' => '1,2,3,4,5',
        'allowed_time_start' => '07:00',
        'allowed_time_end' => '21:00',
        'starts_on' => '2026-08-01',
        'ends_on' => null,
    ]), 'Weekdays, 07:00-21:00, from 1 Aug 2026');
});

test('describeSchedule summarises an always-on rule', function () {
    eq(\App\Services\NotificationService::describeSchedule([
        'days_of_week' => '',
        'allowed_time_start' => '',
        'allowed_time_end' => '',
        'starts_on' => null,
        'ends_on' => null,
    ]), 'Every day, any time');
});

test('describeSchedule summarises a weekend rule with both dates', function () {
    eq(\App\Services\NotificationService::describeSchedule([
        'days_of_week' => '6,7',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
    ]), 'Weekends, any time, from 1 Aug 2026 to 31 Aug 2026');
});

test('describeSchedule names individual days and an end-only date', function () {
    eq(\App\Services\NotificationService::describeSchedule([
        'days_of_week' => '5,1,3',
        'allowed_time_start' => '18:00',
        'allowed_time_end' => '20:30',
        'ends_on' => '2026-12-25',
    ]), 'Mon, Wed, Fri, 18:00-20:30, until 25 Dec 2026');
});

test('describeSchedule calls all seven days "Every day"', function () {
    eq(\App\Services\NotificationService::describeSchedule(['days_of_week' => '1,2,3,4,5,6,7']), 'Every day, any time');
});

/* ------------------------------------------------------------------ dayList */

test('dayList sorts, de-duplicates and drops anything outside 1..7', function () {
    eq(\App\Services\NotificationService::dayList('5,1,1,9,0,3'), [1, 3, 5]);
    eq(\App\Services\NotificationService::dayList([7, 6]), [6, 7]);
    eq(\App\Services\NotificationService::dayList(''), []);
    eq(\App\Services\NotificationService::dayList(null), []);
});
