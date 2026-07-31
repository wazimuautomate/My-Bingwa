<?php
/**
 * Publishing / change-detection cases.
 *
 * Pure logic only: no database, no HTTP. diffSnapshots() takes two plain arrays and must
 * stay that way, because the headline guarantee below — identical snapshots produce ZERO
 * diff items — is the thing that stops Preview claiming an offer changed when the operator
 * only opened it and pressed Save.
 *
 * Run with the rest of the suite: php tests/run.php
 */

/* ------------------------------------------------------ the empty-diff guarantee */

test('identical snapshots produce zero diff items', function () {
    eq(\App\Services\PublishingService::diffSnapshots(baseSnapshot(), baseSnapshot()), []);
});

test('re-saving without editing produces no diff (key order is irrelevant)', function () {
    $old = baseSnapshot();
    $new = baseSnapshot();
    // Same values, different key order — what a re-save through a form produces.
    $new['offers'][0] = array_reverse($new['offers'][0], true);
    eq(\App\Services\PublishingService::diffSnapshots($old, $new), []);
});

test('templates.version never registers as a change', function () {
    $old = baseSnapshot();
    $new = baseSnapshot();
    $new['templates']['version'] = 99;   // always tracks the release number
    eq(\App\Services\PublishingService::diffSnapshots($old, $new), []);
});

test('an empty captures object equals an empty captures array', function () {
    // The working snapshot uses stdClass so it serialises as {}; the published snapshot
    // decodes back to []. Those are the same value and must not look like an edit.
    eq(\App\Services\ChangeDetector::compareItems('smsRule', ['captures' => []], ['captures' => new stdClass()]), []);
});

/* --------------------------------------------------------------- single change */

test('one changed field yields exactly one item with exactly one field', function () {
    $old = baseSnapshot();
    $new = baseSnapshot();
    $new['offers'][0]['price'] = 25;

    $items = \App\Services\PublishingService::diffSnapshots($old, $new);
    eq(count($items), 1);
    eq($items[0]['change_type'], 'changed');
    eq($items[0]['module'], 'offers');
    eq($items[0]['entity_type'], 'offer');
    eq($items[0]['entity_id'], 'data_1');
    eq(count($items[0]['fields']), 1);
    eq($items[0]['fields'][0]['field'], 'price');
    eq($items[0]['fields'][0]['label'], 'Price');
    eq($items[0]['fields'][0]['from'], 19);
    eq($items[0]['fields'][0]['to'], 25);
    eq($items[0]['summary'], 'Price updated');
});

/* -------------------------------------------------- added / removed per section */

test('added SMS rule is detected in the smsRules module', function () {
    $old = baseSnapshot();
    $new = baseSnapshot();
    $new['smsRules'][] = ['id' => 'r1', 'name' => 'Bundle received', 'pattern' => 'received'];

    $items = \App\Services\PublishingService::diffSnapshots($old, $new);
    eq(count($items), 1);
    eq($items[0]['change_type'], 'added');
    eq($items[0]['module'], 'smsRules');
    eq($items[0]['entity_type'], 'smsRule');
    eq($items[0]['entity_id'], 'r1');
    eq($items[0]['entity_label'], 'Bundle received');
    eq($items[0]['summary'], 'Added Bundle received');
});

test('removed category is detected in the categories module', function () {
    $old = baseSnapshot();
    $old['categories'][] = ['id' => 'DATA', 'label' => 'Data', 'sortOrder' => 10];
    $new = baseSnapshot();

    $items = \App\Services\PublishingService::diffSnapshots($old, $new);
    eq(count($items), 1);
    eq($items[0]['change_type'], 'removed');
    eq($items[0]['module'], 'categories');
    eq($items[0]['entity_id'], 'DATA');
    eq($items[0]['summary'], 'Removed Data');
});

test('added notification and changed billboard land in their own modules', function () {
    $old = baseSnapshot();
    $old['billboards'][] = ['id' => 7, 'headline' => 'Weekend deal', 'priority' => 5];
    $new = $old;
    $new['billboards'][0]['priority'] = 1;
    $new['notifications'][] = ['id' => 3, 'name' => 'Morning nudge'];

    $items = \App\Services\PublishingService::diffSnapshots($old, $new);
    $modules = array_column($items, 'module');
    sort($modules);
    eq($modules, ['billboards', 'notifications']);

    $grouped = \App\Services\PublishingService::groupByModule($items);
    eq(array_keys($grouped), ['billboards', 'notifications']); // MODULES order, not insertion order
    eq($grouped['billboards']['count'], 1);
    eq($grouped['billboards']['label'], 'Billboards');
});

