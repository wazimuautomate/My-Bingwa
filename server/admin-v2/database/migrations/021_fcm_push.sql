-- FCM push notifications: store FCM device registration token per customer
-- and create audit / history table for push notification broadcasts.

ALTER TABLE {p}customers ADD COLUMN fcm_token VARCHAR(255) DEFAULT NULL;
CREATE INDEX idx_customer_fcm_token ON {p}customers (fcm_token);

CREATE TABLE IF NOT EXISTS {p}push_broadcasts (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(120) NOT NULL,
    body            TEXT         NOT NULL,
    deep_link_route VARCHAR(32)  NOT NULL DEFAULT 'notifications',
    recipients_count INT         NOT NULL DEFAULT 0,
    success_count   INT          NOT NULL DEFAULT 0,
    failure_count   INT          NOT NULL DEFAULT 0,
    created_by      VARCHAR(64)  NOT NULL DEFAULT 'admin',
    created_at      DATETIME     NOT NULL,
    KEY idx_push_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
