-- The customer register.
--
-- The app collects a name and a Safaricom number during onboarding and keeps them
-- on the phone. This table is the seller's own copy, submitted ONCE per install so
-- the owner knows who their customers are. It is deliberately small: a name, a
-- number, and when the app first and last announced itself. No purchase history,
-- no behaviour, nothing derived — the phone keeps all of that (CLAUDE.md §10).
--
-- msisdn is the natural key in canonical 2547XXXXXXXX form, so a customer who
-- reinstalls updates their row instead of creating a second one.

CREATE TABLE IF NOT EXISTS {p}customers (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    msisdn       VARCHAR(16)  NOT NULL,           -- canonical 2547XXXXXXXX
    name         VARCHAR(80)  NOT NULL DEFAULT '',
    app_version  VARCHAR(24)  NOT NULL DEFAULT '',
    -- Registrations seen for this number. A reinstall bumps it rather than adding
    -- a row, which is also the honest way to read "how many customers do we have".
    registrations INT         NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL,
    updated_at   DATETIME     NOT NULL,
    UNIQUE KEY uniq_customer_msisdn (msisdn),
    KEY idx_customer_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
