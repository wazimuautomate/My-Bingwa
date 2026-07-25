# Claude Prompt — Rebuild My Bingwa Admin and Connect App Sync

```text
You are the lead full-stack engineer rebuilding the My Bingwa administration
system and connecting it to the Android app through a safe, versioned background
sync API.

This is a fresh admin build, not a visual patch of the existing single-page
admin. The existing PHP code, MySQL data and current API are migration
references. Preserve them until the replacement has been verified and a
controlled cutover is approved. “Rebuild from scratch” does not authorise
deleting live data, overwriting production settings or replacing the existing
admin before rollback is possible.

## 1. Product context

My Bingwa is a customer-facing Android app for buying Bingwa data, SMS, minutes
and special offers. The administration system is the control centre for the
app’s offers, billboard adverts, notification campaigns, recognised Safaricom
message templates, payment/support information, remote configuration and
version/update rules.

The admin will be used by:

- Super Admin: the owner. Full control, including administrators, roles,
  publishing, rollback, payment configuration, update rules and security.
- Admin: the owner’s partner. Permissions are assigned by role and may be
  limited by module and action.

The current environment is:

- PHP application.
- MySQL database.
- Hosted on cPanel/shared hosting.
- Existing server API currently handles payments.
- No production app-configuration sync exists yet.

## 2. Mandatory first actions

Before editing:

1. Inspect the repository, current PHP version, Composer setup, routing,
   database access, existing authentication, API code, environment handling and
   deployment structure.
2. Inspect the current MySQL schema and existing records without modifying
   them.
3. Inspect the Android project if it is present in the workspace. If it is not
   present, build and test the server API and produce an exact Android sync
   contract instead of pretending the app was connected.
4. Read all available My Bingwa source-of-truth files, especially `CLAUDE.md`,
   `Plan.md`, `design.md`, `memory.md` and `CHANGELOG.md`.
5. Inspect Git status and preserve all unrelated user changes.
6. Search for exposed credentials, hard-coded database passwords, production
   payment values, signing keys and unsafe committed files.
7. Record the current admin behaviour and a migration map.

Do not work directly on `main`. Create a focused feature branch such as:

`feature/admin-v2-sync-platform`

Do not use destructive Git or database commands. Do not deploy or cut over
production without explicit approval after tests pass.

## 3. Critical architecture correction

The Android app must not fetch live server responses directly into the UI.
However, this does NOT mean the app should become empty when offline.

Implement this exact data flow:

1. Admin publishes an immutable configuration snapshot.
2. The server increments the published configuration version.
3. The Android sync worker checks for a newer version in the background.
4. If a newer valid snapshot exists, the app downloads and verifies it.
5. The app writes the complete snapshot to Room inside one atomic transaction.
6. Android screens observe Room only.
7. The UI never renders raw network responses.
8. When offline, the app continues showing the last successfully verified local
   snapshot.
9. If a fresh installation has never synced, use a minimal approved seed
   snapshot packaged with the app or show a truthful first-sync state.
10. A failed sync never wipes the last valid local data.

The local database is the Android app’s canonical readable source. The server
is the publishing source, and background sync updates the local source.

Admin publishing should trigger a sync hint where available, but Android
background restrictions mean “automatic” cannot be treated as guaranteed
instant delivery. Use:

- A versioned sync API.
- ETag/If-None-Match or equivalent conditional checks.
- WorkManager periodic fallback.
- A one-time WorkManager sync after a valid FCM data-message hint when the
  notification infrastructure exists.
- Manual “Check for updates” or “Sync now” recovery in debug/support tooling.

Do not poll aggressively or drain battery.

## 4. Technical direction

Build for reliable cPanel deployment:

- PHP 8.2+ where the hosting environment supports it.
- MySQL 8 or compatible MariaDB.
- PDO with prepared statements or the existing safe framework database layer.
- Composer-managed dependencies.
- A clear MVC/layered structure: routes, controllers, request validation,
  services/use cases, repositories, models/entities and views.
- Server-rendered responsive pages with progressive enhancement.
- Tailwind CSS or an equivalent compiled utility layer for the custom design
  system.
- Alpine.js or small modular JavaScript for interactive admin behaviour.
- Chart.js or an equally lightweight chart library for dashboard charts.
- Build CSS/JS before cPanel deployment; do not require a Node server in
  production.

If the existing project is already a maintained PHP framework, use its secure
conventions instead of creating a second framework. If it is unsafe,
unstructured plain PHP, create a clean `admin-v2` application beside it and a
controlled migration path.

Do not introduce a framework only for fashion. Record every dependency and why
it is required.

## 5. Admin visual direction

Use the supplied Pinterest dashboard only as a layout and density reference:

- Fixed left sidebar on desktop.
- Compact top header.
- Clear page title and contextual action.
- Summary cards.
- Charts and operational panels.
- Dense but readable tables.
- Large rounded application shell.
- Responsive mobile drawer.

Do not copy its FinSet branding, purple palette, finance copy, icons or exact
artwork.

### My Bingwa admin design system

Use the My Bingwa brand:

- Action green: `#006B27`
- Brand green: `#18C964`
- Information/action blue: `#1565D8`
- Data accent: `#3BA9FF`
- Promotion orange: `#FF8A00`
- Dark navy: `#0A2540`
- Light background: `#F6F9FC`
- Light surface: `#FFFFFF`
- Light grouped surface: `#F0F5F2`
- Light text: `#0A2540`
- Light secondary text: `#526271`
- Divider: `#E5E7EB`
- Error: `#BA1A1A`
- Warning: `#9A5A00`
- Success: `#157A3B`

