-- Simplify to a two-person control panel:
--  * partner Admin gets page-level access (a JSON list of sidebar page keys),
--    replacing the granular role/permission matrix,
--  * app_config gains a single general support message shown on the app help screen.
ALTER TABLE {p}admin_users ADD COLUMN allowed_pages TEXT NULL;
-- @@
ALTER TABLE {p}app_config ADD COLUMN general_support_message TEXT NULL;
