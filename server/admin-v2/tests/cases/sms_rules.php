<?php
/**
 * SMS rule engine — pure matching logic.
 *
 * Everything here runs without a database on purpose: SmsRuleEngine::match() and
 * ::evaluate() must stay pure so the admin panel, these tests and the Android app all
 * reach the same conclusion about a message. Only patternTypes()/eventTypes() touch the
 * catalogue tables, and nothing below calls them.
 *
 * Required by tests/run.php, which already defines test(), ok() and eq().
 */

/** Build a rule row with sensible defaults so each test states only what it cares about. */
function sms_rule(array $overrides = []): array
{
    return array_merge([
        'rule_key'         => 'r1',
        'name'             => 'Rule 1',
        'sender_id'        => 'Safaricom',
        'pattern_type'     => 'contains',
        'pattern'          => 'received',
        'case_sensitive'   => 0,
        'event_type'       => 'DATA_RECEIVED',
        'secondary_events' => '',
        'captures_json'    => null,
        'priority'         => 100,
        'enabled'          => 1,
    ], $overrides);
}

/* ---------------------------------------------------------------- pattern semantics */

test('contains matches anywhere in the message', function () {
    $rule = sms_rule(['pattern_type' => 'contains', 'pattern' => 'Bingwa Sokoni']);
    ok(\App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'You have received Sh20=250MB 24hr from Bingwa Sokoni.')['matched']);
    ok(!\App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'You have received 20 SMS Daily SMS Bundle.')['matched']);
});

test('contains ignores letter case unless case_sensitive is set', function () {
    $loose = sms_rule(['pattern_type' => 'contains', 'pattern' => 'BINGWA', 'case_sensitive' => 0]);
    $strict = sms_rule(['pattern_type' => 'contains', 'pattern' => 'BINGWA', 'case_sensitive' => 1]);
    ok(\App\Services\SmsRuleEngine::match($loose, 'Safaricom', 'from Bingwa Sokoni')['matched']);
    ok(!\App\Services\SmsRuleEngine::match($strict, 'Safaricom', 'from Bingwa Sokoni')['matched']);
    ok(\App\Services\SmsRuleEngine::match($strict, 'Safaricom', 'from BINGWA Sokoni')['matched']);
});

test('starts_with ignores leading whitespace', function () {
    $rule = sms_rule(['pattern_type' => 'starts_with', 'pattern' => 'Dear customer']);
    ok(\App\Services\SmsRuleEngine::match($rule, 'Safaricom', "   Dear customer, your balance is low.")['matched']);
    ok(!\App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'Hello dear customer')['matched']);
});

test('ends_with matches the tail of the message', function () {
    $rule = sms_rule(['pattern_type' => 'ends_with', 'pattern' => 'Bingwa Sokoni.']);
    ok(\App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'You have received Sh20=250MB 24hr from Bingwa Sokoni.')['matched']);
    ok(!\App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'Bingwa Sokoni. Thank you')['matched']);
});

test('exact matches only the whole trimmed message', function () {
    $rule = sms_rule(['pattern_type' => 'exact', 'pattern' => 'Your bundle has expired']);
    ok(\App\Services\SmsRuleEngine::match($rule, 'Safaricom', "  Your bundle has expired  ")['matched']);
    ok(!\App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'Your bundle has expired today')['matched']);
});

test('keywords split on newlines and on commas alike', function () {
    $byLine = sms_rule(['pattern_type' => 'keywords', 'pattern' => "received\nSMS\nBundle"]);
    $byComma = sms_rule(['pattern_type' => 'keywords', 'pattern' => 'received, SMS, Bundle']);
    $body = 'You have received 20 SMS Daily SMS Bundle.';
    ok(\App\Services\SmsRuleEngine::match($byLine, 'Safaricom', $body)['matched']);
    ok(\App\Services\SmsRuleEngine::match($byComma, 'Safaricom', $body)['matched']);
    eq(\App\Services\SmsRuleEngine::keywords("a\nb, c , ,\n"), ['a', 'b', 'c']);
});

test('keywords require every keyword to appear', function () {
    $rule = sms_rule(['pattern_type' => 'keywords', 'pattern' => "received, Minutes"]);
    $res = \App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'You have received 20 SMS Daily SMS Bundle.');
    ok(!$res['matched']);
    ok(strpos($res['reason'], 'Minutes') !== false, 'the reason names the missing keyword');
});

