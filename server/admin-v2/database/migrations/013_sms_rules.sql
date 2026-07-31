-- Dynamic SMS rule management.
--
-- The Android app must never hardcode how a Safaricom message is understood. The server
-- owns the rules; the app downloads them with the published configuration and evaluates
-- them locally (offline-first). Event types and pattern types live in catalogue tables so
-- new ones are added by an administrator, never by a schema change.

CREATE TABLE IF NOT EXISTS {p}sms_event_types (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    event_key   VARCHAR(40) NOT NULL,                 -- DATA_RECEIVED, LOW_SMS, ...
    label       VARCHAR(80) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    is_system   TINYINT NOT NULL DEFAULT 0,           -- shipped with the product; cannot be deleted
    enabled     TINYINT NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 100,
    created_at  DATETIME NOT NULL,
    UNIQUE KEY uniq_sms_event (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}sms_pattern_types (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    type_key    VARCHAR(32) NOT NULL,                 -- regex, contains, starts_with, ...
    label       VARCHAR(80) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    enabled     TINYINT NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 100,
    created_at  DATETIME NOT NULL,
    UNIQUE KEY uniq_sms_pattern_type (type_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}sms_rules (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    rule_key         VARCHAR(64) NOT NULL,            -- stable id sent to the app
    name             VARCHAR(120) NOT NULL,
    description      VARCHAR(255) NOT NULL DEFAULT '',
    sender_id        VARCHAR(40) NOT NULL DEFAULT '', -- '' matches any sender
    pattern_type     VARCHAR(32) NOT NULL DEFAULT 'regex',
    pattern          TEXT NOT NULL,                   -- regex source, phrase, or newline/comma keywords
    case_sensitive   TINYINT NOT NULL DEFAULT 0,
    event_type       VARCHAR(40) NOT NULL DEFAULT 'UNKNOWN',
    secondary_events VARCHAR(255) NOT NULL DEFAULT '',-- comma separated, e.g. "SMS_RECEIVED"
    category         VARCHAR(16) NOT NULL DEFAULT '', -- DATA|SMS|MINUTES|SPECIAL (optional hint)
    bundle_type      VARCHAR(24) NOT NULL DEFAULT '', -- Hourly|Daily|Weekly|Monthly (optional hint)
    captures_json    TEXT NULL,                       -- {"amount":1,"allowance":2}
    correlation_window_min INT NOT NULL DEFAULT 30,   -- how long after a purchase this may still be related
    priority         INT NOT NULL DEFAULT 100,        -- higher wins when several rules match
    enabled          TINYINT NOT NULL DEFAULT 1,
    positive_samples TEXT NULL,                       -- JSON array of messages that MUST match
    negative_samples TEXT NULL,                       -- JSON array of messages that must NOT match
    row_version      INT NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL,
    updated_at       DATETIME NOT NULL,
    created_by       VARCHAR(120) NOT NULL DEFAULT '',
    updated_by       VARCHAR(120) NOT NULL DEFAULT '',
    UNIQUE KEY uniq_sms_rule_key (rule_key),
    KEY idx_sms_rule_event (event_type),
    KEY idx_sms_rule_enabled (enabled),
    KEY idx_sms_rule_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}sms_rule_revisions (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    rule_key      VARCHAR(64) NOT NULL,
    snapshot_json TEXT NOT NULL,
    action        VARCHAR(16) NOT NULL,
    actor_name    VARCHAR(120) NOT NULL DEFAULT '',
    created_at    DATETIME NOT NULL,
    KEY idx_smsrev_key (rule_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
-- ------------------------------------------------------------------ catalogues
INSERT IGNORE INTO {p}sms_pattern_types (type_key, label, description, sort_order, created_at) VALUES
    ('regex',        'Regular expression', 'Full PCRE pattern. Capture groups can extract values.', 10, UTC_TIMESTAMP()),
    ('contains',     'Contains',           'Matches when the message contains this text anywhere.', 20, UTC_TIMESTAMP()),
    ('starts_with',  'Starts with',        'Matches when the message begins with this text.',       30, UTC_TIMESTAMP()),
    ('ends_with',    'Ends with',          'Matches when the message ends with this text.',         40, UTC_TIMESTAMP()),
    ('exact',        'Exact match',        'Matches only when the whole message is exactly this text.', 50, UTC_TIMESTAMP()),
    ('keywords',     'Keyword combination','Every keyword (one per line, or comma separated) must appear.', 60, UTC_TIMESTAMP());
-- @@
INSERT IGNORE INTO {p}sms_event_types (event_key, label, description, is_system, sort_order, created_at) VALUES
    ('DATA_RECEIVED',    'Data received',     'A data bundle arrived on the device.',        1, 10, UTC_TIMESTAMP()),
    ('SMS_RECEIVED',     'SMS received',      'An SMS bundle arrived on the device.',        1, 20, UTC_TIMESTAMP()),
    ('MINUTES_RECEIVED', 'Minutes received',  'A minutes bundle arrived on the device.',     1, 30, UTC_TIMESTAMP()),
    ('LOW_DATA',         'Low data',          'The data balance is getting low.',            1, 40, UTC_TIMESTAMP()),
    ('VERY_LOW_DATA',    'Very low data',     'The data balance is nearly finished.',        1, 50, UTC_TIMESTAMP()),
    ('NO_DATA',          'No data',           'There is no active data bundle.',             1, 60, UTC_TIMESTAMP()),
    ('LOW_SMS',          'Low SMS',           'The SMS balance is getting low.',             1, 70, UTC_TIMESTAMP()),
    ('LOW_MINUTES',      'Low minutes',       'The minutes balance is getting low.',         1, 80, UTC_TIMESTAMP()),
    ('GIFT_RECEIVED',    'Gift received',     'A gifted bundle arrived on the device.',      1, 90, UTC_TIMESTAMP()),
    ('UNKNOWN',          'Unknown',           'Recognised sender but no meaningful event.',  1, 999, UTC_TIMESTAMP());
-- @@
-- --------------------------------------------------------------- starter rules
-- The Safaricom formats in use today. These are ordinary editable rows: future format
-- changes are an admin edit, never a PHP or app change.
INSERT IGNORE INTO {p}sms_rules
    (rule_key, name, description, sender_id, pattern_type, pattern, case_sensitive, event_type,
     secondary_events, category, bundle_type, captures_json, correlation_window_min, priority, enabled,
     positive_samples, negative_samples, row_version, created_at, updated_at, created_by, updated_by)
VALUES
    ('saf_sms_daily_bundle', 'SMS bundle received', 'You have received 20 SMS Daily SMS Bundle.',
     'Safaricom', 'regex', 'received\\s+(\\d+)\\s+SMS', 0, 'SMS_RECEIVED',
     '', 'SMS', '', '{"allowance":1}', 30, 200, 1,
     '["You have received 20 SMS Daily SMS Bundle."]', '["You have received Sh20=250MB 24hr from Bingwa Sokoni."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system'),

    ('saf_data_bingwa_sokoni', 'Bingwa Sokoni data received', 'You have received Sh20=250MB 24hr from Bingwa Sokoni.',
     'Safaricom', 'regex', 'received\\s+Sh(\\d+)\\s*=\\s*([\\d.]+\\s*(?:MB|GB)).*?from\\s+Bingwa\\s+Sokoni', 0, 'DATA_RECEIVED',
     '', 'DATA', '', '{"amount":1,"allowance":2}', 30, 220, 1,
     '["You have received Sh20=250MB 24hr from Bingwa Sokoni."]', '["Dear customer, your deal of the day data balance is 75MBs."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system'),

    ('saf_data_hourly_purchase', 'Hourly data bundle received', 'Thank You For Purchasing 1024.0 MB hourly data bundle.',
     'Safaricom', 'regex', 'Purchasing\\s+([\\d.]+\\s*(?:MB|GB))\\s+hourly\\s+data\\s+bundle', 0, 'DATA_RECEIVED',
     '', 'DATA', 'Hourly', '{"allowance":1}', 30, 210, 1,
     '["Thank You For Purchasing 1024.0 MB hourly data bundle."]', '["You have received 20 SMS Daily SMS Bundle."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system'),

    ('saf_minutes_talkmore', 'Talkmore minutes received', 'You have received 45 Talkmore Minutes.',
     'Safaricom', 'regex', 'received\\s+(\\d+)\\s+Talkmore\\s+Minutes', 0, 'MINUTES_RECEIVED',
     '', 'MINUTES', '', '{"allowance":1}', 30, 200, 1,
     '["You have received 45 Talkmore Minutes."]', '["You have received 20 SMS Daily SMS Bundle."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system'),

    ('saf_easytalk_minutes_sms', 'Easy-Talk minutes + SMS received', 'You have received Easy-Talk 50 Minutes + 50 SMS.',
     'Safaricom', 'regex', 'received\\s+Easy-?Talk\\s+(\\d+)\\s*Minutes\\s*\\+\\s*(\\d+)\\s*SMS', 0, 'MINUTES_RECEIVED',
     'SMS_RECEIVED', 'MINUTES', '', '{"minutes":1,"sms":2}', 30, 230, 1,
     '["You have received Easy-Talk 50 Minutes + 50 SMS."]', '["You have received 45 Talkmore Minutes."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system'),

    ('saf_balance_data_low', 'Deal of the day data low', 'Dear customer, your deal of the day data balance is 75MBs.',
     'SAF_Balance', 'regex', 'data\\s+balance\\s+is\\s+([\\d.]+\\s*MBs?)', 0, 'LOW_DATA',
     '', 'DATA', '', '{"remaining":1}', 0, 100, 1,
     '["Dear customer, your deal of the day data balance is 75MBs."]', '["your deal of the day data balance is below 2MBs."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system'),

    ('saf_balance_data_very_low', 'Deal of the day data very low', 'your deal of the day data balance is below 2MBs.',
     'SAF_Balance', 'regex', 'data\\s+balance\\s+is\\s+below\\s+2\\s*MBs', 0, 'VERY_LOW_DATA',
     '', 'DATA', '', NULL, 0, 300, 1,
     '["Dear customer, your deal of the day data balance is below 2MBs."]', '["Dear customer, your deal of the day data balance is 75MBs."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system'),

    ('saf_balance_no_data', 'No active data bundle', 'you do not have an active data bundle.',
     'SAF_Balance', 'contains', 'do not have an active data bundle', 0, 'NO_DATA',
     '', 'DATA', '', NULL, 0, 320, 1,
     '["Dear customer, you do not have an active data bundle."]', '["Dear customer, your deal of the day data balance is 75MBs."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system'),

    ('saf_balance_minutes_low', 'Easy-Talk minutes low', 'Easy-Talk Minutes are below 10 Minutes.',
     'SAF_Balance', 'regex', 'Easy-?Talk\\s+Minutes\\s+are\\s+below\\s+(\\d+)\\s*Minutes', 0, 'LOW_MINUTES',
     '', 'MINUTES', '', '{"remaining":1}', 0, 110, 1,
     '["Easy-Talk Minutes are below 10 Minutes."]', '["your deal of the day data balance is below 2MBs."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system'),

    ('saf_ofamoto_gift', 'Gift received', 'You have received a gift...',
     'SAF_OfaMOTO', 'contains', 'received a gift', 0, 'GIFT_RECEIVED',
     '', '', '', NULL, 30, 150, 1,
     '["You have received a gift of Sh20=43 Mins,3hrs from Safaricom."]', '["You have received 20 SMS Daily SMS Bundle."]',
     1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'system', 'system');
-- @@
-- ----------------------------------------------------- upgrade path from v1 templates
-- An existing installation already curated Safaricom patterns in {p}message_templates.
-- Import them so nothing an administrator wrote is lost, mapping purpose+category onto
-- the new event vocabulary. Fresh installs import nothing (the table is empty) and use
-- the starter rules above. Rule keys are prefixed so they can never collide.
INSERT IGNORE INTO {p}sms_rules
    (rule_key, name, description, sender_id, pattern_type, pattern, case_sensitive, event_type,
     secondary_events, category, bundle_type, captures_json, correlation_window_min, priority, enabled,
     positive_samples, negative_samples, row_version, created_at, updated_at, created_by, updated_by)
SELECT
    CONCAT('tpl_', t.template_key),
    t.label,
    '',
    t.sender_id,
    'regex',
    t.pattern,
    t.case_sensitive,
    CASE
        WHEN t.purpose = 'delivery' AND t.category = 'SMS'     THEN 'SMS_RECEIVED'
        WHEN t.purpose = 'delivery' AND t.category = 'MINUTES' THEN 'MINUTES_RECEIVED'
        WHEN t.purpose = 'delivery'                            THEN 'DATA_RECEIVED'
        WHEN t.purpose = 'very_low_balance'                    THEN 'VERY_LOW_DATA'
        WHEN t.category = 'SMS'                                THEN 'LOW_SMS'
        WHEN t.category = 'MINUTES'                            THEN 'LOW_MINUTES'
        ELSE 'LOW_DATA'
    END,
    '',
    t.category,
    '',
    t.captures_json,
    t.correlation_window_min,
    t.match_priority * 10,
    CASE WHEN t.status = 'active' THEN 1 ELSE 0 END,
    t.positive_samples,
    t.negative_samples,
    1,
    t.created_at,
    t.updated_at,
    COALESCE(NULLIF(t.updated_by, ''), 'imported'),
    COALESCE(NULLIF(t.updated_by, ''), 'imported')
FROM {p}message_templates t
WHERE t.status <> 'archived';
