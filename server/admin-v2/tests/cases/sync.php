<?php
/**
 * Synchronisation API — pure logic.
 *
 * Everything covered here runs without a database and without HTTP: per-resource
 * checksums and versions (App\Services\ResourceVersions) and the request-parameter
 * helpers of the sync controller (App\Controllers\Api\SyncController).
 *
 * Required by tests/run.php, which already defines test(), ok() and eq().
 */

/* ------------------------------------------------------- resource checksums */

test('resource checksums are stable and key-order independent', function () {
    $a = ['offers' => [['id' => 'data_1', 'price' => 19]], 'support' => ['tillNumber' => '111', 'paybillNumber' => '222']];
    $b = ['support' => ['paybillNumber' => '222', 'tillNumber' => '111'], 'offers' => [['id' => 'data_1', 'price' => 19]]];
    $ca = \App\Services\ResourceVersions::checksums($a);
    $cb = \App\Services\ResourceVersions::checksums($b);
    eq($ca['offers'], $cb['offers']);
    eq($ca['support'], $cb['support']);
    eq(strlen($ca['offers']), 64, 'checksum should be a sha256 hex string');
});

test('resource checksums change when the section changes', function () {
    $one = \App\Services\ResourceVersions::checksums(['offers' => [['id' => 'a', 'price' => 10]]]);
    $two = \App\Services\ResourceVersions::checksums(['offers' => [['id' => 'a', 'price' => 11]]]);
    ok($one['offers'] !== $two['offers']);
});

test('resource checksums only cover known, present sections', function () {
    $snapshot = ['offers' => [], 'smsRules' => [], 'somethingElse' => ['x' => 1]];
    $sums = \App\Services\ResourceVersions::checksums($snapshot);
    ok(array_key_exists('offers', $sums));
    ok(array_key_exists('smsRules', $sums));
    ok(!array_key_exists('somethingElse', $sums), 'unknown sections are not resources');
    ok(!array_key_exists('billboards', $sums), 'absent sections are not invented');
});

test('every declared resource has a label and a list flag', function () {
    foreach (\App\Services\ResourceVersions::keys() as $key) {
        ok(\App\Services\ResourceVersions::label($key) !== '');
        ok(is_bool(\App\Services\ResourceVersions::isList($key)));
    }
    ok(in_array('offers', \App\Services\ResourceVersions::keys(), true));
    ok(in_array('smsRules', \App\Services\ResourceVersions::keys(), true));
    ok(in_array('featureFlags', \App\Services\ResourceVersions::keys(), true));
});

/* ------------------------------------------------------------ item counts */

test('countOf counts list resources and marks singletons as one', function () {
    eq(\App\Services\ResourceVersions::countOf('offers', [['id' => 'a'], ['id' => 'b']]), 2);
    eq(\App\Services\ResourceVersions::countOf('offers', []), 0);
    eq(\App\Services\ResourceVersions::countOf('offers', null), 0);
    eq(\App\Services\ResourceVersions::countOf('support', ['tillNumber' => '1']), 1);
    eq(\App\Services\ResourceVersions::countOf('support', null), 0);
    eq(\App\Services\ResourceVersions::countOf('appConfig', ['maintenanceMode' => false]), 1);
});

/* -------------------------------------------------------- version computing */

test('compute assigns the new version to every resource on a first publish', function () {
    $snapshot = ['offers' => [['id' => 'a', 'price' => 10]], 'support' => ['tillNumber' => '111']];
    $map = \App\Services\ResourceVersions::compute($snapshot, [], 5);
    eq($map['offers']['version'], 5);
    eq($map['support']['version'], 5);
    eq($map['offers']['changed'], true);
    eq($map['offers']['count'], 1);
});

test('compute keeps an unchanged resource at its old version and bumps a changed one', function () {
    $first = ['offers' => [['id' => 'a', 'price' => 10]], 'support' => ['tillNumber' => '111']];
    $v5 = \App\Services\ResourceVersions::compute($first, [], 5);

    $second = $first;
    $second['support']['tillNumber'] = '222';   // only support moved
    $v6 = \App\Services\ResourceVersions::compute($second, $v5, 6);

    eq($v6['offers']['version'], 5, 'an untouched resource must not move');
    eq($v6['offers']['changed'], false);
    eq($v6['offers']['checksum'], $v5['offers']['checksum']);
    eq($v6['support']['version'], 6);
    eq($v6['support']['changed'], true);
});

