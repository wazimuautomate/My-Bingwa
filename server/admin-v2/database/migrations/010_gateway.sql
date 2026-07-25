-- Server-side payment/delivery gateway settings, editable from the admin dashboard.
-- SECRETS (SMS API key) are stored AES-GCM encrypted. These values drive the PAYMENT
-- SERVER (stk.php etc.), NOT the app, and are NEVER placed in the app sync snapshot.
CREATE TABLE IF NOT EXISTS {p}gateway_config (
    id                  INT PRIMARY KEY DEFAULT 1,
    daraja_env          VARCHAR(16) NOT NULL DEFAULT 'production',   -- sandbox|production
    -- Buy-for-myself (Till / Buy Goods) STK route
    self_transaction_type VARCHAR(30) NOT NULL DEFAULT 'CustomerBuyGoodsOnline',
    business_shortcode  VARCHAR(16) NOT NULL DEFAULT '',   -- Head Office/store number used in the STK password
    party_b             VARCHAR(16) NOT NULL DEFAULT '',   -- the Till number that RECEIVES the money
    -- Buy-for-another (Paybill) STK route
    paybill_shortcode   VARCHAR(16) NOT NULL DEFAULT '',   -- Paybill number (BusinessShortCode == PartyB)
    callback_url        VARCHAR(200) NOT NULL DEFAULT '',
    -- Fulfilment SMS (buy-for-another signal)
    fulfilment_phone    VARCHAR(20) NOT NULL DEFAULT '',
    business_name       VARCHAR(40) NOT NULL DEFAULT 'MyBingwa',
    sms_api_url         VARCHAR(200) NOT NULL DEFAULT '',
    sms_sender_id       VARCHAR(24) NOT NULL DEFAULT '',
    sms_api_key_enc     TEXT NULL,                          -- AES-GCM encrypted at rest
    row_version         INT NOT NULL DEFAULT 1,
    updated_at          DATETIME NOT NULL,
    updated_by          VARCHAR(120) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