Dark theme:

- Background: `#0E1512`
- Surface: `#15201B`
- Grouped surface: `#19251F`
- Raised surface: `#233129`
- Text: `#EEF7F1`
- Secondary text: `#BAC9BF`
- Divider: `#303D35`

Typography:

- Outfit for page titles, important numbers and section headings.
- Poppins for navigation, forms, controls, tables and body copy.

Use Material Symbols Rounded or another single consistent web-safe outline icon
family. Do not use emojis.

### Futuristic/glassy treatment

Make the admin feel modern and slightly futuristic, but do not turn dense
management pages into low-contrast glassmorphism.

Allowed:

- A restrained translucent sidebar/top bar or floating filter bar.
- Subtle background blur where browser support and readability allow it.
- Fine 1px translucent borders.
- Soft tinted green, blue or orange highlights used by meaning.
- Smooth 120–240ms transitions.

Required:

- Forms, tables, destructive confirmations and data-heavy cards use sufficiently
  opaque, high-contrast surfaces.
- Text contrast remains accessible.
- Blur has a solid-colour fallback.
- No gradients, neon glows, giant shadows, excessive animation or transparent
  tables.
- Green remains the normal primary action.
- Orange is reserved for promotion or warning emphasis.

### Responsive behaviour

- Desktop: persistent sidebar, wide data tables and multi-column dashboard.
- Tablet: collapsible sidebar and two-column panels.
- Mobile: off-canvas navigation, stacked summary cards, responsive data tables
  that become prioritised rows/cards without hiding important actions.
- Every admin action must remain usable on a phone.

## 6. Global admin shell

Create:

- Login and secure account-recovery flow.
- Persistent sidebar.
- Compact top bar with global search where useful, sync/publish status,
  notifications, theme control and admin profile.
- Breadcrumbs only where they reduce confusion.
- Light, dark and system theme.
- Page-level primary action.
- Global draft/published status indicator.
- Toast/snackbar feedback for reversible actions.
- Proper modal/dialog confirmations for dangerous actions.
- Empty, loading, error, permission-denied and no-results states.

Sidebar, in this exact order:

1. Dashboard
2. Offers
3. Billboard adverts
4. Notifications
5. Message templates
6. Payments
7. Support details
8. App configuration
9. Updates and versions
10. Audit log
11. Settings

Keep Help/Profile/Sign out in a separated sidebar footer.

## 7. Dashboard

The dashboard should answer: Is the app configured correctly, is it syncing,
what is selling, and what needs attention?

