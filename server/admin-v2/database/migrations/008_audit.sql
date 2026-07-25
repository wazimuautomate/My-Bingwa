-- Append-only audit trail. No UPDATE/DELETE is ever issued against this table.
CREATE TABLE IF NOT EXISTS {p}audit_logs (
    id             BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_id       INT NULL,
    actor_name     VARCHAR(120) NOT NULL DEFAULT '',
    actor_role     VARCHAR(40) NOT NULL DEFAULT '',
    action         VARCHAR(80) NOT NULL,
    entity_type    VARCHAR(60) NOT NULL DEFAULT '',
    entity_id      VARCHAR(64) NOT NULL DEFAULT '',
    before_json    MEDIUMTEXT NULL,
    after_json     MEDIUMTEXT NULL,
    diff_json      MEDIUMTEXT NULL,
    reason         VARCHAR(500) NULL,
    release_version INT NULL,
    ip             VARCHAR(45) NOT NULL DEFAULT '',
    user_agent     VARCHAR(255) NOT NULL DEFAULT '',
    success        TINYINT NOT NULL DEFAULT 1,
    created_at     DATETIME NOT NULL,
    KEY idx_audit_actor (actor_id),
    KEY idx_audit_action (action),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
