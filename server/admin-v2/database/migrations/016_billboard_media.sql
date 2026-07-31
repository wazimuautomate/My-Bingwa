-- Billboard media upgrade: animated GIF / WEBP support, generated thumbnails, explicit
-- display order and a declared target action (offer, category, internal screen or URL).
--
-- Existing columns keep their meaning: `headline` is the title and `body` is the
-- description, so no shipped app field is renamed or removed.

ALTER TABLE {p}billboards
    ADD COLUMN media_type      VARCHAR(12) NOT NULL DEFAULT 'none',  -- none|image|gif
    ADD COLUMN thumb_asset_id  INT NULL,
    ADD COLUMN display_order   INT NOT NULL DEFAULT 0,
    ADD COLUMN target_action   VARCHAR(24) NOT NULL DEFAULT 'none',  -- none|offer|category|url|internal
    ADD COLUMN click_url       VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN internal_action VARCHAR(60) NOT NULL DEFAULT '',
    ADD COLUMN target_category VARCHAR(16) NOT NULL DEFAULT '',
    ADD COLUMN enabled         TINYINT NOT NULL DEFAULT 1;
-- @@
ALTER TABLE {p}billboards ADD KEY idx_bb_order (display_order);
-- @@
ALTER TABLE {p}billboard_assets
    ADD COLUMN kind        VARCHAR(12) NOT NULL DEFAULT 'image',     -- image|gif
    ADD COLUMN is_animated TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN frame_count INT NOT NULL DEFAULT 1,
    ADD COLUMN thumb_name  VARCHAR(120) NOT NULL DEFAULT '';
-- @@
-- Existing rows: a billboard that already carries an image is an image billboard, and a
-- linked offer already implies an "open this offer" tap target.
UPDATE {p}billboards SET media_type = 'image' WHERE image_asset_id IS NOT NULL;
-- @@
UPDATE {p}billboards SET target_action = 'offer'
 WHERE target_action = 'none' AND linked_offer_id IS NOT NULL AND linked_offer_id <> '';
-- @@
UPDATE {p}billboards SET display_order = priority WHERE display_order = 0;
