-- Immutable published configuration releases + sync telemetry.
CREATE TABLE IF NOT EXISTS {p}configuration_releases (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    version         INT NOT NULL,                         -- incrementing published config version
    schema_version  INT NOT NULL DEFAULT 1,
    snapshot_json   MEDIUMTEXT NOT NULL,                  -- canonical JSON, app-safe published data
    checksum        VARCHAR(80) NOT NULL,                 -- sha256 of canonical snapshot
    signature       TEXT NULL,                            -- base64 RSA/EC signature, null if unsigned
    signature_algo  VARCHAR(16) NOT NULL DEFAULT '',
    min_client_version_code INT NOT NULL DEFAULT 1,
    published_by    VARCHAR(120) NOT NULL DEFAULT '',
    published_by_id INT NULL,
    notes           VARCHAR(500) NOT NULL DEFAULT '',
    rolled_back_from INT NULL,                            -- source version if this is a rollback
    created_at      DATETIME NOT NULL,
    UNIQUE KEY uniq_version (version),
    KEY idx_rel_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}configuration_release_items (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    release_id    INT NOT NULL,
    version       INT NOT NULL,
    entity_type   VARCHAR(40) NOT NULL,                   -- offer|billboard|template|support|app_config|version
    entity_id     VARCHAR(64) NOT NULL DEFAULT '',
    change_type   VARCHAR(16) NOT NULL DEFAULT 'unchanged', -- added|changed|removed|unchanged
    summary       VARCHAR(255) NOT NULL DEFAULT '',
    KEY idx_cri_release (release_id),
    KEY idx_cri_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}sync_events (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    install_id    VARCHAR(64) NOT NULL DEFAULT '',        -- anonymous installation id
    version_code  INT NULL,                               -- reporting app versionCode
    config_version INT NULL,                              -- config version the device holds
    event_type    VARCHAR(24) NOT NULL,                   -- manifest|snapshot|not_modified|error
    result_code   VARCHAR(16) NOT NULL DEFAULT '',
    created_at    DATETIME NOT NULL,
    KEY idx_se_type (event_type),
    KEY idx_se_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}anonymous_app_versions (
    install_id     VARCHAR(64) NOT NULL PRIMARY KEY,      -- anonymous installation id
    version_code   INT NULL,
    config_version INT NULL,
    last_seen_at   DATETIME NOT NULL,
    KEY idx_aav_config (config_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}settings (
    skey       VARCHAR(64) PRIMARY KEY,
    svalue     TEXT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