test('compute is idempotent when nothing changed at all', function () {
    $snapshot = ['offers' => [['id' => 'a', 'price' => 10]], 'featureFlags' => ['x' => true]];
    $v2 = \App\Services\ResourceVersions::compute($snapshot, [], 2);
    $v3 = \App\Services\ResourceVersions::compute($snapshot, $v2, 3);
    eq($v3['offers']['version'], 2);
    eq($v3['featureFlags']['version'], 2);
    foreach ($v3 as $row) {
        eq($row['changed'], false);
    }
});

test('a resource added by a later release starts at that release version', function () {
    $v1 = \App\Services\ResourceVersions::compute(['offers' => []], [], 1);
    $v2 = \App\Services\ResourceVersions::compute(['offers' => [], 'smsRules' => []], $v1, 2);
    eq($v2['offers']['version'], 1);
    eq($v2['smsRules']['version'], 2);
});

test('forRelease tolerates a release published before resource versioning', function () {
    eq(\App\Services\ResourceVersions::forRelease(null), []);
    eq(\App\Services\ResourceVersions::forRelease(['resource_versions_json' => '']), []);
    eq(\App\Services\ResourceVersions::forRelease(['resource_versions_json' => 'not json']), []);
    $map = \App\Services\ResourceVersions::forRelease(['resource_versions_json' => '{"offers":{"version":7,"checksum":"abc"}}']);
    eq($map['offers']['version'], 7);
});

/* ------------------------------------------------- controller: keys parsing */

$supportedKeys = \App\Services\ResourceVersions::keys();

test('parseKeys with no parameter returns every supported resource', function () use ($supportedKeys) {
    $r = \App\Controllers\Api\SyncController::parseKeys(null, $supportedKeys);
    eq($r['keys'], $supportedKeys);
    eq($r['unknown'], []);
    eq(\App\Controllers\Api\SyncController::parseKeys('   ', $supportedKeys)['keys'], $supportedKeys);
});

test('parseKeys selects the requested resources in the order asked', function () use ($supportedKeys) {
    $r = \App\Controllers\Api\SyncController::parseKeys('smsRules,offers', $supportedKeys);
    eq($r['keys'], ['smsRules', 'offers']);
    eq($r['unknown'], []);
});

test('parseKeys reports unknown keys instead of failing the request', function () use ($supportedKeys) {
    $r = \App\Controllers\Api\SyncController::parseKeys('offers,wallet,smsRules', $supportedKeys);
    eq($r['keys'], ['offers', 'smsRules']);
    eq($r['unknown'], ['wallet']);
});

test('parseKeys trims spacing, drops empties and de-duplicates', function () use ($supportedKeys) {
    $r = \App\Controllers\Api\SyncController::parseKeys(' offers , , offers ,,support', $supportedKeys);
    eq($r['keys'], ['offers', 'support']);
    eq($r['unknown'], []);
});

test('parseKeys sanitises hostile input rather than echoing it', function () use ($supportedKeys) {
    $r = \App\Controllers\Api\SyncController::parseKeys('offers,<script>alert(1)</script>', $supportedKeys);
    eq($r['keys'], ['offers']);
    eq($r['unknown'], ['scriptalert1script']);
    foreach ($r['unknown'] as $u) {
        ok(preg_match('/^[A-Za-z0-9._-]{1,40}$/', $u) === 1, 'unknown keys must be safe to echo');
    }
    $sql = \App\Controllers\Api\SyncController::parseKeys("offers';DROP TABLE mb_offers;--", $supportedKeys);
    eq($sql['keys'], []);
    ok(strpos($sql['unknown'][0], "'") === false && strpos($sql['unknown'][0], ' ') === false);
});

test('parseKeys caps how many keys one request may ask for', function () use ($supportedKeys) {
    $tokens = [];
    for ($i = 0; $i < 60; $i++) {
        $tokens[] = 'k' . $i;
    }
    $r = \App\Controllers\Api\SyncController::parseKeys(implode(',', $tokens), $supportedKeys);
    eq(count($r['keys']) + count($r['unknown']), \App\Controllers\Api\SyncController::MAX_BATCH_KEYS);
});

