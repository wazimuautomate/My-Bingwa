<?php
/**
 * Canonical reference data for a fresh admin-v2 install:
 *  - the full permission set and the default roles (§17),
 *  - the app's shipped catalogue, message templates and support/config defaults,
 *    mirroring the Android app + legacy server so the first publish matches the app.
 *
 * Editing this file changes only NEW installs / empty tables. It never clobbers data
 * an administrator has already edited (see Seeder).
 */

return [

    // permission_key => [group, label]
    'permissions' => [
        'dashboard.view'      => ['Dashboard', 'View dashboard'],
        'offers.view'         => ['Offers', 'View offers'],
        'offers.create'       => ['Offers', 'Create offers'],
        'offers.edit'         => ['Offers', 'Edit offers'],
        'offers.archive'      => ['Offers', 'Archive/restore offers'],
        'offers.delete'       => ['Offers', 'Delete unused draft offers'],
        'billboards.manage'   => ['Billboards', 'Manage billboard adverts'],
        'notifications.create'=> ['Notifications', 'Create notification campaigns'],
        'notifications.schedule'=> ['Notifications', 'Schedule / cancel campaigns'],
        'templates.manage'    => ['Message templates', 'Manage message templates'],
        'payments.view'       => ['Payments', 'View payment operations'],
        'payments.export'     => ['Payments', 'Export payment CSV'],
        'support.edit'        => ['Support', 'Edit support & payment routes'],
        'config.edit'         => ['App config', 'Edit remote app configuration'],
        'releases.manage'     => ['Updates', 'Manage app version / update rules'],
        'publish.execute'     => ['Publishing', 'Publish a configuration release'],
        'rollback.execute'    => ['Publishing', 'Roll back to a previous release'],
        'audit.view'          => ['Audit', 'View the audit log'],
        'admins.manage'       => ['Settings', 'Manage administrators & roles'],
    ],

    // role_key => [name, description, is_system, [permission_keys]]
    'roles' => [
        'administrator' => [
            'Administrator',
            'Owner\'s partner. Creates and edits drafts in assigned modules; cannot publish, roll back, change payment routes or manage admins by default.',
            1,
            [
                'dashboard.view', 'offers.view', 'offers.create', 'offers.edit',
                'billboards.manage', 'notifications.create', 'notifications.schedule',
                'templates.manage', 'payments.view',
            ],
        ],
        'publisher' => [
            'Publisher',
            'Administrator who may also publish reviewed drafts.',
            1,
            [
                'dashboard.view', 'offers.view', 'offers.create', 'offers.edit', 'offers.archive',
                'billboards.manage', 'notifications.create', 'notifications.schedule',
                'templates.manage', 'payments.view', 'config.edit', 'releases.manage',
                'publish.execute',
            ],
        ],
        'analyst' => [
            'Analyst',
            'Read-only access to the dashboard, offers, payments and the audit log.',
            1,
            ['dashboard.view', 'offers.view', 'payments.view', 'audit.view'],
        ],
    ],

    // Support / payment public config defaults (mirror the app + legacy get_config.php).
    'support_config' => [
        'till_number'      => '4953696',
        'paybill_number'   => '40450595',
        'support_number'   => '0727921038',
        'support_whatsapp' => '254727921038',
        'offline_self_instructions'  => 'Go to M-PESA > Lipa na M-PESA > Buy Goods and Services. Enter Till 4953696, enter the exact amount, then your PIN.',
        'offline_other_instructions' => 'Go to M-PESA > Lipa na M-PESA > Pay Bill. Enter Business no. 40450595, use the recipient number as the Account number, enter the exact amount, then your PIN.',
        'support_banner'   => '',
        'working_hours'    => 'Daily, 8:00 AM - 9:00 PM',
    ],

    'app_config' => [
        'maintenance_mode'       => 0,
        'sync_interval_minutes'  => 360,
        'snapshot_cache_hours'   => 168,
        'offline_config_valid_hours' => 168,
        'quiet_hours_start'      => '21:00',
        'quiet_hours_end'        => '07:00',
        'campaign_daily_cap'     => 2,
        'feature_flags'          => ['sms_parsing' => false, 'billboards' => true],
        'personalisation'        => [
            'frequency_weight' => 1.0, 'value_weight' => 0.6, 'validity_weight' => 0.4,
            'diversity_floor'  => 0.2, 'max_step_up' => 3.0, 'top_pool' => 5,
        ],
    ],

    'app_version' => [
        'latest_version_code' => 1,
        'latest_version_name' => '1.0.0',
        'min_supported_version_code' => 1,
        'mandatory' => 0,
        'play_store_url' => 'https://play.google.com/store/apps/details?id=com.bingwasokoni',
        'apk_url' => 'https://github.com/wazimuautomate/My-Bingwa/releases',
        'apk_sha256' => '',
        'rollout_percent' => 100,
        'release_notes' => 'Initial public release.',
    ],

    // [offer_id, category, name, price, validity, band, once?] — mirror the app catalogue.
    'offers' => [
        ['data_1',  'DATA', '1GB',           19,   '1 Hr',     'Hourly',  true],
        ['data_2',  'DATA', '250MB',         20,   '24 Hrs',   'Daily',   true],
        ['data_3',  'DATA', '1.5GB',         50,   '3 Hrs',    'Hourly',  true],
        ['data_4',  'DATA', '1.25GB',        55,   'Midnight', 'Daily',   true],
        ['data_5',  'DATA', '1GB',           95,   '24 Hrs',   'Daily',   true],
        ['data_6',  'DATA', '2GB',           110,  '24 Hrs',   'Daily',   false],
        ['data_7',  'DATA', '350MB',         49,   '7 days',   'Weekly',  true],
        ['data_8',  'DATA', '2.5GB',         300,  '7 days',   'Weekly',  true],
        ['data_9',  'DATA', '6GB',           700,  '7 days',   'Weekly',  true],
        ['data_10', 'DATA', '1.2GB',         250,  '30 days',  'Monthly', true],
        ['data_11', 'DATA', '2.5GB',         500,  '30 days',  'Monthly', true],
        ['data_12', 'DATA', '10GB',          1000, '30 days',  'Monthly', true],
        ['data_13', 'DATA', '8GB + 400 Min', 1005, '30 days',  'Monthly', true],
        ['sms_1',   'SMS',  '10 SMS',        5,    '24 Hrs',   'Daily',   false],
        ['sms_2',   'SMS',  '200 SMS',       10,   '24 Hrs',   'Daily',   false],
        ['sms_3',   'SMS',  '1,000 SMS',     30,   '7 days',   'Weekly',  false],
        ['sms_4',   'SMS',  '1,500 SMS',     101,  '30 days',  'Monthly', false],
        ['sms_5',   'SMS',  '3,500 SMS',     201,  '30 days',  'Monthly', false],
        ['min_1',   'MINUTES', '20 Min',     22,   'Midnight', 'Daily',   false],
        ['min_2',   'MINUTES', '35 Min',     23,   '2 Hrs',    'Hourly',  false],
        ['min_3',   'MINUTES', '45 Min',     24,   '3 Hrs',    'Hourly',  false],
        ['min_4',   'MINUTES', '50 Min',     48,   'Midnight', 'Daily',   false],
        ['min_5',   'MINUTES', '250 Min',    205,  '7 days',   'Weekly',  false],
        ['min_6',   'MINUTES', '100 Min',    105,  'Midnight', 'Daily',   false],
        ['min_7',   'MINUTES', '300 Min',    499,  '30 days',  'Monthly', false],
        ['min_8',   'MINUTES', '800 Min',    950,  '30 days',  'Monthly', false],
        ['spec_1',  'SPECIAL', '1GB',        21,   '1 Hr',     'Hourly',  true],
        ['spec_2',  'SPECIAL', '1.5GB',      51,   '3 Hrs',    'Hourly',  true],
        ['spec_3',  'SPECIAL', '2GB',        110,  '24 Hrs',   'Daily',   false],
    ],

    // Safaricom senders.
    'sender_ids' => [
        ['Safaricom', 'Bundle delivery + general Safaricom messages'],
        ['SAF_Balance', 'Balance / deal-of-the-day notices'],
        ['SAF_OfaMOTO', 'Minutes / offers delivery'],
    ],

    // [template_key, purpose, sender, category, pattern, label, positive[], negative[]]
    'message_templates' => [
        [
            'data_bingwa_sokoni', 'delivery', 'Safaricom', 'DATA',
            'received\\b.*?\\d+\\s*(?:MB|GB).*?from\\s+Bingwa\\s+Sokoni', 'Bingwa Sokoni data delivery',
            ['You have received Sh20=250MB 24hr from Bingwa Sokoni. Valid till...'],
            ['Your data balance is below 2MBs'],
        ],
        [
            'sms_daily_bundle', 'delivery', 'Safaricom', 'SMS',
            'received\\s+\\d+\\s+SMS', 'SMS bundle delivery',
            ['You have received 20 SMS Daily SMS Bundle. Expiry date:...'],
            ['You have received Sh20=250MB'],
        ],
        [
            'minutes_gift', 'delivery', 'SAF_OfaMOTO', 'MINUTES',
            'received\\s+a\\s+gift\\s+of.*?\\d+\\s*Mins', 'Minutes gift delivery',
            ['You have received a gift of Sh20=43 Mins,3hrs from...'],
            ['You have received 20 SMS'],
        ],
        [
            'data_low_balance', 'low_balance', 'SAF_Balance', 'DATA',
            'data\\s+balance\\s+is\\s+(?:below|[0-9])', 'Data low balance',
            ['Dear customer, your deal of the day data balance is 75MBs...'],
            ['You have received Sh20=250MB from Bingwa Sokoni'],
        ],
        [
            'data_very_low_balance', 'very_low_balance', 'SAF_Balance', 'DATA',
            'data\\s+balance\\s+is\\s+below\\s+2\\s*MBs', 'Data very low balance',
            ['Dear customer, your deal of the day data balance is below 2MBs...'],
            ['your deal of the day data balance is 75MBs'],
        ],
    ],
];
