-- Remove SMS rules.
--
-- The Android app no longer reads device SMS (it was rejected from Google Play for the
-- SMS permission), so the on-device message-matching feature this module fed is gone. The
-- admin's SMS Rules module, its engine and its /sms-rules/* screens have been removed from
-- the codebase; this migration drops the tables migration 013_sms_rules.sql created so a
-- fresh install never provisions them. Migration 013 itself is left in place as a
-- historical ledger entry — it already ran against production.
--
-- DROP TABLE IF EXISTS so this is harmless whether or not 013 ever ran on a given install.
DROP TABLE IF EXISTS {p}sms_rule_revisions;
-- @@
DROP TABLE IF EXISTS {p}sms_rules;
-- @@
DROP TABLE IF EXISTS {p}sms_event_types;
-- @@
DROP TABLE IF EXISTS {p}sms_pattern_types;
-- @@
-- Notifications could be triggered by "an SMS rule matched a message on the device"
-- (migration 014_notifications_v2.sql seeded this as the 'sms_event' trigger type, the
-- only one with needs_event = 1). That event catalogue is gone with the tables above, so
-- disable the option rather than leaving a trigger an operator could pick but never wire
-- to a real event. Existing campaigns already saved with this trigger keep their stored
-- value; they simply stop matching an enabled catalogue entry until re-edited.
UPDATE {p}notification_trigger_types SET enabled = 0 WHERE trigger_key = 'sms_event';
-- @@
-- migration 017_categories_flags.sql seeded a 'sms_rules' feature flag ("SMS awareness")
-- that an operator could see and toggle on the App configuration page. With the module
-- gone the switch controls nothing, so remove the row rather than leave a phantom toggle.
DELETE FROM {p}feature_flags WHERE flag_key = 'sms_rules';
