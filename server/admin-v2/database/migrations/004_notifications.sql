-- Notification campaigns, reusable copy templates and delivery events.
CREATE TABLE IF NOT EXISTS {p}notification_campaigns (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,                -- internal campaign name
    type            VARCHAR(28) NOT NULL DEFAULT 'manual',-- manual|scheduled|new_offer|app_update|personalised|payment
    title           VARCHAR(120) NOT NULL DEFAULT '',
    body            VARCHAR(255) NOT NULL DEFAULT '',
    image_asset_id  INT NULL,
    deep_link       VARCHAR(120) NOT NULL DEFAULT '',
    audience_rule   VARCHAR(40) NOT NULL DEFAULT 'all',
    linked_offer_id VARCHAR(48) NULL,
    category        VARCHAR(16) NOT NULL DEFAULT '',
    scheduled_at    DATETIME NULL,
    expires_at      DATETIME NULL,
    priority        VARCHAR(12) NOT NULL DEFAULT 'normal',-- low|normal|high
    respect_quiet_hours TINYINT NOT NULL DEFAULT 1,
    frequency_cap   INT NOT NULL DEFAULT 1,
    suppress_recent_purchase TINYINT NOT NULL DEFAULT 1,
    status          VARCHAR(12) NOT NULL DEFAULT 'draft', -- draft|scheduled|sent|cancelled
    row_version     INT NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    updated_by      VARCHAR(120) NOT NULL DEFAULT '',
    KEY idx_nc_status (status),
    KEY idx_nc_sched (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}notification_templates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(48) NOT NULL,
    label       VARCHAR(120) NOT NULL,
    title       VARCHAR(120) NOT NULL DEFAULT '',
    body        VARCHAR(255) NOT NULL DEFAULT '',
    category    VARCHAR(16) NOT NULL DEFAULT '',
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    UNIQUE KEY uniq_ntpl_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}notification_events (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    campaign_id  INT NOT NULL,
    event_type   VARCHAR(16) NOT NULL,                    -- sent|delivered|opened|converted|cancelled|test
    count        INT NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL,
    KEY idx_ne_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
