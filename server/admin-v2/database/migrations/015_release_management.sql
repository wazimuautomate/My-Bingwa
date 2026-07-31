-- Release management: per-resource versions and field-level change records.
--
-- One global config version still identifies a release. In addition, every synchronisable
-- resource carries its own version, which only moves when that resource's published bytes
-- actually change. A device compares versions and downloads nothing it already holds.
--
-- release_field_changes is what makes Preview honest: "Price updated" instead of
-- "Offer modified". Rows are written at publish time and are never mutated.

CREATE TABLE IF NOT EXISTS {p}resource_versions (
    resource_key VARCHAR(40) NOT NULL PRIMARY KEY,     -- offers, billboards, smsRules, ...
    version      INT NOT NULL DEFAULT 0,               -- config version this resource last changed at
    checksum     VARCHAR(80) NOT NULL DEFAULT '',      -- sha256 of the canonical resource JSON
    item_count   INT NOT NULL DEFAULT 0,
    updated_at   DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}release_field_changes (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    release_id  INT NOT NULL,
    version     INT NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id   VARCHAR(64) NOT NULL DEFAULT '',
    field       VARCHAR(64) NOT NULL DEFAULT '',
    field_label VARCHAR(80) NOT NULL DEFAULT '',
    old_value   VARCHAR(500) NULL,
    new_value   VARCHAR(500) NULL,
    KEY idx_rfc_release (release_id),
    KEY idx_rfc_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
ALTER TABLE {p}configuration_releases
    ADD COLUMN release_uid            VARCHAR(40) NOT NULL DEFAULT '',
    ADD COLUMN change_count           INT NOT NULL DEFAULT 0,
    ADD COLUMN resource_versions_json TEXT NULL;
-- @@
ALTER TABLE {p}configuration_release_items
    ADD COLUMN entity_label VARCHAR(160) NOT NULL DEFAULT '',
    ADD COLUMN fields_json  TEXT NULL;
-- @@
-- Audit filtering by module without parsing the action string in SQL.
ALTER TABLE {p}audit_logs ADD COLUMN module VARCHAR(40) NOT NULL DEFAULT '';
-- @@
ALTER TABLE {p}audit_logs ADD KEY idx_audit_module (module);
-- @@
UPDATE {p}audit_logs SET module = SUBSTRING_INDEX(action, '.', 1) WHERE module = '';
-- @@
-- Which resource a device asked for, so sync traffic can be understood without user data.
ALTER TABLE {p}sync_events ADD COLUMN resource VARCHAR(40) NOT NULL DEFAULT '';
