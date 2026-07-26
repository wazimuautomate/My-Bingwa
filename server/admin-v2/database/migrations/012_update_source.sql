-- Update delivery source for a version rule: 'github' (direct APK) or 'play' (Play Store).
-- Folded into the published snapshot.version and mirrored in the owner's update.json.
ALTER TABLE {p}app_versions ADD COLUMN update_source VARCHAR(12) NOT NULL DEFAULT 'github';