Header:

- Time-based greeting using the signed-in administrator’s first name.
- Current environment: Production/Staging.
- Current published configuration version.
- Last publish time.
- Primary action: `Create draft` or `Review draft`.

Summary cards:

- Active offers.
- Scheduled notifications.
- Revenue for selected period.
- Successful payments for selected period.
- App sync health.

Dashboard panels:

- Revenue/payment trend chart.
- Purchases by category: Data, SMS, Minutes and Special.
- Latest payments with masked customer identifiers.
- Upcoming notification campaigns.
- Draft changes waiting for review.
- Recent publishes and rollbacks.
- Sync health: current version, devices on current/older versions where
  anonymous telemetry is available, last successful API response, failed sync
  rate and stale configuration warning.
- Operational warnings: missing payment details, expired billboards, invalid
  message templates, forced-update misconfiguration or failed campaign.
- Recent audit activity.

Provide date filtering and CSV export only where meaningful. Do not create fake
metrics when the backend does not collect them. Show `Not available yet` with
the missing data source instead.

## 8. Offers

Build a dedicated Offers page instead of one huge page.

List/table fields:

- Offer ID.
- Category.
- Offer name/allowance.
- Price.
- Validity.
- Daily rule.
- Active/draft/archived state.
- Current published version or unpublished-change indicator.
- Updated at/by.
- Actions.

Filters:

- Search.
- Category.
- Status.
- Daily rule.
- Validity band.
- Price range.
- Published/unpublished changes.

Offer form:

- Stable unique ID.
- Category: Data, SMS, Minutes or Special.
- Name/allowance.
- Price in KSh.
- Validity value and display label.
- Daily rule:
  - Once per day.
  - Multiple per day.
  - Maximum per recipient per day, if supported.
- Maximum count where applicable.
- Optional commercial tag such as Best value.
- Offline-purchase eligibility.
- Active/inactive.
- Start/end availability where required.
- Customer-visible restrictions.

Actions:

- Add.
- Edit.
- Duplicate.
- Archive.
- Restore archived offer.
- Delete only when safe.
- Export filtered results to CSV.

There is no manual offer sort/rank setting.

Safety rules:

- Existing published offers referenced by purchases or audits must normally be
  archived, not hard-deleted.
- Hard delete is limited to unused drafts and requires appropriate permission.
- Duplicate creates a new stable ID and a draft; it never overwrites the source.
- Price, daily-rule and offline-payment changes receive a clear diff preview.
- Publish validation prevents duplicate IDs, invalid prices, expired
  availability and unsafe offline-payment ambiguity.

## 9. Billboard adverts

Create list, calendar and editor views.

Common billboard fields:

- Internal name.
- Status: Draft, Scheduled, Active, Paused, Expired or Archived.
- Priority.
- Start/end date and Africa/Nairobi time.
- Display-frequency rules.
- Eligible audience/rules.
- Linked app destination/deep link.
- Preview in phone-sized light and dark frames.
- Impression, click and purchase-attribution metrics where available.

### Simple billboard

The simple version is generated from a linked current offer.

Default template:

- Tag: `BEST VALUE`
- Title: `{{offer_name}} for KSh {{price}}`
- Description: `Stay connected for {{validity}}.`
- CTA: `Buy now`

Requirements:

- Choose a linked offer.
- Validate supported tokens.
- Preview resolved content.
- Allow controlled text variants without changing product truth.
- Disable or replace the billboard automatically if its linked offer becomes
  unavailable.
- Never publish unresolved `{{tokens}}`.

### Advanced billboard

Fields:

- Image upload with crop/preview and accessible alt text.
- Tag.
- Headline.
- Body text.
- CTA label.
- CTA destination/deep link.
- Optional linked offer.
- Priority.
- Start/end date.
- Display-frequency and impression caps.
- Eligibility rules.

Image security:

- Validate MIME type, size and actual image decoding.
- Re-encode images safely.
- Generate correctly sized variants.
- Use random server filenames.
- Prevent executable uploads.
- Store originals and derivatives outside executable paths or under a strict
  non-executable upload directory.

