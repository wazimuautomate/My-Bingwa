-- Optional: notification / SMS-recognition templates (the admin panel also creates
-- this table automatically). These mirror the app's built-in Safaricom patterns and
-- are here so you can adjust wording from the admin if Safaricom changes its SMS.

CREATE TABLE IF NOT EXISTS templates (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    tkey    VARCHAR(48) NOT NULL,
    label   VARCHAR(80) NOT NULL,
    pattern VARCHAR(255) NOT NULL,
    active  TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO templates (tkey, label, pattern, active) VALUES
    ('delivery_data',    'Data delivery',    'You have received %s of data', 1),
    ('delivery_sms',     'SMS delivery',     'You have received %s SMS',      1),
    ('delivery_minutes', 'Minutes delivery', 'You have received %s minutes',  1),
    ('low_balance',      'Low balance',      'Your %s balance is running low', 1);
