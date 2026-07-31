-- Notification management redesign.
--
-- A notification is now a rule the app evaluates locally: a category, a trigger, an
-- optional schedule window and one or more wording variations. The app picks a variation
-- at random and substitutes {{variables}} on-device, so no personal data ever reaches the
-- server. Categories, triggers and variables are catalogue rows, not PHP constants.

ALTER TABLE {p}notification_campaigns MODIFY COLUMN category VARCHAR(40) NOT NULL DEFAULT '';
-- @@
ALTER TABLE {p}notification_campaigns
    ADD COLUMN trigger_type       VARCHAR(32) NOT NULL DEFAULT 'manual',
    ADD COLUMN trigger_event      VARCHAR(40) NOT NULL DEFAULT '',   -- SMS event key when trigger_type='sms_event'
    ADD COLUMN starts_on          DATE NULL,
    ADD COLUMN ends_on            DATE NULL,
    ADD COLUMN days_of_week       VARCHAR(20) NOT NULL DEFAULT '',   -- '' = every day, else '1,2,3' (Mon=1)
    ADD COLUMN allowed_time_start VARCHAR(5) NOT NULL DEFAULT '',    -- 'HH:MM' Africa/Nairobi, '' = any time
    ADD COLUMN allowed_time_end   VARCHAR(5) NOT NULL DEFAULT '',
    ADD COLUMN cooldown_minutes   INT NOT NULL DEFAULT 0,            -- minimum gap between two showings
    ADD COLUMN enabled            TINYINT NOT NULL DEFAULT 1,
    ADD COLUMN notes              VARCHAR(255) NOT NULL DEFAULT '';