### Automatic personalisation

Implement the billboard selection rules as a tested, explainable scoring
system—not opaque AI and not a fixed advert.

Required flow:

`Fetch current offers → remove unavailable/ineligible offers → analyse allowed
local behaviour → score relevant upgrades → choose from a top candidate pool
with controlled randomness → render a varied approved template → record
privacy-safe outcome → improve future scoring`

Rules:

- Prefer categories the customer buys more frequently.
- Promote a reasonable step up from the customer’s normal spend, not an
  extreme irrelevant jump.
- Give extra value to longer-validity and higher-value offers.
- Preserve category diversity so SMS and Minutes are not permanently hidden.
- Exclude bought-today once-per-day offers, expired offers and unavailable
  offers.
- Apply impression caps and recent-dismissal suppression.
- Use weighted randomness among top candidates so two similar users do not
  always see the same advert.
- Never claim knowledge the app does not have.
- Keep the scoring inputs and weights remotely configurable within validated
  limits.
- Prefer on-device scoring from local purchase behaviour.
- Use an anonymous installation identifier for optional server analytics; do
  not use a phone number as the analytics identity.
- Record impression, click, purchase and dismissal events only after consent
  and according to the privacy policy.

Provide an admin `Why this billboard may be shown` explainer and a simulator
where an administrator can enter anonymous sample behaviour and inspect the
score calculation.

## 10. Notifications

Notification types:

- Manual broadcast.
- Scheduled campaign.
- New-offer notification.
- App-update notification.
- Personalised purchase-based notification.
- Payment-related notification.

Pages:

- Campaign list.
- Create/edit campaign.
- Schedule/calendar.
- Audience and rule builder.
- Phone preview.
- Delivery/results detail.
- Reusable approved notification copy templates.

Fields:

- Internal campaign name.
- Notification type.
- Title.
- Body.
- Image where supported.
- Deep link/action.
- Audience/rule.
- Category/linked offer.
- Schedule in Africa/Nairobi.
- Expiry.
- Priority.
- Quiet-hours handling.
- Frequency cap.
- Recent-purchase suppression.
- Draft/preview/publish status.

Rules:

- Payment notifications are system-triggered from verified payment states, not
  arbitrary manual claims.
- Do not expose full phone numbers, M-Pesa receipts or sensitive data on lock
  screens.
- Do not send a campaign merely because it exists.
- Deduplicate remote and locally scheduled campaigns.
- Enforce quiet hours and shared caps.
- Prevent advertising an inactive or expired offer.
- Support test-send only to authorised test installations.
- Show sent, delivered where the platform provides evidence, opened and
  converted separately. Never fabricate delivery data.
- Allow cancellation of future campaigns, not retroactive unsending.

## 11. Message templates

These templates describe known messages from Safaricom sender IDs. Their
purposes are:

1. Detecting a likely bundle-delivery message within a limited time window
   after an eligible purchase.
2. Detecting low or very-low bundle-balance messages and offering relevant
   My Bingwa choices.

Initial reference examples:

- Sender `Safaricom`, SMS delivery:
  `You have received 20 SMS Daily SMS Bundle. Expiry date: ...`
- Sender `Safaricom`, data delivery:
  `You have received Sh20=250MB 24hr from Bingwa Sokoni ...`
- Sender `SAF_Balance`, low data:
  `Dear customer, your deal of the day data balance is 75MBs...`
- Sender `SAF_Balance`, very low data:
  `Dear customer, your deal of the day data balance is below 2MBs...`
- Sender `SAF_OfaMOTO`, minutes delivery:
  `You have received a gift of Sh20=43 Mins,3hrs from ...`

Create:

- Template list.
- Sender-ID management.
- Template editor.
- Sample-message test console.
- Match diagnostics.
- Version history.
- Activate/deactivate.
- Duplicate.
- Archive.

Template fields:

