-- Billboard adverts (simple + advanced), assets and privacy-safe events.
CREATE TABLE IF NOT EXISTS {p}billboards (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,                -- internal name
    kind            VARCHAR(12) NOT NULL DEFAULT 'simple',-- simple|advanced
    status          VARCHAR(12) NOT NULL DEFAULT 'draft', -- draft|scheduled|active|paused|expired|archived
    priority        INT NOT NULL DEFAULT 5,
    linked_offer_id VARCHAR(48) NULL,
    tag             VARCHAR(40) NOT NULL DEFAULT '',
    headline        VARCHAR(120) NOT NULL DEFAULT '',
    body            VARCHAR(255) NOT NULL DEFAULT '',
    cta_label       VARCHAR(40) NOT NULL DEFAULT 'Buy now',
    cta_destination VARCHAR(120) NOT NULL DEFAULT '',     -- app deep link
    image_asset_id  INT NULL,
    alt_text        VARCHAR(160) NOT NULL DEFAULT '',
    audience_rule   VARCHAR(40) NOT NULL DEFAULT 'all',   -- all|category:DATA|new_users|...
    frequency_cap   INT NOT NULL DEFAULT 0,               -- max impressions/day/device, 0 = unlimited
    starts_at       DATETIME NULL,
    ends_at         DATETIME NULL,
    row_version     INT NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    updated_by      VARCHAR(120) NOT NULL DEFAULT '',
    KEY idx_bb_status (status),
    KEY idx_bb_offer (linked_offer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}billboard_revisions (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    billboard_id  INT NOT NULL,
    snapshot_json TEXT NOT NULL,
    action        VARCHAR(16) NOT NULL,
    actor_name    VARCHAR(120) NOT NULL DEFAULT '',
    created_at    DATETIME NOT NULL,
    KEY idx_bbrev_bb (billboard_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}billboard_assets (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    stored_name   VARCHAR(120) NOT NULL,                  -- random filename on disk
    original_name VARCHAR(160) NOT NULL DEFAULT '',
    mime          VARCHAR(40) NOT NULL DEFAULT '',
    width         INT NOT NULL DEFAULT 0,
    height        INT NOT NULL DEFAULT 0,
    bytes         INT NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL,
    UNIQUE KEY uniq_stored (stored_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}billboard_events (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    billboard_id INT NOT NULL,
    install_id   VARCHAR(64) NOT NULL DEFAULT '',         -- anonymous installation id, never a phone number
    event_type   VARCHAR(16) NOT NULL,                    -- impression|click|purchase|dismiss
    created_at   DATETIME NOT NULL,
    KEY idx_bbe_bb (billboard_id),
    KEY idx_bbe_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