test('keyword reason reads as a plain sentence', function () {
    $rule = sms_rule(['pattern_type' => 'keywords', 'pattern' => "received, SMS, Bundle"]);
    eq(
        \App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'You have received 20 SMS Daily SMS Bundle.')['reason'],
        'Sender matched and all 3 keywords were found.'
    );
});

test('regex matches and extracts named captures', function () {
    $rule = sms_rule([
        'pattern_type'  => 'regex',
        'pattern'       => 'received\s+Sh(\d+)\s*=\s*([\d.]+\s*(?:MB|GB))',
        'captures_json' => '{"amount":1,"allowance":2}',
    ]);
    $res = \App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'You have received Sh20=250MB 24hr from Bingwa Sokoni.');
    ok($res['matched']);
    eq($res['captures']['amount'], '20');
    eq($res['captures']['allowance'], '250MB');
});

test('a rule that does not match extracts nothing', function () {
    $rule = sms_rule([
        'pattern_type'  => 'regex',
        'pattern'       => 'received\s+Sh(\d+)',
        'captures_json' => '{"amount":1}',
    ]);
    $res = \App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'Your call could not be completed.');
    ok(!$res['matched']);
    eq($res['captures'], []);
});

test('the message body is capped at 1000 characters', function () {
    $rule = sms_rule(['pattern_type' => 'contains', 'pattern' => 'NEEDLE']);
    $body = str_repeat('a', 1000) . ' NEEDLE';
    ok(!\App\Services\SmsRuleEngine::match($rule, 'Safaricom', $body)['matched']);
    ok(\App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'NEEDLE ' . $body)['matched']);
});

/* ------------------------------------------------------------------------- senders */

test('an empty sender_id matches any sender', function () {
    $rule = sms_rule(['sender_id' => '', 'pattern_type' => 'contains', 'pattern' => 'received']);
    ok(\App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'You have received 250MB')['matched']);
    ok(\App\Services\SmsRuleEngine::match($rule, 'SAF_Balance', 'You have received 250MB')['matched']);
    ok(\App\Services\SmsRuleEngine::match($rule, '', 'You have received 250MB')['matched']);
});

test('sender comparison trims and ignores case', function () {
    $rule = sms_rule(['sender_id' => 'Safaricom']);
    ok(\App\Services\SmsRuleEngine::match($rule, '  safaricom ', 'You have received 250MB')['senderMatched']);
    ok(\App\Services\SmsRuleEngine::match($rule, 'SAFARICOM', 'You have received 250MB')['matched']);
});

test('a wrong sender fails with a sentence naming both senders', function () {
    $rule = sms_rule(['sender_id' => 'Safaricom']);
    $res = \App\Services\SmsRuleEngine::match($rule, 'SAF_Balance', 'You have received 250MB');
    ok(!$res['matched']);
    ok(!$res['senderMatched']);
    eq($res['reason'], 'Sender is SAF_Balance but this rule only accepts Safaricom.');
});

/* ------------------------------------------------------------------ rule validation */

test('validatePattern rejects an empty non-regex pattern', function () {
    ok(!\App\Services\SmsRuleEngine::validatePattern('contains', '   ')['ok']);
    ok(!\App\Services\SmsRuleEngine::validatePattern('keywords', " ,\n, ")['ok']);
});

test('validatePattern reuses the regex ReDoS guard', function () {
    ok(!\App\Services\SmsRuleEngine::validatePattern('regex', '(a+)+')['ok']);
    ok(\App\Services\SmsRuleEngine::validatePattern('regex', 'received\s+\d+\s*(?:MB|GB)')['ok']);
});

test('validatePattern rejects a pattern type the matcher cannot run', function () {
    ok(!\App\Services\SmsRuleEngine::validatePattern('telepathy', 'anything')['ok']);
});

test('a rule with an unusable pattern never matches and says why', function () {
    $rule = sms_rule(['pattern_type' => 'regex', 'pattern' => '(a+)+']);
    $res = \App\Services\SmsRuleEngine::match($rule, 'Safaricom', 'aaaaaaaaaa');
    ok(!$res['matched']);
    ok($res['error'] !== null);
    ok(strpos($res['reason'], 'cannot run') !== false);
});