- Stable key.
- Label.
- Sender ID with normalisation rules.
- Purpose: Delivery, Low balance or Very low balance.
- Category: Data, SMS, Minutes or Special.
- Pattern type: safe regex or structured token pattern.
- Pattern.
- Case-sensitivity setting where justified.
- Capture mappings: amount, allowance, validity and expiry where available.
- Match priority.
- Purchase-correlation window.
- Active dates/version.
- Active/draft/archived status.
- Example positive messages.
- Example negative messages.

Validation:

- Reject invalid or dangerously expensive regex patterns.
- Set input-length and execution safeguards against regex denial of service.
- Detect overlapping patterns and show which template wins.
- Require positive and negative samples before publish.
- Test sender plus body, not body alone.
- Keep an audit of every template revision.

Delivery detection limitations:

- A matching SMS is evidence that a recognised delivery message arrived on
  that device, not authoritative server fulfilment proof.
- Use customer copy such as `Delivery message detected`, unless a stronger
  verified backend source exists.
- Correlate only with a recent compatible purchase and time window.
- For “buy for another number”, the payer’s device may never receive the
  recipient’s Safaricom SMS. Do not mark that purchase delivered from absence or
  from an unrelated message.
- Handle dual-SIM uncertainty conservatively.
- Never infer delivery from sender ID alone.

Low-balance engagement:

- Match category and severity.
- Suppress suggestions immediately after a purchase.
- Apply rate limits and quiet hours.
- Recommend currently available offers only.
- Persuasive or lightly witty copy is allowed, but never insulting, alarming,
  manipulative or based on invented usage.
- Admins choose from approved message variants; do not generate uncontrolled
  sarcastic text on the device.

### Play Store permission gate

Do not silently add restricted SMS or Call Log permissions to the Play build.
Google Play permits SMS access only for eligible core uses. Build the admin
template system independently, then gate Android SMS parsing by an explicit
distribution/compliance decision.

Required Android separation:

- `playRelease`: no restricted SMS-reading permission or parser unless written
  Play-policy eligibility has been confirmed.
- `directRelease`: an optional, explicit-consent SMS-recognition feature may be
  compiled only after privacy, security and legal review.
- Never request Call Log permission for this feature.
- Keep template sync useful even when the Play client does not activate SMS
  parsing.
- Record this as an unresolved release decision; do not fake compliance.

## 12. Payments

Build a payment operations page backed only by real payment records.

Dashboard/list:

- Date/time.
- Masked payer.
- Masked recipient.
- Offer.
- Amount.
- M-Pesa receipt masked by default.
- Payment state.
- Fulfilment/delivery-message state shown separately.
- Source/app version.
- Support flag.

Filters:

- Date range.
- State.
- Category/offer.
- Amount.
- M-Pesa receipt search with protected access.
- Has support issue.

Details:

- Payment timeline.
- Order and idempotency reference.
- Callback/status history.
- Recipient/payer distinction.
- App configuration version used.
- Audit events.
- Related support case.

Provide CSV export only to authorised roles, with sensitive fields minimised.
Do not expose Daraja credentials. Do not allow an administrator to manually mark
an unverified payment successful without a privileged, reasoned, audited
override policy.

## 13. Support details

Manage:

- Till number.
- Paybill number.
- Support phone number.
- WhatsApp number/link.
- Offline payment instructions for own-number purchase.
- Offline payment instructions for another-number purchase.
- Support message/banner.
- Availability/working hours if added.

Requirements:

- Draft/preview/publish.
- Phone preview.
- Copy validation.
- Kenyan phone/shortcode validation.
- Super Admin permission by default for Till/Paybill changes.
- Re-authentication and explicit confirmation for payment-route changes.
- Clear before/after diff.
- Immutable audit record.
- Rollback through a new published version.
- Do not put private payment credentials in app configuration.

## 14. App configuration

Remotely configurable items:

- Maintenance mode.
- Maintenance title/message and allowed actions.
- Optional/forced update behaviour.
- Sync interval within safe minimum/maximum limits.
- Support messages.
- Feature flags.
- Published snapshot cache validity.
- Offline-payment configuration validity.
- Notification quiet hours and caps.
- Billboard personalisation weights within validated bounds.
- Emergency disable for a broken offer, campaign or payment route.