test('singleton sections diff their own keys', function () {
    $old = baseSnapshot();
    $new = baseSnapshot();
    $new['support']['tillNumber'] = '999999';
    $new['featureFlags'] = ['offline_purchase' => false];
    $old['featureFlags'] = ['offline_purchase' => true];

    $items = \App\Services\PublishingService::diffSnapshots($old, $new);
    eq(count($items), 2);

    $byType = [];
    foreach ($items as $it) { $byType[$it['entity_type']] = $it; }

    ok(isset($byType['support']), 'support change missing');
    eq($byType['support']['module'], 'support');
    eq($byType['support']['entity_id'], 'support');
    eq(count($byType['support']['fields']), 1);
    eq($byType['support']['fields'][0]['field'], 'tillNumber');
    eq($byType['support']['fields'][0]['label'], 'Till number');

    ok(isset($byType['featureFlags']), 'feature flag change missing');
    eq($byType['featureFlags']['fields'][0]['field'], 'offline_purchase');
    eq($byType['featureFlags']['fields'][0]['from'], true);
    eq($byType['featureFlags']['fields'][0]['to'], false);
});

/* --------------------------------------------------------- ChangeDetector detail */

test('compareItems flattens nested keys and compares lists by index', function () {
    $old = ['variations' => [['title' => 'A', 'body' => 'One'], ['title' => 'B', 'body' => 'Two']]];
    $new = ['variations' => [['title' => 'A', 'body' => 'One'], ['title' => 'B', 'body' => 'Three']]];

    $fields = \App\Services\ChangeDetector::compareItems('notification', $old, $new);
    eq(count($fields), 1);
    eq($fields[0]['field'], 'variations.1.body');
    eq($fields[0]['from'], 'Two');
    eq($fields[0]['to'], 'Three');
    ok(strpos($fields[0]['label'], '#2') !== false, 'index should read as #2');
});

test('compareItems reports a longer list by index, not as one blob', function () {
    $fields = \App\Services\ChangeDetector::compareItems('notification', ['daysOfWeek' => [1, 2]], ['daysOfWeek' => [1, 3]]);
    eq(count($fields), 1);
    eq($fields[0]['field'], 'daysOfWeek.1');
    eq($fields[0]['to'], 3);
});

test('fieldLabel humanises unknown fields and uses the map for known ones', function () {
    eq(\App\Services\ChangeDetector::fieldLabel('offer', 'price'), 'Price');
    eq(\App\Services\ChangeDetector::fieldLabel('offer', 'dailyRule'), 'Daily limit');
    eq(\App\Services\ChangeDetector::fieldLabel('offer', 'somethingNew'), 'Something new');
});

test('summarise reads as a sentence', function () {
    $fields = [
        ['field' => 'price', 'label' => 'Price', 'from' => 19, 'to' => 25],
        ['field' => 'priority', 'label' => 'Priority', 'from' => 1, 'to' => 2],
    ];
    eq(\App\Services\ChangeDetector::summarise('changed', '1GB', $fields), 'Price updated, Priority updated');
    eq(\App\Services\ChangeDetector::summarise('added', '1GB', []), 'Added 1GB');
    eq(\App\Services\ChangeDetector::summarise('removed', '1GB', []), 'Removed 1GB');
    eq(\App\Services\ChangeDetector::summarise('changed', '1GB', []), '1GB changed');
});

test('displayValue is readable for every value kind', function () {
    eq(\App\Services\ChangeDetector::displayValue(null), '(none)');
    eq(\App\Services\ChangeDetector::displayValue(true), 'Yes');
    eq(\App\Services\ChangeDetector::displayValue(false), 'No');
    eq(\App\Services\ChangeDetector::displayValue(''), '(empty)');
    eq(\App\Services\ChangeDetector::displayValue([]), '(empty)');
    eq(\App\Services\ChangeDetector::displayValue('Bingwa'), 'Bingwa');
    eq(\App\Services\ChangeDetector::displayValue(19, 'price'), 'KSh 19');
    eq(\App\Services\ChangeDetector::displayValue(19), '19');
    eq(\App\Services\ChangeDetector::displayValue(['a' => 1]), '{"a":1}');
});

/* ------------------------------------------------------------ resource versions */

test('resource versions keep an unchanged section and bump a changed one', function () {
    $snap = baseSnapshot();
    $first = \App\Services\ResourceVersions::compute($snap, [], 5);
    eq($first['offers']['version'], 5);
    eq($first['support']['version'], 5);
    eq($first['offers']['changed'], true);

    $edited = $snap;
    $edited['offers'][0]['price'] = 25;
    $second = \App\Services\ResourceVersions::compute($edited, $first, 6);

    eq($second['offers']['version'], 6);
    eq($second['offers']['changed'], true);
    eq($second['support']['version'], 5);
    eq($second['support']['changed'], false);
});

test('an untouched snapshot moves no resource version at all', function () {
    $snap = baseSnapshot();
    $first = \App\Services\ResourceVersions::compute($snap, [], 5);
    $second = \App\Services\ResourceVersions::compute($snap, $first, 6);
    foreach ($second as $key => $row) {
        eq($row['version'], 5, "resource {$key} should not have moved");
        eq($row['changed'], false, "resource {$key} should not be marked changed");
    }
});