/* --------------------------------------------------------------------- evaluation */

test('evaluate lets the highest priority rule win', function () {
    $rules = [
        sms_rule(['rule_key' => 'general', 'priority' => 100, 'pattern' => 'received', 'event_type' => 'DATA_RECEIVED']),
        sms_rule(['rule_key' => 'specific', 'priority' => 300, 'pattern' => 'Bingwa Sokoni', 'event_type' => 'GIFT_RECEIVED']),
    ];
    $out = \App\Services\SmsRuleEngine::evaluate($rules, 'Safaricom', 'You have received Sh20=250MB from Bingwa Sokoni.');
    eq($out['winner']['rule_key'], 'specific');
    eq($out['events'], ['GIFT_RECEIVED']);
    eq(count($out['candidates']), 2);
    eq($out['candidates'][0]['rule']['rule_key'], 'specific', 'candidates are ordered strongest first');
});

test('evaluate breaks a priority tie on rule_key ascending', function () {
    $rules = [
        sms_rule(['rule_key' => 'bbb', 'priority' => 200]),
        sms_rule(['rule_key' => 'aaa', 'priority' => 200]),
    ];
    $out = \App\Services\SmsRuleEngine::evaluate($rules, 'Safaricom', 'You have received 250MB');
    eq($out['winner']['rule_key'], 'aaa');
});

test('a disabled rule is reported but can never win', function () {
    $rules = [
        sms_rule(['rule_key' => 'off', 'priority' => 900, 'enabled' => 0]),
        sms_rule(['rule_key' => 'on', 'priority' => 100, 'enabled' => 1]),
    ];
    $out = \App\Services\SmsRuleEngine::evaluate($rules, 'Safaricom', 'You have received 250MB');
    eq($out['winner']['rule_key'], 'on');
    eq(count($out['candidates']), 2);
    ok($out['candidates'][0]['result']['matched'], 'the disabled rule still reports that it matched');
});

test('evaluate returns the winning rule captures', function () {
    $rules = [
        sms_rule([
            'rule_key'      => 'data',
            'pattern_type'  => 'regex',
            'pattern'       => 'received\s+(\d+)\s*MB',
            'captures_json' => '{"allowance":1}',
            'priority'      => 500,
        ]),
        sms_rule(['rule_key' => 'plain', 'priority' => 10]),
    ];
    $out = \App\Services\SmsRuleEngine::evaluate($rules, 'Safaricom', 'You have received 250MB today');
    eq($out['winner']['rule_key'], 'data');
    eq($out['captures'], ['allowance' => '250']);
});

test('an unrecognised message matches nothing', function () {
    $rules = [
        sms_rule(['rule_key' => 'data', 'pattern' => 'received']),
        sms_rule(['rule_key' => 'balance', 'sender_id' => 'SAF_Balance', 'pattern' => 'balance is']),
    ];
    $out = \App\Services\SmsRuleEngine::evaluate($rules, 'Safaricom', 'Karibu! Dial *100# for help.');
    eq($out['winner'], null);
    eq($out['events'], []);
    eq($out['captures'], []);
    foreach ($out['candidates'] as $candidate) {
        ok(!$candidate['result']['matched']);
        ok($candidate['result']['reason'] !== '', 'every candidate explains itself');
    }
});

test('evaluate on no rules is safe', function () {
    $out = \App\Services\SmsRuleEngine::evaluate([], 'Safaricom', 'anything');
    eq($out['winner'], null);
    eq($out['candidates'], []);
});

/* -------------------------------------------------------------------------- events */

test('eventsFor returns the primary event then the secondary ones', function () {
    $rule = sms_rule(['event_type' => 'MINUTES_RECEIVED', 'secondary_events' => 'SMS_RECEIVED, DATA_RECEIVED']);
    eq(\App\Services\SmsRuleEngine::eventsFor($rule), ['MINUTES_RECEIVED', 'SMS_RECEIVED', 'DATA_RECEIVED']);
});

test('eventsFor drops blanks and duplicates', function () {
    $rule = sms_rule(['event_type' => 'LOW_DATA', 'secondary_events' => ' , LOW_DATA ,, LOW_SMS,']);
    eq(\App\Services\SmsRuleEngine::eventsFor($rule), ['LOW_DATA', 'LOW_SMS']);
});