Do not create arbitrary remote code execution, remotely supplied JavaScript or
unbounded server-driven UI. Configuration controls known app behaviour only.

Maintenance mode must not unnecessarily remove access to Help or existing
Activity. Forced update must contain a valid version rule, update destination
and customer-safe explanation.

## 15. Updates and versions

Create:

- Version list.
- Add release rule.
- Optional/required update preview.
- Release notes.
- Minimum supported versionCode.
- Latest versionCode/versionName.
- Play Store destination.
- Direct APK/update destination where applicable.
- Rollout percentage if supported.
- Active/inactive status.
- Adoption summary where anonymous telemetry exists.

Prevent:

- A minimum version higher than the published latest version.
- A forced update without a valid destination.
- Downgrades.
- Conflicting simultaneous rules.
- Accidental lockout of every app version.

Every forced-update change requires Super Admin permission, re-authentication,
preview and audit.

## 16. Audit log

The audit log is append-only and cannot be edited from the UI.

Record:

- Actor.
- Role.
- Action.
- Entity type and ID.
- Before/after diff with sensitive-value masking.
- Draft/publish/rollback version.
- Reason where required.
- Timestamp in UTC plus Africa/Nairobi display.
- IP and user-agent where appropriate.
- Success/failure.

Filters:

- Actor.
- Module.
- Action.
- Entity.
- Date range.
- Publish version.

Allow authorised CSV export. Never log passwords, tokens, full credentials,
M-Pesa PINs or secret values.

## 17. Settings, administrators and RBAC

Settings sections:

- My profile.
- Theme.
- Password.
- Two-factor authentication.
- Active sessions/devices.
- Administrators.
- Roles and permissions.
- Security events.
- Environment/system information.
- API health.

Default permission approach:

Super Admin:

- All modules and actions.
- Manage admins/roles.
- Publish and rollback.
- Payment/support route changes.
- Forced updates.
- Security settings.

Admin:

- View Dashboard.
- Create/edit drafts in explicitly assigned modules.
- May preview.
- Cannot manage Super Admins.
- Cannot change payment routes, forced updates, security settings, secrets,
  publish or rollback unless that exact permission is granted.

Use granular permissions such as:

- `offers.view`, `offers.create`, `offers.edit`, `offers.archive`
- `billboards.manage`
- `notifications.create`, `notifications.schedule`
- `templates.manage`
- `payments.view`, `payments.export`
- `support.edit`
- `config.edit`
- `releases.manage`
- `publish.execute`
- `rollback.execute`
- `audit.view`
- `admins.manage`

Enforce permissions on the server for every request. Hiding a sidebar item is
not authorisation.

## 18. Draft, preview, publish and rollback

This workflow applies to all app-shipped configuration:

1. Edit a draft.
2. Validate.
3. Preview entity changes and the full app snapshot.
4. Show a human-readable diff from the current published version.
5. Publish inside one database transaction.
6. Create an immutable release snapshot and incrementing version.
7. Sign/checksum the canonical snapshot.
8. Write the audit event.
9. Send a background-sync hint after successful commit.

Rollback:

- Never mutate or delete an old published snapshot.
- Selecting an old version creates a new draft copied from it.
- Preview the rollback diff.
- Publishing the rollback creates a new, later version.
- Require a reason and suitable permission.

Add optimistic locking/version checks so two admins cannot silently overwrite
each other. Show a conflict screen with both revisions.

## 19. Suggested MySQL model

Create migrations, not manual production edits. Adapt names to existing
conventions while preserving these concepts:

- `admin_users`
- `roles`
- `permissions`
- `role_permissions`
- `admin_user_roles`
- `admin_sessions`
- `offers`
- `offer_revisions`
- `billboards`
- `billboard_revisions`
- `notification_campaigns`
- `notification_templates`
- `message_sender_ids`
- `message_templates`
- `message_template_revisions`
- `support_config`
- `payment_config`
- `app_config`
- `app_versions`
- `configuration_drafts`
- `configuration_releases`
- `configuration_release_items`
- `audit_logs`
- `payments` or integration mapping to the existing payment table
- `sync_events`
- `anonymous_app_versions`
- `campaign_events` where privacy policy and consent allow