-- @@
ALTER TABLE {p}notification_campaigns ADD KEY idx_nc_trigger (trigger_type);
-- @@
CREATE TABLE IF NOT EXISTS {p}notification_variations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    title       VARCHAR(120) NOT NULL DEFAULT '',
    body        VARCHAR(255) NOT NULL DEFAULT '',
    sort_order  INT NOT NULL DEFAULT 0,
    enabled     TINYINT NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    KEY idx_nvar_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}notification_categories (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_key VARCHAR(40) NOT NULL,
    label        VARCHAR(80) NOT NULL DEFAULT '',
    description  VARCHAR(255) NOT NULL DEFAULT '',
    is_system    TINYINT NOT NULL DEFAULT 0,
    enabled      TINYINT NOT NULL DEFAULT 1,
    sort_order   INT NOT NULL DEFAULT 100,
    created_at   DATETIME NOT NULL,
    UNIQUE KEY uniq_ncat (category_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}notification_trigger_types (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    trigger_key  VARCHAR(32) NOT NULL,
    label        VARCHAR(80) NOT NULL DEFAULT '',
    description  VARCHAR(255) NOT NULL DEFAULT '',
    needs_event  TINYINT NOT NULL DEFAULT 0,           -- requires an SMS event key
    enabled      TINYINT NOT NULL DEFAULT 1,
    sort_order   INT NOT NULL DEFAULT 100,
    created_at   DATETIME NOT NULL,
    UNIQUE KEY uniq_ntrig (trigger_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}notification_variables (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    var_key      VARCHAR(40) NOT NULL,                 -- first_name -> {{first_name}}
    label        VARCHAR(80) NOT NULL DEFAULT '',
    description  VARCHAR(255) NOT NULL DEFAULT '',
    sample_value VARCHAR(80) NOT NULL DEFAULT '',
    enabled      TINYINT NOT NULL DEFAULT 1,
    sort_order   INT NOT NULL DEFAULT 100,
    created_at   DATETIME NOT NULL,
    UNIQUE KEY uniq_nvarkey (var_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
INSERT IGNORE INTO {p}notification_categories (category_key, label, description, is_system, sort_order, created_at) VALUES
    ('SUPPORT',          'Support',          'Help and how to reach the seller.',            1, 10,  UTC_TIMESTAMP()),
    ('OFFLINE',          'Offline',          'Shown when the device has no internet.',       1, 20,  UTC_TIMESTAMP()),
    ('ONLINE',           'Online',           'Shown when the device comes back online.',     1, 30,  UTC_TIMESTAMP()),
    ('MORNING',          'Morning',          'Morning time-of-day message.',                 1, 40,  UTC_TIMESTAMP()),
    ('AFTERNOON',        'Afternoon',        'Afternoon time-of-day message.',               1, 50,  UTC_TIMESTAMP()),
    ('EVENING',          'Evening',          'Evening time-of-day message.',                 1, 60,  UTC_TIMESTAMP()),
    ('NIGHT',            'Night',            'Night time-of-day message.',                   1, 70,  UTC_TIMESTAMP()),
    ('PROMOTION',        'Promotion',        'Promotional message. Always optional.',        1, 80,  UTC_TIMESTAMP()),
    ('PURCHASE_SUCCESS', 'Purchase success', 'Payment received for an order.',               1, 90,  UTC_TIMESTAMP()),
    ('BUNDLE_RECEIVED',  'Bundle received',  'A recognised delivery message arrived.',       1, 100, UTC_TIMESTAMP()),
    ('LOW_DATA',         'Low data',         'Data balance is getting low.',                 1, 110, UTC_TIMESTAMP()),
    ('VERY_LOW_DATA',    'Very low data',    'Data balance is nearly finished.',             1, 120, UTC_TIMESTAMP()),
    ('NO_DATA',          'No data',          'No active data bundle.',                       1, 130, UTC_TIMESTAMP()),
    ('LOW_MINUTES',      'Low minutes',      'Minutes balance is getting low.',              1, 140, UTC_TIMESTAMP()),
    ('LOW_SMS',          'Low SMS',          'SMS balance is getting low.',                  1, 150, UTC_TIMESTAMP()),
    ('GIFT_RECEIVED',    'Gift received',    'A gifted bundle arrived.',                     1, 160, UTC_TIMESTAMP()),
    ('GENERAL',          'General',          'Anything that does not fit another category.', 1, 170, UTC_TIMESTAMP()),
    ('SYSTEM',           'System',           'App updates, maintenance and service notices.',1, 180, UTC_TIMESTAMP());
-- @@
INSERT IGNORE INTO {p}notification_trigger_types (trigger_key, label, description, needs_event, sort_order, created_at) VALUES
    ('manual',         'Manual',         'Only shown when the administrator sends it.',                 0, 10, UTC_TIMESTAMP()),
    ('offline',        'Offline',        'The device lost internet access.',                            0, 20, UTC_TIMESTAMP()),
    ('online',         'Online',         'The device regained internet access.',                        0, 30, UTC_TIMESTAMP()),
    ('sms_event',      'SMS event',      'An SMS rule matched a message on the device.',                1, 40, UTC_TIMESTAMP()),
    ('purchase_event', 'Purchase event', 'A payment reached its final state.',                          0, 50, UTC_TIMESTAMP()),
    ('bundle_expiry',  'Bundle expiry',  'A purchased bundle is close to its validity end.',            0, 60, UTC_TIMESTAMP()),
    ('time_based',     'Time based',     'Shown inside the configured days and time window.',           0, 70, UTC_TIMESTAMP()),
    ('promotion',      'Promotion',      'Tied to a promotion or billboard campaign window.',           0, 80, UTC_TIMESTAMP());
-- @@
INSERT IGNORE INTO {p}notification_variables (var_key, label, description, sample_value, sort_order, created_at) VALUES
    ('first_name',        'First name',        'The name the customer entered on this device.', 'James',      10, UTC_TIMESTAMP()),
    ('bundle_name',       'Bundle name',       'The most recent or relevant bundle name.',      '2GB',        20, UTC_TIMESTAMP()),
    ('remaining_data',    'Remaining data',    'Remaining data read from a balance message.',   '75MB',       30, UTC_TIMESTAMP()),
    ('time_of_day',       'Time of day',       'Morning, afternoon, evening or night.',         'Morning',    40, UTC_TIMESTAMP()),
    ('recommended_offer', 'Recommended offer', 'An offer the app picks locally.',               '1GB @ KSh 19',50, UTC_TIMESTAMP());
-- @@
-- 'scheduled' was the old "this will go out" state. In the rule-based model that is simply
-- an active rule the app may show when its trigger and window allow it.
UPDATE {p}notification_campaigns SET status = 'active' WHERE status = 'scheduled';
