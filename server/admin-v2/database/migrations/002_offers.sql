-- Offers catalogue (working state) + immutable per-save revisions.
CREATE TABLE IF NOT EXISTS {p}offers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    offer_id        VARCHAR(48) NOT NULL,                 -- stable app-facing id, e.g. data_6
    category        VARCHAR(16) NOT NULL DEFAULT 'DATA',  -- DATA|SMS|MINUTES|SPECIAL
    name            VARCHAR(80) NOT NULL,
    price           INT NOT NULL,
    validity        VARCHAR(48) NOT NULL DEFAULT '',
    band            VARCHAR(16) NOT NULL DEFAULT 'Daily',
    daily_rule      VARCHAR(28) NOT NULL DEFAULT 'MULTIPLE_PER_DAY', -- MULTIPLE_PER_DAY|ONCE_PER_RECIPIENT_PER_DAY|MAX_PER_RECIPIENT_PER_DAY
    max_per_day     INT NULL,
    commercial_tag  VARCHAR(40) NOT NULL DEFAULT '',      -- e.g. Best value
    offline_eligible TINYINT NOT NULL DEFAULT 1,
    restrictions    VARCHAR(255) NOT NULL DEFAULT '',
    status          VARCHAR(12) NOT NULL DEFAULT 'active', -- active|draft|archived
    starts_at       DATETIME NULL,
    ends_at         DATETIME NULL,
    sort_hint       INT NOT NULL DEFAULT 0,
    row_version     INT NOT NULL DEFAULT 1,               -- optimistic locking
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    updated_by      VARCHAR(120) NOT NULL DEFAULT '',
    UNIQUE KEY uniq_offer_id (offer_id),
    KEY idx_offer_status (status),
    KEY idx_offer_cat (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}offer_revisions (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    offer_id    VARCHAR(48) NOT NULL,
    snapshot_json TEXT NOT NULL,
    action      VARCHAR(16) NOT NULL,                     -- create|update|archive|restore|delete
    actor_name  VARCHAR(120) NOT NULL DEFAULT '',
    created_at  DATETIME NOT NULL,
    KEY idx_orev_offer (offer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