Use foreign keys, indexes, unique constraints, timestamps and soft-delete/
archive rules deliberately. Keep revision and release records immutable.

Store uploaded asset metadata separately from executable paths. Do not store
large image binaries directly in MySQL unless the existing deployment requires
it and the trade-off is documented.

## 20. Sync API contract

Create a versioned read-only app sync API separated from authenticated admin
routes.

Recommended endpoints:

- `GET /api/v1/app/manifest`
- `GET /api/v1/app/snapshot/{version}`
- `GET /api/v1/app/sync` as an optional combined conditional endpoint
- `POST /api/v1/app/sync-events` only if anonymous sync telemetry is approved
- Existing payment endpoints remain separate.

Manifest fields:

- Schema version.
- Configuration version.
- Published timestamp.
- Snapshot URL/path.
- ETag/checksum.
- Signature and signature algorithm.
- Minimum client schema/app version.
- Expiry/valid-until where required.

Snapshot contains only published, app-safe data:

- Active offers.
- Active/scheduled billboards relevant at evaluation time.
- Notification/template metadata required by the client.
- Approved message templates where that client feature is enabled.
- Public payment/support details.
- Offline instructions.
- App configuration.
- Update/version rules.

Do not include:

- Admin users.
- Roles.
- Drafts.
- Audit logs.
- Database identifiers not required by the app.
- Daraja credentials.
- Database credentials.
- API secrets.
- Internal notes.

Security:

- HTTPS.
- Canonical JSON encoding.
- Strong checksum.
- Sign configuration using a server-held private key and verify with a public
  key embedded in the app; never put a shared signing secret in the APK.
- Rate limiting and abuse controls.
- Conditional requests returning `304 Not Modified`.
- Schema compatibility rules.
- Size limits.
- Cache headers consistent with versioned immutable snapshots.

Payment/support configuration is high risk: reject an invalid signature or
incompatible schema and retain the previous valid local snapshot.

## 21. Android sync implementation

If the Android project is available, implement:

- Network DTOs separate from Room entities and UI models.
- Sync metadata table/DataStore record.
- Room transaction that validates and replaces/upserts the complete published
  snapshot atomically.
- WorkManager periodic sync using safe minimum intervals.
- One-time sync on app start when stale, without blocking cached Home.
- One-time sync after an FCM sync hint.
- Connectivity retry with exponential backoff.
- ETag/version handling.
- Signature/checksum verification.
- Schema compatibility.
- Last-successful-sync timestamp.
- Debug-only sync diagnostics.
- Preserve previous data on any failure.
- UI observes Room only.

Do not:

- Clear Room before snapshot verification.
- block startup on the network.
- show raw network data.
- claim publish changes reached every device instantly.
- store server secrets in the APK.

If the Android code is not in this workspace:

- Finish the tested server API.
- Generate `APP_SYNC_CONTRACT.md` with exact JSON examples, validation rules,
  error codes and Android integration steps.
- Do not claim the app connection was completed.

## 22. Authentication and security

Implement:

- Secure session-based admin authentication.
- `password_hash`/`password_verify` with a supported strong algorithm.
- TOTP two-factor authentication, especially mandatory for Super Admin.
- CSRF protection on state-changing web requests.
- Session rotation after login and privilege changes.
- Secure, HttpOnly and SameSite cookies; Secure in HTTPS production.
- Login throttling and temporary lockout.
- Server-side RBAC.
- Output escaping.
- Prepared SQL.
- Strict request validation.
- Safe file uploads.
- Security headers.
- Secret values only in protected environment configuration.
- Encrypted storage for any secret that must be retained.
- Re-authentication for payment routes, roles, rollback and forced updates.

Never expose stack traces, SQL errors or secrets to customers/admins in
production. Never commit `.env`, database passwords, private keys, FCM service
credentials or signing material.

