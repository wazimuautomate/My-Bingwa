-- Safaricom message recognition: sender IDs, templates and immutable revisions.
CREATE TABLE IF NOT EXISTS {p}message_sender_ids (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    VARCHAR(40) NOT NULL,                    -- e.g. Safaricom, SAF_Balance
    normalised   VARCHAR(40) NOT NULL DEFAULT '',         -- uppercased/trimmed form used for matching
    note         VARCHAR(160) NOT NULL DEFAULT '',
    created_at   DATETIME NOT NULL,
    UNIQUE KEY uniq_sender (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}message_templates (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    template_key    VARCHAR(48) NOT NULL,                 -- stable key
    label           VARCHAR(120) NOT NULL,
    sender_id       VARCHAR(40) NOT NULL DEFAULT '',
    purpose         VARCHAR(16) NOT NULL DEFAULT 'delivery', -- delivery|low_balance|very_low_balance
    category        VARCHAR(16) NOT NULL DEFAULT 'DATA',
    pattern_type    VARCHAR(12) NOT NULL DEFAULT 'regex', -- regex|token
    pattern         VARCHAR(500) NOT NULL,
    case_sensitive  TINYINT NOT NULL DEFAULT 0,
    captures_json   TEXT NULL,                            -- {amount:1, allowance:2, validity:3, expiry:4}
    match_priority  INT NOT NULL DEFAULT 5,
    correlation_window_min INT NOT NULL DEFAULT 30,
    positive_samples TEXT NULL,                           -- JSON array
    negative_samples TEXT NULL,                           -- JSON array
    status          VARCHAR(12) NOT NULL DEFAULT 'draft', -- active|draft|archived
    row_version     INT NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    updated_by      VARCHAR(120) NOT NULL DEFAULT '',
    UNIQUE KEY uniq_mtpl_key (template_key),
    KEY idx_mtpl_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}message_template_revisions (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    template_key  VARCHAR(48) NOT NULL,
    snapshot_json TEXT NOT NULL,
    action        VARCHAR(16) NOT NULL,
    actor_name    VARCHAR(120) NOT NULL DEFAULT '',
    created_at    DATETIME NOT NULL,
    KEY idx_mtrev_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
