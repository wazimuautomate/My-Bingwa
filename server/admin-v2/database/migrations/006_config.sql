-- Support/payment public details, remote app configuration and app version rules.
-- Single-row config tables use id=1.
CREATE TABLE IF NOT EXISTS {p}support_config (
    id                     INT PRIMARY KEY DEFAULT 1,
    till_number            VARCHAR(24) NOT NULL DEFAULT '',
    paybill_number         VARCHAR(24) NOT NULL DEFAULT '',
    support_number         VARCHAR(24) NOT NULL DEFAULT '',
    support_whatsapp       VARCHAR(24) NOT NULL DEFAULT '',
    offline_self_instructions   VARCHAR(500) NOT NULL DEFAULT '',
    offline_other_instructions  VARCHAR(500) NOT NULL DEFAULT '',
    support_banner         VARCHAR(255) NOT NULL DEFAULT '',
    working_hours          VARCHAR(120) NOT NULL DEFAULT '',
    row_version            INT NOT NULL DEFAULT 1,
    updated_at             DATETIME NOT NULL,
    updated_by             VARCHAR(120) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}app_config (
    id                     INT PRIMARY KEY DEFAULT 1,
    maintenance_mode       TINYINT NOT NULL DEFAULT 0,
    maintenance_title      VARCHAR(120) NOT NULL DEFAULT '',
    maintenance_message    VARCHAR(500) NOT NULL DEFAULT '',
    maintenance_allow_help TINYINT NOT NULL DEFAULT 1,
    sync_interval_minutes  INT NOT NULL DEFAULT 360,      -- clamped 60..1440
    snapshot_cache_hours   INT NOT NULL DEFAULT 168,
    offline_config_valid_hours INT NOT NULL DEFAULT 168,
    quiet_hours_start      VARCHAR(5) NOT NULL DEFAULT '21:00',
    quiet_hours_end        VARCHAR(5) NOT NULL DEFAULT '07:00',
    campaign_daily_cap     INT NOT NULL DEFAULT 2,
    feature_flags_json     TEXT NULL,                     -- {"sms_parsing":false,...}
    personalisation_json   TEXT NULL,                     -- scoring weights within validated bounds
    emergency_disable_json TEXT NULL,                     -- {"offers":[],"campaigns":[],"routes":[]}
    row_version            INT NOT NULL DEFAULT 1,
    updated_at             DATETIME NOT NULL,
    updated_by             VARCHAR(120) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}app_versions (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    latest_version_code    INT NOT NULL,
    latest_version_name    VARCHAR(24) NOT NULL,
    min_supported_version_code INT NOT NULL DEFAULT 1,
    mandatory              TINYINT NOT NULL DEFAULT 0,
    play_store_url         VARCHAR(200) NOT NULL DEFAULT '',
    apk_url                VARCHAR(200) NOT NULL DEFAULT '',
    apk_sha256             VARCHAR(80) NOT NULL DEFAULT '',
    rollout_percent        INT NOT NULL DEFAULT 100,
    release_notes          VARCHAR(1000) NOT NULL DEFAULT '',
    status                 VARCHAR(12) NOT NULL DEFAULT 'active', -- active|inactive
    row_version            INT NOT NULL DEFAULT 1,
    created_at             DATETIME NOT NULL,
    updated_at             DATETIME NOT NULL,
    updated_by             VARCHAR(120) NOT NULL DEFAULT '',
    KEY idx_ver_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