## 23. Migration and cutover

Create:

- Read-only inventory of current tables and records.
- Migration mapping from current offers, support/payment details and message
  templates.
- Idempotent import command/tool.
- Dry-run mode.
- Validation report.
- Database backup instructions.
- Side-by-side staging admin.
- Production cutover checklist.
- Rollback plan to the current admin.

Do not change the existing production table shape merely to suit a UI component
until migrations, compatibility and rollback are tested.

## 24. Testing

At minimum test:

- Authentication, CSRF, rate limiting and RBAC.
- Super Admin versus Admin permissions.
- Offer create/edit/duplicate/archive/delete safety.
- CSV export escaping.
- Billboard token rendering.
- Billboard scheduling/frequency.
- Personalisation scoring, exclusions, diversity and controlled randomness.
- Notification scheduling, caps and invalid linked offers.
- Message-template positive/negative matching.
- Dangerous/overlapping regex rejection.
- Purchase-correlation window.
- Draft conflict handling.
- Publish transaction.
- Immutable release creation.
- Rollback as a new version.
- Audit logging.
- Payment/support sensitive-change confirmation.
- Forced-update validation.
- API `200`, `304`, invalid version and incompatible schema.
- Snapshot checksum/signature.
- Android atomic sync and last-valid-snapshot preservation where available.
- Light/dark themes.
- Desktop, tablet and mobile layouts.
- Keyboard navigation, visible focus, labels and contrast.

Use realistic fixtures but never production customer data or secrets.

## 25. Deliverables

Deliver:

1. Fresh Admin V2 source.
2. Safe MySQL migrations and seed/fixture data.
3. Existing-data import/migration tool.
4. Complete responsive UI for every sidebar page.
5. Light, dark and system themes.
6. RBAC and two-factor-ready authentication.
7. Draft/preview/publish/rollback workflow.
8. Immutable audit history.
9. Billboard simple/advanced editors and personalisation engine.
10. Notification campaign system.
11. Message-template editor and match-test console.
12. Payment/support/app/version configuration.
13. Versioned signed sync API.
14. Android sync integration if its repository is available; otherwise the
    complete tested contract.
15. Tests.
16. cPanel deployment guide.
17. Migration/cutover/rollback guide.
18. Updated `memory.md` and `CHANGELOG.md` where those project rules apply.

## 26. Execution order

Work in coherent checkpoints:

1. Audit and architecture.
2. Database migrations, auth and RBAC.
3. Shared admin shell/design system.
4. Offers and publishing foundation.
5. Billboard and personalisation.
6. Notifications and message templates.
7. Payments, support, app configuration and versions.
8. Audit log.
9. Versioned snapshot API.
10. Android sync or exact handoff contract.
11. Migration tests, responsive QA, security review and cPanel deployment.

Commit each checkpoint coherently. Do not merge to `main` until required tests,
review and deployment checks pass.

## 27. Definition of done

The task is complete only when:

- The replacement admin is not a single giant page.
- Every sidebar destination works.
- Both roles are enforced server-side.
- Mobile and desktop layouts are usable.
- My Bingwa colours and fonts are actually applied.
- The glassy treatment remains readable.
- Draft changes cannot leak to the app.
- Every publish creates a validated immutable version.
- Rollback creates a new version and complete audit evidence.
- The app sync endpoint exposes published app-safe data only.
- Android screens read local data, not raw network responses.
- Offline Android use retains the last valid snapshot.
- Failed sync cannot erase working data.
- Payment details and forced updates receive stronger controls.
- SMS parsing is not silently added to a non-compliant Play build.
- Existing production data is preserved and migratable.
- Tests pass.
- No secrets are committed.
- Git, CI, deployment and remaining blockers are reported truthfully.

Final report:

- What was built.
- Architecture and schema decisions.
- Files/migrations changed.
- Tests and results.
- Security review.
- Migration/cutover status.
- Git branch and commit.
- Whether Android sync was actually implemented or only contracted.
- Exact unresolved inputs and risks.
```
