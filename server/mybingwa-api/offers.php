<?php
/**
 * Authoritative offer prices. The SERVER decides the price from the offerId — the
 * app never sends a trusted amount (CLAUDE.md §7, Plan.md §API).
 *
 * These mirror the app catalogue for now. Later, when you manage offers from
 * cPanel, replace this with a SELECT from an `offers` table — nothing else changes.
 *
 * Returns: offerId => price in KSh.
 */

return [
    'off_1'  => 19,
    'off_2'  => 20,
    'off_3'  => 50,
    'off_4'  => 55,
    'off_5'  => 110,
    'off_6'  => 5,
    'off_7'  => 10,
    'off_8'  => 30,
    'off_9'  => 22,
    'off_10' => 23,
    'off_11' => 51,
    'off_12' => 99,
    'off_13' => 45,
    'off_14' => 250,
    'off_15' => 1000,
    'off_16' => 1500,
];
