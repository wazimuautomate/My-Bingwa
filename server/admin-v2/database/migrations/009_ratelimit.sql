-- Lightweight fixed-window rate limiting for the public sync API (no APCu dependency).
CREATE TABLE IF NOT EXISTS {p}rate_limits (
    rl_key       VARCHAR(80) NOT NULL PRIMARY KEY,   -- e.g. "sync:1.2.3.4:29123456"
    hits         INT NOT NULL DEFAULT 0,
    window_start INT NOT NULL DEFAULT 0,             -- unix minute bucket
    KEY idx_rl_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
