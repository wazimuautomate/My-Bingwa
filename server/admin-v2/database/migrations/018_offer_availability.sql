-- Time-of-day selling windows for offers.
--
-- Safaricom restricts some bundles to a slot of the day (e.g. sold only between
-- 17:00 and 23:00). Buying one outside its slot fails at the carrier after the
-- customer has already paid, so the window has to travel all the way to the app:
-- the admin sets it here, publishing puts it in the snapshot, get_offers.php
-- serves it, the app shows it on every offer card and refuses checkout, and
-- stk.php refuses the STK push as a last line of defence.
--
-- Both columns NULL (the default, and what every existing row gets) means "no
-- restriction, buyable any time" — so this migration changes no behaviour until
-- an administrator actually sets a window.
--
-- Times are stored as plain wall-clock TIME values in Africa/Nairobi, the same
-- day boundary the once-per-day rule uses. They are NOT UTC: a window is a
-- customer-facing "5pm to 11pm" and must not drift with the server's timezone.
--
-- Each ALTER is guarded against information_schema so a re-run (or a half-applied
-- file on a shared host that timed out mid-migration) is harmless.

SET @mb_add_from := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{p}offers' AND COLUMN_NAME = 'available_from') > 0,
    'SELECT 1',
    'ALTER TABLE {p}offers ADD COLUMN available_from TIME NULL DEFAULT NULL'
))
-- @@
PREPARE mb_stmt_from FROM @mb_add_from
-- @@
EXECUTE mb_stmt_from
-- @@
DEALLOCATE PREPARE mb_stmt_from
-- @@
SET @mb_add_to := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{p}offers' AND COLUMN_NAME = 'available_to') > 0,
    'SELECT 1',
    'ALTER TABLE {p}offers ADD COLUMN available_to TIME NULL DEFAULT NULL'
))
-- @@
PREPARE mb_stmt_to FROM @mb_add_to
-- @@
EXECUTE mb_stmt_to
-- @@
DEALLOCATE PREPARE mb_stmt_to
