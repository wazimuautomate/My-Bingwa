-- Offer categories and feature flags become data instead of constants.
--
-- The app used to compile the four category tabs and every on/off switch into the APK.
-- Both are now published configuration: an administrator can rename a category, reorder
-- the tabs or turn a capability off without an app release.

CREATE TABLE IF NOT EXISTS {p}offer_categories (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_key VARCHAR(16) NOT NULL,                  -- DATA|SMS|MINUTES|SPECIAL|...
    label        VARCHAR(40) NOT NULL DEFAULT '',       -- tab label in the app
    description  VARCHAR(160) NOT NULL DEFAULT '',
    accent       VARCHAR(16) NOT NULL DEFAULT '',       -- design token name, never a raw hex
    sort_order   INT NOT NULL DEFAULT 100,
    enabled      TINYINT NOT NULL DEFAULT 1,
    is_system    TINYINT NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL,
    updated_at   DATETIME NOT NULL,
    UNIQUE KEY uniq_offer_cat (category_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}feature_flags (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    flag_key    VARCHAR(48) NOT NULL,
    label       VARCHAR(80) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    enabled     TINYINT NOT NULL DEFAULT 1,
    is_system   TINYINT NOT NULL DEFAULT 0,
    sort_order  INT NOT NULL DEFAULT 100,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    UNIQUE KEY uniq_flag (flag_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
INSERT IGNORE INTO {p}offer_categories (category_key, label, description, accent, sort_order, enabled, is_system, created_at, updated_at) VALUES
    ('DATA',    'Data',    'Data bundles.',              'info',      10, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ('SMS',     'SMS',     'SMS bundles.',               'primary',   20, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ('MINUTES', 'Minutes', 'Voice minute bundles.',      'primary',   30, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ('SPECIAL', 'Special', 'Special and promotional offers.', 'promo', 40, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
-- @@
INSERT IGNORE INTO {p}feature_flags (flag_key, label, description, enabled, is_system, sort_order, created_at, updated_at) VALUES
    ('offline_purchase',   'Offline purchase',    'Show cached M-Pesa instructions when the device is offline.', 1, 1, 10, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ('billboards',         'Billboard adverts',   'Show the advert carousel on Home.',                           1, 1, 20, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ('sms_rules',          'SMS awareness',       'Let the app understand Safaricom messages using SMS rules.',  1, 1, 30, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ('local_notifications','Local notifications', 'Allow locally scheduled notification templates.',             1, 1, 40, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ('promotions',         'Promotions',          'Allow promotional messaging and promo styling.',              1, 1, 50, UTC_TIMESTAMP(), UTC_TIMESTAMP());
