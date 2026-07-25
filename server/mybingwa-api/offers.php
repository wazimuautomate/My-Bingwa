<?php
/**
 * Authoritative offer prices. The SERVER decides the price from the offerId — the
 * app never sends a trusted amount (CLAUDE.md §7, Plan.md §API).
 *
 * IMPORTANT: these ids MUST match the ids the app sends (the app catalogue /
 * `offers.sql`). A mismatch makes stk.php return UNKNOWN_OFFER, which the app shows
 * as an instant "could not start payment" failure.
 *
 * Later, when you manage offers from cPanel, replace this with a SELECT from the
 * `offers` table (offers.sql) — nothing else changes.
 *
 * Returns: offerId => price in KSh.
 */

return [
    // Data
    'data_1'  => 19,
    'data_2'  => 20,
    'data_3'  => 50,
    'data_4'  => 55,
    'data_5'  => 95,
    'data_6'  => 110,
    'data_7'  => 49,
    'data_8'  => 300,
    'data_9'  => 700,
    'data_10' => 250,
    'data_11' => 500,
    'data_12' => 1000,
    'data_13' => 1005,
    // SMS
    'sms_1'   => 5,
    'sms_2'   => 10,
    'sms_3'   => 30,
    'sms_4'   => 101,
    'sms_5'   => 201,
    // Minutes
    'min_1'   => 22,
    'min_2'   => 23,
    'min_3'   => 24,
    'min_4'   => 48,
    'min_5'   => 205,
    'min_6'   => 105,
    'min_7'   => 499,
    'min_8'   => 950,
    // Special
    'spec_1'  => 21,
    'spec_2'  => 51,
    'spec_3'  => 110,
    // Diagnostic-only KSh 1 offer for end-to-end payment tests. Not shown in the app.
    // Safe to remove after testing.
    'test_1'  => 1,
];
