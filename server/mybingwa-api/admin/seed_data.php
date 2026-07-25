<?php
/**
 * Canonical "app defaults" — the exact data the app ships with today. The admin's
 * "Load app defaults" button upserts these into the database so the server mirrors
 * the app. Keep this in step with the app when its bundled data changes.
 */

return [

    // Contact / payment details (mirror AppConfig.DEFAULT).
    'settings' => [
        'till_number'      => '',
        'paybill_number'   => '',
        'support_number'   => '',
        'support_whatsapp' => '',
    ],

    // Catalogue (mirror the app's bundled offers). [id, category, name, price,
    // validity, band, once?] — once=true → ONCE_PER_DAY, else BUY_AGAIN_TODAY.
    'offers' => [
        ['data_1',  'DATA', '1GB',          19,   '1 Hr',     'Hourly',  true,  1],
        ['data_2',  'DATA', '250MB',        20,   '24 Hrs',   'Daily',   true,  2],
        ['data_3',  'DATA', '1.5GB',        50,   '3 Hrs',    'Hourly',  true,  3],
        ['data_4',  'DATA', '1.25GB',       55,   'Midnight', 'Daily',   true,  4],
        ['data_5',  'DATA', '1GB',          95,   '24 Hrs',   'Daily',   true,  5],
        ['data_6',  'DATA', '2GB',          110,  '24 Hrs',   'Daily',   false, 6],
        ['data_7',  'DATA', '350MB',        49,   '7 days',   'Weekly',  true,  7],
        ['data_8',  'DATA', '2.5GB',        300,  '7 days',   'Weekly',  true,  8],
        ['data_9',  'DATA', '6GB',          700,  '7 days',   'Weekly',  true,  9],
        ['data_10', 'DATA', '1.2GB',        250,  '30 days',  'Monthly', true,  10],
        ['data_11', 'DATA', '2.5GB',        500,  '30 days',  'Monthly', true,  11],
        ['data_12', 'DATA', '10GB',         1000, '30 days',  'Monthly', true,  12],
        ['data_13', 'DATA', '8GB + 400 Min', 1005, '30 days', 'Monthly', true,  13],
        ['sms_1',   'SMS',  '10 SMS',       5,    '24 Hrs',   'Daily',   false, 20],
        ['sms_2',   'SMS',  '200 SMS',      10,   '24 Hrs',   'Daily',   false, 21],
        ['sms_3',   'SMS',  '1,000 SMS',    30,   '7 days',   'Weekly',  false, 22],
        ['sms_4',   'SMS',  '1,500 SMS',    101,  '30 days',  'Monthly', false, 23],
        ['sms_5',   'SMS',  '3,500 SMS',    201,  '30 days',  'Monthly', false, 24],
        ['min_1',   'MINUTES', '20 Min',    22,   'Midnight', 'Daily',   false, 30],
        ['min_2',   'MINUTES', '35 Min',    23,   '2 Hrs',    'Hourly',  false, 31],
        ['min_3',   'MINUTES', '45 Min',    24,   '3 Hrs',    'Hourly',  false, 32],
        ['min_4',   'MINUTES', '50 Min',    48,   'Midnight', 'Daily',   false, 33],
        ['min_5',   'MINUTES', '250 Min',   205,  '7 days',   'Weekly',  false, 34],
        ['min_6',   'MINUTES', '100 Min',   105,  'Midnight', 'Daily',   false, 35],
        ['min_7',   'MINUTES', '300 Min',   499,  '30 days',  'Monthly', false, 36],
        ['min_8',   'MINUTES', '800 Min',   950,  '30 days',  'Monthly', false, 37],
        ['spec_1',  'SPECIAL', '1GB',       21,   '1 Hr',     'Hourly',  true,  40],
        ['spec_2',  'SPECIAL', '1.5GB',     51,   '3 Hrs',    'Hourly',  true,  41],
        ['spec_3',  'SPECIAL', '2GB',       110,  '24 Hrs',   'Daily',   false, 42],
    ],

    // Notification / SMS-recognition templates (mirror DefaultTemplates.SEED).
    // [tkey, type, sender_id, category, pattern, label]
    'templates' => [
        ['data_bingwa_sokoni', 'delivery',    'Safaricom',   'DATA',    'received\\b.*?\\d+\\s*(?:MB|GB).*?from\\s+Bingwa\\s+Sokoni', 'Bingwa Sokoni data delivery'],
        ['sms_daily_bundle',   'delivery',    'Safaricom',   'SMS',     'received\\s+\\d+\\s+SMS',                                    'SMS bundle delivery'],
        ['minutes_gift',       'delivery',    'SAF_OfaMOTO', 'MINUTES', 'received\\s+a\\s+gift\\s+of.*?\\d+\\s*Mins',                  'Minutes gift delivery'],
        ['data_low_balance',   'low_balance', 'SAF_Balance', 'DATA',    'data\\s+balance\\s+is\\s+below',                             'Data low balance'],
    ],
];
