-- Authentication, roles and permissions. All tables prefixed with {p} (default mb_).
CREATE TABLE IF NOT EXISTS {p}admin_users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    is_super_admin  TINYINT NOT NULL DEFAULT 0,
    status          TINYINT NOT NULL DEFAULT 1,          -- 1 active, 0 disabled
    totp_enabled    TINYINT NOT NULL DEFAULT 0,
    totp_secret     VARCHAR(255) NOT NULL DEFAULT '',     -- AES-GCM encrypted at rest
    recovery_codes  TEXT NULL,                            -- JSON array of password_hash()ed codes
    failed_attempts INT NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role_key    VARCHAR(48) NOT NULL,
    name        VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    is_system   TINYINT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL,
    UNIQUE KEY uniq_role_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}permissions (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    perm_key  VARCHAR(48) NOT NULL,
    perm_group VARCHAR(48) NOT NULL DEFAULT '',
    label     VARCHAR(120) NOT NULL,
    UNIQUE KEY uniq_perm_key (perm_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}role_permissions (
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_perm (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}admin_user_roles (
    admin_user_id INT NOT NULL,
    role_id       INT NOT NULL,
    PRIMARY KEY (admin_user_id, role_id),
    KEY idx_aur_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}admin_sessions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    session_id    VARCHAR(128) NOT NULL,
    ip            VARCHAR(45) NOT NULL DEFAULT '',
    user_agent    VARCHAR(255) NOT NULL DEFAULT '',
    created_at    DATETIME NOT NULL,
    last_seen_at  DATETIME NOT NULL,
    UNIQUE KEY uniq_session (session_id),
    KEY idx_sess_user (admin_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- @@
CREATE TABLE IF NOT EXISTS {p}login_attempts (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(190) NOT NULL DEFAULT '',
    ip         VARCHAR(45) NOT NULL DEFAULT '',
    success    TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_la_email (email),
    KEY idx_la_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
