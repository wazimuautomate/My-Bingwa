-- Notification / SMS-recognition templates. The admin panel's "Load app defaults"
-- button seeds these for you; this file is the phpMyAdmin equivalent. Mirrors the
-- app's DefaultTemplates.SEED (real Safaricom / Bingwa Sokoni message formats).

CREATE TABLE IF NOT EXISTS templates (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    tkey      VARCHAR(48)  NOT NULL,
    label     VARCHAR(80)  NOT NULL,
    ttype     VARCHAR(16)  NOT NULL DEFAULT 'delivery',   -- 'delivery' | 'low_balance'
    sender_id VARCHAR(32)  NOT NULL DEFAULT '',
    category  VARCHAR(16)  NOT NULL DEFAULT 'DATA',        -- DATA | SMS | MINUTES | SPECIAL
    pattern   VARCHAR(255) NOT NULL,                       -- case-insensitive regex
    active    TINYINT      NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO templates (tkey, label, ttype, sender_id, category, pattern, active) VALUES
    ('data_bingwa_sokoni', 'Bingwa Sokoni data delivery', 'delivery',    'Safaricom',   'DATA',    'received\\b.*?\\d+\\s*(?:MB|GB).*?from\\s+Bingwa\\s+Sokoni', 1),
    ('sms_daily_bundle',   'SMS bundle delivery',         'delivery',    'Safaricom',   'SMS',     'received\\s+\\d+\\s+SMS', 1),
    ('minutes_gift',       'Minutes gift delivery',       'delivery',    'SAF_OfaMOTO', 'MINUTES', 'received\\s+a\\s+gift\\s+of.*?\\d+\\s*Mins', 1),
    ('data_low_balance',   'Data low balance',            'low_balance', 'SAF_Balance', 'DATA',    'data\\s+balance\\s+is\\s+below', 1);