test('parseKeys against an empty supported list returns nothing supported', function () {
    $r = \App\Controllers\Api\SyncController::parseKeys('offers', []);
    eq($r['keys'], []);
    eq($r['unknown'], ['offers']);
});

/* ------------------------------------------------ controller: since parsing */

test('parseSince accepts a non-negative integer only', function () {
    eq(\App\Controllers\Api\SyncController::parseSince('12'), 12);
    eq(\App\Controllers\Api\SyncController::parseSince(' 0 '), 0);
    eq(\App\Controllers\Api\SyncController::parseSince(7), 7);
    eq(\App\Controllers\Api\SyncController::parseSince(null), null);
    eq(\App\Controllers\Api\SyncController::parseSince(''), null);
    eq(\App\Controllers\Api\SyncController::parseSince('-3'), null);
    eq(\App\Controllers\Api\SyncController::parseSince('1.5'), null);
    eq(\App\Controllers\Api\SyncController::parseSince('12; DROP TABLE x'), null);
    eq(\App\Controllers\Api\SyncController::parseSince(['1']), null);
    eq(\App\Controllers\Api\SyncController::parseSince('9999999999999'), null);
});

/* ---------------------------------------------- controller: key + url helpers */

test('normaliseKey strips anything that is not an identifier', function () {
    eq(\App\Controllers\Api\SyncController::normaliseKey(' offers '), 'offers');
    eq(\App\Controllers\Api\SyncController::normaliseKey('../../etc/passwd'), '....etcpasswd');
    eq(\App\Controllers\Api\SyncController::normaliseKey('offers%2F..'), 'offers2F..');
    eq(\App\Controllers\Api\SyncController::normaliseKey(''), '');
    ok(strlen(\App\Controllers\Api\SyncController::normaliseKey(str_repeat('a', 200))) <= 40);
});

test('resourceUrl is a relative path with no scheme or host', function () {
    eq(\App\Controllers\Api\SyncController::resourceUrl('offers'), 'api/sync/resource/offers');
    $url = \App\Controllers\Api\SyncController::resourceUrl('smsRules');
    ok(strpos($url, '://') === false, 'never a hardcoded domain');
    ok($url[0] !== '/', 'relative so the app can resolve it against its own base URL');
});

/* -------------------------------------------- controller: anonymous install id */

test('installId accepts only an anonymous opaque identifier', function () {
    eq(\App\Controllers\Api\SyncController::installId('a1B2-c3_d4.e5'), 'a1B2-c3_d4.e5');
    eq(\App\Controllers\Api\SyncController::installId(str_repeat('a', 64)), str_repeat('a', 64));
    eq(\App\Controllers\Api\SyncController::installId(str_repeat('a', 65)), null);
    eq(\App\Controllers\Api\SyncController::installId(''), null);
    eq(\App\Controllers\Api\SyncController::installId(null), null);
    eq(\App\Controllers\Api\SyncController::installId('has space'), null);
    eq(\App\Controllers\Api\SyncController::installId("x'; DROP TABLE mb_sync_events;--"), null);
    // A phone number would be silently ignored as an id shape we never ask for, but the
    // app must never send one — see docs/server/API.md.
    eq(\App\Controllers\Api\SyncController::installId('+254712345678'), null);
});

/* ---------------------------------------------------- controller: ETag matching */

test('etagMatches honours lists, weak tags and wildcards', function () {
    $etag = '"abc123"';
    ok(\App\Controllers\Api\SyncController::etagMatches('"abc123"', $etag));
    ok(\App\Controllers\Api\SyncController::etagMatches(' "zzz", "abc123" ', $etag));
    ok(\App\Controllers\Api\SyncController::etagMatches('W/"abc123"', $etag));
    ok(\App\Controllers\Api\SyncController::etagMatches('*', $etag));
    ok(!\App\Controllers\Api\SyncController::etagMatches('"abc124"', $etag));
    ok(!\App\Controllers\Api\SyncController::etagMatches('', $etag));
    ok(!\App\Controllers\Api\SyncController::etagMatches(null, $etag));
    ok(!\App\Controllers\Api\SyncController::etagMatches('"abc123"', ''));
});
