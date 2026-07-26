# Changelog

All notable changes to the My Bingwa customer app are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Sections used: `Added`, `Changed`, `Fixed`, `Removed`, `Security`, `Internal`
(`Internal` = repository change with no customer-visible effect).

## [Unreleased]

_Nothing yet. Next up: server→app notifications sync (FCM or poll+local-schedule)._

## [1.0.1] - 2026-07-26

Released to the direct/GitHub channel (`versionCode 2`). Signed
`My-Bingwa-v1.0.1-direct.apk` + Play AAB published on the `v1.0.1` GitHub Release;
`update.json` points at it so devices on 1.0.0 are offered the update.

### Fixed

- **Payments — buy-for-myself now collects to the Till, not a Paybill (critical).**
  The live payment config had `transaction_type = CustomerPayBillOnline` with
  `party_b = 4050595`, so own-number STK pushes were initiated against a Paybill instead
  of the Buy Goods Till that recommends the data. `server/mybingwa-api/config.php` now
  pins the self route to `CustomerBuyGoodsOnline` with `party_b` = the Buy Goods Till
  (fallback `4953696`) and keeps a separate `paybill_shortcode` (`4050595`) so
  buy-for-another still collects on the Paybill with the recipient number as the account
  reference. No `lib.php` change was needed — the routing code was already correct; only
  the config values were wrong.

### Added

- **Server→app billboard (promotions) sync.** The Home billboard now keeps a local,
  offline-safe copy of the admin's published promotions instead of a hardcoded list,
  mirroring the offer-catalogue sync. A new `get_billboards.php` endpoint serves the
  published snapshot's `billboards` verbatim (empty when nothing is published, so the app
  keeps its cache). The app fetches them through a new Retrofit `AndroidRemoteBillboardSource`
  (same `X-App-Key` auth as the catalogue), maps each to a `Promotion` with a
  kind-derived accent (offer→green, announcement→blue, update→navy — never orange), and
  persists them in the installation snapshot. Promotions are replaced only on a non-empty
  response and restored on launch, so a failed/empty sync never blanks the board and
  synced promotions stay available offline and across process death. Remote images are
  still deferred (no image-loading library) — a slide renders as a coloured text slide,
  matching current behaviour.

- **Admin V2 — payment routing on App configuration.** Two new fields, **Payment Till
  number** (the Buy Goods Till that collects buy-for-myself money → STK `party_b`) and
  **Fulfilment number** (the phone that receives the buy-for-another notification SMS →
  `fulfilment_phone`). Stored digits-only in the `mb_settings` key/value table and read
  live by the payment API through the new `server/admin-v2/cutover/gateway_bridge.php`
  overlay. They apply immediately (server-side routing, not part of the app Publish
  snapshot); blank falls back to the `config.php` defaults. The bridge only ever
  overrides `party_b` and `fulfilment_phone` — never auth/paybill shortcodes — and fails
  safe to no-op if the admin is absent or the DB is unreachable.

- **Android background sync (WorkManager).** A periodic `CatalogueSyncWorker`
  (`CoroutineWorker`, `CONNECTED` constraint, 6-hour period, exponential backoff)
  refreshes the seller config and offer catalogue from the published server data and
  persists them on-device. Synced offers are now stored in the installation snapshot
  and restored on launch, so the UI reads offers from **local storage** (not the
  network), previously synced offers stay available **offline** and across process
  death, and a failed/empty/incomplete sync never overwrites good local data — offers
  and the stored catalogue version are only replaced after a complete, validated
  response. Repository construction moved into a new `MyBingwaApplication` so the UI
  and the worker share one process-wide instance.
- **Admin V2 — three-dot (kebab) row menus** on Offers (View, Edit, Duplicate,
  Archive/Restore, Delete), Message templates (View, Deactivate, Edit, Delete) and
  Payments (View + existing actions), replacing the visible per-row button strips.
- **Admin V2 — payment details open in an overlay modal** (not a separate page),
  showing every recorded field unmasked for operator reconciliation.
- **Admin V2 — collapsible desktop sidebar** (icon-only rail, persisted in `mb_nav`)
  alongside the existing mobile off-canvas drawer, and the real My Bingwa logo asset in
  the brand/header (plus favicon) in place of the placeholder "B".
- **Admin V2 — a simple private control panel (`server/admin-v2/`).** A small PHP 8.2
  admin for **two people** (Super Admin + Admin), built beside the legacy
  `server/mybingwa-api` (which is preserved and untouched), coexisting in the same MySQL
  database via the `mb_` prefix and reading the legacy `payments` table read-only. Runs
  on plain cPanel with **no Composer/Node dependency at runtime**.
  - Eleven sidebar pages: Dashboard, Offers, Billboard adverts (simple/advanced + secure
    image re-encoding), Notifications, Message templates (ReDoS-safe regex + a
    single-sample test), Payments (read-only, masked identifiers), Support details, App
    configuration, Updates & versions, an append-only Audit log, and Settings.
  - **Two account types only:** Super Admin (full control) and Admin (the Super Admin
    ticks which sidebar pages the Admin may see/edit). No roles matrix, no 2FA.
  - **Auto-generated offer IDs** (`data_14`-style) — the admin never types an ID.
  - **Draft → publish → rollback** with immutable, versioned, SHA-256 checksummed
    snapshots; a baseline is published on install so imported records are live (not
    dozens of "drafts"); rollback creates a new later version.
  - **One read-only sync endpoint** `GET /api/app-data` returns the latest published
    offers, adverts, templates, support details, app config, update info and version
    (ETag/`304`, rate-limited).
  - **Zero-touch install:** the database provisions all tables + seed data + baseline on
    first load (no phpMyAdmin, no SQL). On cPanel you create the DB + user once in the
    MySQL wizard and fill `config.php`; everything inside the DB is automatic thereafter.
  - **Offline Till/Paybill/support** are set on the Support page and served via
    `/api/app-data` — never hardcoded, and distinct from the server-side STK shortcode
    (which stays in `mybingwa-api/config.php`).
  - My Bingwa brand design system (Outfit/Poppins, action-green primary, light/dark/
    system themes, responsive mobile drawer). Safe MySQL migrations + idempotent seeder.
    Pure-logic test harness (`tests/run.php`).
- **Permanent release identity for version 1.** The app now carries its permanent
  production `applicationId` **`com.bingwasokoni`** with **`versionName 1.0.0`** and
  **`versionCode 1`** — the identity used on both Google Play and the direct/GitHub
  channel forever, so updates on either channel supersede correctly. (The internal
  `namespace` stays `com.example`; it names generated classes only and is invisible
  to users and Play.)
- **Dual distribution from one codebase and one signing identity.** Two product
  flavors — **`direct`** (signed APK for GitHub/sideload) and **`play`** (AAB for the
  Google Play Console) — share the same `applicationId` and the same app-signing key,
  so a user can move between the two channels and updates apply cleanly.
- **Debug/release separation.** Debug builds install alongside the release app using
  the `.debug` application-id suffix, a `-debug` version-name suffix and the **"My
  Bingwa Dev"** launcher label (release stays **"My Bingwa"**), and use AGP's
  auto-generated debug keystore — never the permanent release key.
- **Signed release pipeline (`.github/workflows/release.yml`).** Runs only on `v*`
  tags or a manual dispatch (never on feature branches, so signing secrets are never
  exposed). It builds `:app:assembleDirectRelease` and `:app:bundlePlayRelease`,
  produces `My-Bingwa-v<version>-direct.apk`, its `.sha256` checksum and
  `My-Bingwa-v<version>-play.aab`, and publishes them as assets on a GitHub Release.
- **One-time keystore bootstrap workflow (`.github/workflows/bootstrap-keystore.yml`).**
  Generates the permanent upload key and delivers it as a GPG-encrypted artifact for
  the owner to decrypt, back up offline and store as the `KEYSTORE_BASE64` secret.
- **In-app update contract for the direct/GitHub channel (`update.json`).** The
  sideload build checks a published `update.json` (via the `UPDATE_MANIFEST_URL`
  BuildConfig field) so directly-installed users get an "update available" prompt;
  Play users are updated natively by Google.
- **Release documentation.** A public privacy policy (`PRIVACY.md`, required for a
  Google Play listing), a first-time Play publishing runbook
  (`docs/RELEASE_PLAYSTORE.md`) covering secrets, keystore bootstrap, the release
  build, Play App Signing with the owner's own key, the Data safety/store-listing
  steps and cross-channel updates, and public release notes
  (`RELEASE_NOTES_v1.0.0.md`).

### Changed

- **Admin V2 is now centre-aligned everywhere.** The shared design system and
  components centre page content, cards, sections, headings, titles, text, forms and
  empty states across every admin page; tables keep their column structure but the
  table, its headings, cells and the actions column are visually centred.
- **Admin V2 dashboard trimmed.** The "Last app sync" card was removed and "Latest
  payments" now shows only the six most recent records.
- **Admin V2 removals (per owner request):** the Support page no longer exposes the
  editable "Offline purchase instructions" fields (stored values are preserved and
  still published); App configuration no longer has the "General support message"
  setting; and the Settings page no longer has any Appearance/theme controls.
- **Admin V2 drastically simplified to a two-person control panel.** Removed the
  enterprise features that were never needed: two-factor authentication, granular
  role/permission matrix, the Analyst & Publisher roles, the "why-this" billboard
  simulator, personalisation weight controls, feature flags, emergency-disable controls,
  quiet-hours / campaign caps, snapshot-signing UI, the separate payment "gateway" page
  (and its `mybingwa-api` config overlay), separate Sender-ID management, the message
  regex "console", system diagnostics (`diag.php`), device telemetry / sync-events,
  active-session management, production/environment badges, per-record draft badges and
  the sidebar draft count, and the sync-health / release-history / revenue-chart
  dashboard cards. Access is now Super Admin (full) + Admin (page-level), the dashboard
  shows only six tiles + latest payments, and every page uses plain customer-friendly
  language. Payment and database functionality is unchanged.
- **Hardcoded seller numbers removed everywhere — including the app.** The Till
  (`4953696`), Paybill (`40450595`/`4050595`) and personal number (`0727921038`) are no
  longer baked into the server (`settings.sql`, `get_config.php`, `admin/*`,
  `config.sample.php`, admin-v2 seed) **or the Android app** (`AppConfig.DEFAULT` and
  `CachedOfflineConfigProvider.DEFAULT` are now blank). They are set once from the admin
  **Support** page and synced. The **offline** Till/Paybill shown to customers is decoupled
  from the server-side STK shortcode used to initiate payments (which stays only in
  `mybingwa-api/config.php`).
- **The app now shows the admin's published data with no app change.** The payment API's
  `get_offers.php` / `get_config.php` / `get_templates.php` now serve the latest
  **published** admin snapshot (read from the shared `mb_configuration_releases` table),
  falling back to the legacy tables when the admin isn't installed/published. So the
  existing app — which already syncs these endpoints on connectivity — reflects what the
  owner publishes, while the admin's own `GET /api/app-data` remains available for direct
  consumption.
- **Offer IDs are generated automatically** (`data_14`-style, per category) instead of
  being typed by the operator; existing IDs stay immutable.
- **App-sync API consolidated to a single `GET /api/app-data`** (was
  `/api/v1/app/manifest|snapshot|sync|offers|config|templates|sync-events`).
- **Payment API auto-creates its `payments` table** on first DB connection
  (`mybingwa-api/db.php`), so `schema.sql` no longer needs a manual phpMyAdmin import.
- **Publishing/rollback simplified** (no signing UI, no re-auth prompts, no draft
  badges): a "Preview changes" button appears in the header only when changes exist, and
  a baseline is published on install so imported records are live rather than drafts.
- **Release output is now flavor-qualified.** With the `direct`/`play` flavors, the
  shipping variants are `directRelease` (APK) and `playRelease` (AAB) instead of a
  single release variant.

### Security

- **Leaked server secrets purged from all Git history + push guards added.** An older
  `server/mybingwa-api/config.php` (live Daraja consumer key/secret/passkey) had been
  committed early in the project and later un-tracked, so its blobs remained reachable
  in the public repo's history. All 93 commits were rewritten with `git filter-repo` to
  strip the file from every branch and the `v1.0.0` tag, then force-pushed. `.gitignore`
  now blocks `**/config.php` and common secret files (templates excepted), and
  `.githooks/pre-commit`/`pre-push` (enabled via `core.hooksPath`) refuse to commit or
  push `config.php` or files containing live-secret markers. The exposed keys must be
  rotated (in progress) and treated as compromised; forks/clones and GitHub's cache may
  retain old objects until GitHub GC.
- **The Google Play build ships no restricted permission.** The `play` flavor's
  manifest overlay removes `RECEIVE_SMS` and the `SmsDeliveryReceiver`, so the Play
  (AAB) submission needs no SMS permissions declaration and cannot be rejected for
  one. The **`direct`** (GitHub) build keeps `RECEIVE_SMS` for the opt-in, local
  Safaricom delivery / low-balance detection only — SMS content is read on-device and
  never leaves the phone.
- **Release signing material stays out of the repository.** No keystore is committed;
  the permanent key is supplied to CI only through protected GitHub Actions secrets
  (`KEYSTORE_BASE64`, `STORE_PASSWORD`, `KEY_PASSWORD`, `KEY_ALIAS`), decoded into the
  runner's temp dir inside the protected release job and deleted in an always()-run
  cleanup step. Daraja/payment secrets remain only on the payment backend.

- **Real on-device persistence (replaces the in-memory "Fake" store).** A new
  `data/persistence/LocalStore` (Preferences DataStore + Moshi JSON, no KSP) loads
  state on start and re-saves the whole snapshot on every change, so the customer's
  **name, profile, favourites, Activity (purchases), notifications, recent recipients
  and any in-flight order now survive process death** — the CLAUDE.md §2 promise that
  these are "local to the installation" is now actually true. Previously everything
  lived in `MutableStateFlow` and reset to seeded demo data on every restart. Unit
  test: full serialisation round-trip of the persisted snapshot (incl. enums).
- **Safe process-death payment restore.** The in-flight order is persisted; on a
  relaunch after the app was killed mid-payment it is settled to an honest **Waiting
  to verify** record (never silently lost, never re-charged) and appears in Activity.
- **Buy-for-another is now a real payment route (was permanently mocked).** With a
  configured backend it goes through the real gateway carrying `forSelf=false`; the
  hardened backend routes it to **Paybill with the recipient number as the account**
  (`stk.php` + `lib.php`). Only a debug build with no backend still simulates it.

### Security

- **Payment callback is authenticated by Safaricom source IP + amount cross-check.**
  `callback.php` authenticates Daraja's result webhook by its source IP (Safaricom's
  `196.201.212/213/214.x` block plus an explicit allowlist) and cross-checks the
  callback amount against the server-recomputed price (a mismatch is flagged, not
  confirmed), so a spoofed "paid" POST from any other IP is ignored. NOTE: an earlier
  `?token=` URL-secret approach was removed — Daraja **strips the query string** from
  the CallbackURL, which silently rejected every real callback and broke all payment
  confirmation; IP auth is the reliable, standard fix (validated live end-to-end for
  both buy-for-myself and buy-for-another).
- **`X-App-Key` validation is now fail-closed** on `stk.php`/`status.php` (empty/
  missing key → 401), and **STK idempotency is atomic** (insert-first on the unique
  `client_request_id`) so concurrent duplicate requests no longer fire two real STK
  prompts or 500. New server config keys: `callback_secret`, `callback_ip_allowlist`,
  `paybill_shortcode`, `paybill_passkey`, `trusted_proxy_header` (documented in
  `config.sample.php`; no real secrets committed).
- **A release build can never fake a payment success.** When no backend is configured,
  release builds use `UnavailablePaymentGateway` (payments fail honestly) instead of
  the dev simulation that returned a fabricated M-Pesa receipt. Real STK requires both
  a base URL (defaults to the production API host) and the `PAYMENTS_APP_KEY` secret.

### Added

- **Buy-for-another number is now implemented (was a mock).** Per
  `docs/Buy For Another Number - Implementation Spec.md`, adapted to our Paybill setup:
  - The app routes a buy-for-another purchase through the real backend (`forSelf=false`);
    the server charges the payer via the Paybill with the **recipient's number** as the
    AccountReference.
  - On a **confirmed** buy-for-another payment, `callback.php` sends a **mocked M-Pesa
    SMS** to the fulfilment phone whose "received from" number is the **recipient** (not
    the payer), so the operator loads the bundle for the right line. The message is a
    byte-for-byte reproduction of the Safaricom format (`lib.php`
    `build_mocked_mpesa_message`), sent via the BlazeTechScope bulk-SMS API
    (`send_mocked_mpesa_sms`). Self-purchases never trigger it.
  - Duplicate-safe: the SMS fires only on the atomic REQUESTED→CONFIRMED transition, so
    Daraja's repeated callbacks never double-send. New config keys (`fulfilment_phone`,
    `business_name`, `sms_api_url`, `sms_api_key`, `sms_sender_id`) documented in
    `config.sample.php`.

### Fixed

- **Support Till/Paybill/phone details now save.** The Support form validated these
  fields with `max:24`, but the validator treats a numeric-looking string as a numeric
  comparison, so any real Till/Paybill/phone number greater than 24 was rejected with
  "Must be at most 24…". A new length-based `maxlen`/`minlen` rule (always `mb_strlen`)
  replaces `max:24` on those fields, so valid shortcodes and numbers save while length
  is still bounded and the `msisdn` format check is unchanged.
- **A fresh install no longer shows prefilled data and opens on onboarding.** The
  default profile was seeded with a real name/number and `isOnboardingCompleted=true`,
  so a new install skipped onboarding and showed the owner's details. The default
  profile is now empty with onboarding not completed (app opens on onboarding on first
  launch), and all seeded demo data (purchases, notifications, recent recipients,
  favourites — which also carried a real phone number) is removed. Tests seed their own
  fixtures via new test-only constructor params. Phone normalisation already supports
  `07…`/`01…` (e.g. `0112385760` → `254112385760`); the earlier mangling was a stale APK.

- **Online STK push now actually works (was failing / not delivering).** Two live
  defects found by firing a real KSh 1 STK against production Daraja:
  - `offers.php` (the server's authoritative price map) used stale ids `off_1..off_16`
    while the app sends `data_6`/`sms_2`/etc., so `stk.php` returned `UNKNOWN_OFFER` and
    the app showed an instant "could not start payment". Rewrote `offers.php` to the
    real catalogue ids/prices (matching `offers.sql`).
  - The seller shortcode `4050595` is a **Paybill**, but the config used
    `CustomerBuyGoodsOnline` (Till), so Daraja accepted the request (ResponseCode 0)
    yet never delivered the prompt. The online buy-for-myself route now uses
    `CustomerPayBillOnline` (deployment change in the server's git-ignored `config.php`).
    A real KSh 1 Paybill STK was confirmed delivered to the test phone.
- **Buy-for-another is a mock again (owner decision).** It uses a different M-Pesa
  integration that is not built yet, so it no longer routes through the real
  self/Paybill gateway — always a labelled simulation, even when a backend is
  configured.
- **Settings notification/SMS toggles now reflect the real OS permission** and are
  persisted, instead of showing "on" optimistically regardless of the actual grant.
  MainActivity writes the true `POST_NOTIFICATIONS` / `RECEIVE_SMS` state into the
  profile on start and after every permission result.

- **Offline-first (Phase 6) + server sync & admin (Phase 7).** The app now knows when
  it is offline and stays fully usable; the server (owner's cPanel) is sync-only.
  - Real connectivity drives the offline state (`setConnectionState(NONE)` ⇒ offline).
  - **Offline manual payment:** when offline, tapping buy skips the online review and
    shows a **Copy Till/Paybill & open M-Pesa** action that copies the number and
    opens the SIM Toolkit (own number ⇒ Till, another ⇒ Paybill). Honest "I've paid"
    receipt tracking is kept (Waiting to verify / Payment not confirmed).
  - **Server config sync:** Till, Paybill, support number and WhatsApp are fetched
    from `get_config.php` when online and CACHED (SharedPreferences) so they always
    work offline; baked-in defaults cover a fresh install. Help + the offline steps
    read these (also fixes the old Help Paybill `4050595` → `40450595` mismatch).
  - **Server offers sync:** the catalogue is fetched from `get_offers.php` when online
    (preserving local favourites/bought-today); the bundled catalogue is the
    guaranteed offline base. `data/catalogue/*`, repo `syncCatalogue()`.
  - **Admin panel** (`server/mybingwa-api/admin/`): brand-styled, password-protected
    dashboard to manage offers, payment/support details and notification templates —
    creates its own tables on first load. New server endpoints `get_config.php`,
    `get_offers.php` (+ `settings.sql`, `offers.sql`, `templates.sql` seeds).
  - Unit tests: connectivity→offline flag, config seed/sync, catalogue sync + local
    fallback + favourite preservation.

- **Activity-aware notification system (owner request).** A new
  `core/notifications` subsystem plus the integration to drive it:
  - **Brand-styled, non-noisy system notifications** via `AppNotifier` using the
    monochrome status icon (`ic_stat_my_bingwa`) and brand-green accent, on
    separated channels (Transactions default importance; Offers/Reminders/Updates
    low importance, silent) — transaction updates kept apart from promotions (§9).
  - **Notification permission on opt-in:** enabling **Push Notifications** in
    Settings shows an in-app rationale, then requests `POST_NOTIFICATIONS`
    (Android 13+); a denied state links to app settings.
  - **Connection-state awareness** (`ConnectivityObserver`): Wi-Fi / mobile data /
    both / none, fed into the offer-suggestion logic.
  - **Safaricom SMS watching (delivery + low balance)**, opt-in behind a separate
    "Bundle & balance alerts" toggle that requests `RECEIVE_SMS` after a clear
    rationale. A `SMS_RECEIVED` receiver classifies messages with a **pure,
    server-syncable template + parser** (`SmsTemplates`/`DefaultTemplates`/
    `SafaricomSmsParser`) seeded from the real Safaricom formats (data/SMS/minutes
    delivery from `Safaricom`/`SAF_OfaMOTO`; low-balance from `SAF_Balance`).
    Templates are data, not code — a `RemoteTemplateSync` seam is left for the
    future server.
  - **Honest delivery reconciliation:** a matched delivery SMS flips the newest
    matching purchase's new `PurchaseRecord.isDeliveryConfirmed` flag and adds a
    quiet, **Safaricom-attributed** in-app note (never a loud "bundle received"
    every time, never a "we delivered/activated" claim — §7). Activity shows a
    small "Safaricom confirmed delivery" line when confirmed.
  - **Low-balance nudges + suggestions** (`OfferSuggestionEngine`) use only §8
    allowed language ("More … offers for you", "Top up with these deals") — never
    "you are running out / you need more data".
  - Unit tests: SMS parser (all four real samples + negatives), suggestion engine,
    and delivery/low-balance reconciliation.

### Fixed

- **App launcher icon and Home header now use the real brand logo, not the mock.**
  The mock `ic_mybingwa_symbol` vector was still driving the Home header **and**
  the adaptive launcher foreground (`ic_launcher_foreground.xml` wrapped it), so
  modern phones showed the mock even after the PNG mipmaps were replaced.
  `ic_mybingwa_symbol`(+`_mono`) are now the approved asset PNGs
  (`my-bingwa-symbol-transparent` / `ic_launcher_monochrome`), fixing the header,
  the adaptive foreground (kept inside the safe-zone layer-list) and the themed
  monochrome icon in one place; the mock vectors were deleted.
- **"Special" category icon now gently glitters** (soft scale + brightness pulse
  on the star) to draw attention to high-value offers; respects reduced motion.

- **Launch splash now uses the approved brand mark.** The Android 12+ splash was
  showing a crude hand-drawn vector (`ic_splash_logo.xml`) instead of the real
  logo. Replaced it with the approved `my-bingwa-splash-mark-512.png` asset
  (`drawable-nodpi/ic_splash_logo.png`); the launcher and onboarding logos were
  already correct.
- **Promotion billboard CTA no longer overlaps the text.** The "Buy now" button
  was absolutely positioned over the subhead and hid it. The slide is now a
  `Row` with the text taking `weight(1f)` and the CTA reserving its own space, so
  they can't overlap — robust at small width and 200% font scale.
- **Settings permission dialogs (notifications & SMS) now have a clean button
  layout.** The rationale dialogs previously cross-aligned "Allow" with a stacked
  "Not now / Open app settings" column. They now use a single full-width vertical
  stack — primary **Allow**, then **Not now**, then **Open app settings**.

### Security

- **`RECEIVE_SMS` is Google Play-restricted.** It is declared for the opt-in
  bundle/balance detection and is intended for the **direct-APK** distribution;
  the Play (AAB) build should exclude it. SMS logic is isolated behind a receiver
  + in-memory signal bus; full SMS bodies and phone numbers are never logged.

### Changed

- **Offers filter reverted to the classic compact styling (owner feedback).** The
  Phase-3 filter-sheet redesign (bold green price flourish, rounded highlight
  rows, "Filter & sort") was reverted to the classic look ("Filter Offers", calm
  values, `FieldButtonShape` rows) while keeping all functionality — category,
  price range, validity and the five sort orders.

- **Notification centre is now an in-app slide-up overlay (per owner request),
  not a standalone page.** Tapping the Home header bell opens a `ModalBottomSheet`
  above the app shell instead of navigating to a disconnected full-screen route,
  so context and the bottom navigation are preserved. Each notification can be
  read (tap), copied (to clipboard) and cleared (single or "Clear all"); a
  notification carrying a deep-link route routes to it and closes the overlay.
- **Settings moved to the bottom navigation (per owner request).** The Home
  header no longer shows a profile avatar; that space now holds only the
  notification bell. Settings is a fifth primary bottom-nav destination and is
  reached and highlighted like every other tab.
- **Home simplified (per owner feedback):** removed the Home search bar and the
  Popular / Bought today / Buy again sections. After the promotion billboard the
  Home now shows only **Your favourites** (vertical list) and **You may also
  like** (a horizontally swipeable row of similar offers).
- **Offer card reverted to the classic compact design** (category tag, name +
  validity, price, buy-tag, and a **Buy** button). The earlier Phase 3 card
  redesign was undone — only the underlying feature logic was wanted, not a UI
  change. Tapping a card or its Buy button opens the purchase sheet directly
  (the interim offer-details sheet was removed).
- **Catalogue replaced with the real My Bingwa offers** (Data, SMS, Minutes,
  Special) with correct prices, validity, per-day tags and validity bands; the
  offer model gained an explicit `validityBand` so the Offers validity filter is
  exact. Billboard promotions now advertise the real high-value monthly/weekly
  offers.
- **Billboard CTA** moved to the right and vertically centred, with more height/
  padding so its label is never clipped.

### Added

- **Checkout & payment state machine (Phase 4):** real payment logic behind a
  transport-agnostic payment-gateway interface, replacing the demo `delay()` stub.
  - Payment state machine (`core/payment`): the Plan.md §6 online (STK) and offline
    device-first transitions as a pure, unit-tested machine with the exact
    customer-facing copy; illegal transitions throw so a payment is never
    optimistically confirmed.
  - **Daraja via the owner's cPanel PHP API** (`server/mybingwa-api/`): a tiny
    4-endpoint PHP API (`stk.php`, `status.php`, `callback.php` + shared `lib/db/
    offers/config`) that holds the Daraja consumer key/secret + passkey and owns the
    CallbackURL, so **no Daraja secrets ship in the APK**. `stk.php` recomputes the
    price from `offerId` and is idempotent on `clientRequestId`; `status.php` falls
    back to Daraja `stkpushquery` if the callback is slow. Ships with `schema.sql`
    and a beginner cPanel walkthrough README. The app calls it over Retrofit
    (`stk.php`/`status.php`) with an `X-App-Key` header; base URL + app-key are
    non-secret `BuildConfig` fields (`PAYMENTS_BASE_URL`, `PAYMENTS_APP_KEY`) injected
    from GitHub secrets, empty by default.
  - A clearly-labelled local **simulation** gateway used when no backend URL is
    configured (and for the still-mocked buy-for-another path), so the app stays
    testable on a phone without ever faking a real success.
  - Idempotent checkout: every attempt carries a `clientRequestId`; a double-tap or
    retry returns the existing record instead of charging twice. Airtight in-flight
    guard in the sheet plus repository-level idempotency.
  - Offline signed-config interface (`OfflinePaymentConfig` + provider): Till/Paybill
    values with a validity window and signature check, and pure eligibility rules —
    expiry, amount ambiguity (shared price on the same route), Till vs Paybill route,
    and hard once-per-day offline blocking.
  - Offline receipt capture: **I've paid** with an M-Pesa code → **Waiting to verify**;
    without a code → **Payment not confirmed**. Never shown as success.
  - Process-death restoration **contract** (`ActiveOrder` + `activeOrder` flow),
    in-memory this phase; Phase 6 persists it.
  - Kenyan phone normalisation (`KenyanPhone`): `07…/01…/254…/+254…` → E.164, grouped
    display and MSISDN for the gateway; invalid numbers block STK.
  - New payment statuses (`EXPIRED`, `NOT_CONFIRMED`, `COULD_NOT_VERIFY`) and
    `PurchaseRecord` fields (`clientRequestId`, `orderReference`).
  - Unit tests: state machine (full transition table + illegal transitions + copy),
    phone normalisation, offline eligibility, and repository idempotency/honesty.
- **Catalogue experience (Phase 3):** real logic for Home, Offers, offer
  details, search, filters, sorting, favourites, promotions and daily purchase
  awareness, replacing the demo screens.
  - `CatalogueViewModel` derives immutable `HomeUiState`/`OffersUiState` from the
    repository flows (screen-level ViewModel, injectable clock for tests).
  - `CatalogueLogic` — pure, unit-tested functions for filtering, five sort
    orders (incl. shortest/longest validity), Nairobi-day daily purchase state,
    Home section derivation, restrained personalised suggestions and promotion
    selection.
  - Home now follows the Plan.md §5.2 order: greeting, search, category
    shortcuts, one promotion billboard, Popular, Bought today, More offers you
    can buy, Buy again, Your favourites and a restrained "You might also like".
  - `PromotionBillboard` — a swipeable advert surface (the "television"): solid
    brand-colour slides (no gradients), optional bundled artwork, manual-swipe
    carousel with page indicators (no auto-rotation), and a breathing CTA that
    respects reduced motion. Rotates the seller's biggest weekly/monthly/
    high-value offers plus announcements and app updates.
  - `OfferDetailsSheet` — offer details bottom sheet (allowance, price, validity,
    daily state, favourite, **Buy bundle**) that hands the purchase to checkout.
  - Favourite toggle with an **Undo** snackbar on Home and Offers.
  - Daily purchase awareness presentation: Available today / Bought today /
    Available again tomorrow / {n} purchases left today / Waiting to verify,
    per-recipient and per Africa/Nairobi day.
  - Offers filter sheet now offers category, **price range**, validity and all
    **five** sort orders; results, query, filters, sort and scroll position are
    preserved across tab switches.
  - Loading (skeleton), empty-from-filters, empty-catalogue and offline states
    for both Home and Offers.
  - New core models: `Promotion` (+ `PromotionKind`/`PromotionAccent`),
    `PurchasePolicy` and `OfferDailyState`/`DailyStateKind`.
  - Repository contract extended with `promotions`, `catalogueLoading`,
    `setFavourite(id, isFavourite)` and `refreshCatalogue()` (fake pool seeded;
    Phase 6/7 syncs real data into Room).
- Bundled brand typefaces: Outfit (variable) and Poppins (Regular/Medium/
  SemiBold/Bold static) under `app/src/main/res/font`, with OFL licences kept in
  `app/licenses/`. Typography now maps every Material 3 role to Outfit/Poppins,
  so no text falls back to the system font.
- Theme-aware category colours (`ui/theme/CategoryColors.kt`): category chips and
  icon tiles resolve their accent/container/on-container from the active theme,
  designed for both light and dark (design.md §7.3) instead of baked light hexes.
- Branded launcher icon set: proper Android 13+ themed (monochrome) silhouette
  layer, adaptive foreground/background, and My Bingwa raster launcher icons for
  API 24–25; monochrome notification icon (`ic_stat_my_bingwa`) placed for all
  densities ahead of the notifications phase.
- Android 12+ launch splash showing the My Bingwa mark on the brand canvas
  (light and dark), via `androidx.core:core-splashscreen`.
- Checked-in Gradle wrapper (Gradle 9.3.1) under `my-bingwa/gradle/wrapper/`
  with `gradlew`/`gradlew.bat`, so the project builds from the command line and
  CI without Android Studio. Distribution is pinned with a SHA-256 checksum.
- Root `.gitignore` covering build output, `local.properties`, `.env`,
  keystores, signing material, Firebase config/logs and IDE state.
- `CHANGELOG.md` (this file) following Keep a Changelog.
- Top-level `README.md` describing the repository, the CI-first build workflow
  and where to get a debug APK.
- `docs/REPO_INVENTORY.md`: imported-project inventory, module/package map,
  proposed feature ownership boundaries for parallel phases, and the shared
  contracts that Phase 1 must create before parallel feature work starts.
- GitHub Actions workflow `.github/workflows/feature-debug-build.yml` that, on
  feature/chore branches and `main`, runs `test lint assembleDebug` via the
  Gradle wrapper and uploads a clearly named debug APK plus test/lint reports.

### Changed

- **Honest payment language (Phase 4):** the checkout success screen now shows
  **Payment received** with "Please wait for the bundle on {recipient}" and no
  delivery timeframe (was "Purchase successful" / "Your bundle will be received in a
  few minutes"). The checkout now surfaces every honest state — Payment cancelled,
  Payment failed, Request expired, Still checking payment and We could not verify —
  and never claims delivery. Phone-field labels use the exact spec strings
  **Bundle recipient** and **M-Pesa payment number**.
- The checkout Till/Paybill values are read from the signed offline config (single
  source of truth) instead of hardcoded literals in the sheet.
- Offer cards are now pure selection surfaces: the compact full-size **Buy**
  button was removed (Plan.md §5.3) — tapping a card opens offer details / the
  purchase sheet. Cards now present calm daily-state labels.
- Home promotion surface no longer uses a gradient banner; it is the solid
  brand-colour `PromotionBillboard`.
- Typography engine switched from the downloadable Google Fonts provider (which
  needed real Google certificates and silently fell back to the system font) to
  the bundled font files.
- Bottom navigation reduced to the four primary destinations — Home, Offers,
  Activity, Help (design.md §12.1). Settings opens from the Home avatar.
- Repository layout: planning documents (`Plan.md`, `design.md`,
  `CLAUDE_KICKOFF_AND_BUILD_PHASES.md`) moved into `docs/`. Operating brain
  (`CLAUDE.md`), `memory.md` and `CHANGELOG.md` remain at the repository root.
- Brand asset kit folder renamed from `assests/` to `assets/` (typo fix).

### Fixed

- **Bottom-navigation bug:** tapping Home from Offers could get stuck, and
  tapping Home from Help/Activity could land on Offers. Root cause was mixing
  plain `navigate()` calls to routes that are also bottom-nav tabs with the
  save/restore tab state machine, plus popping to `graph.startDestinationId`
  (which can still be `onboarding`). All jumps to a tab route now use one
  consistent `popUpTo("home"){ saveState }` + `launchSingleTop` +
  `restoreState`, guarded against re-navigating the current route; reselecting a
  tab scrolls its list to the top.
- Debug build no longer references a non-existent, git-ignored
  `debug.keystore`; it now uses AGP's auto-generated debug signing config, which
  unblocks `assembleDebug` in a clean CI environment.
- Corrected `ExampleRobolectricTest` to expect the real app name "My Bingwa"
  (was the template default "My Application"), so the unit-test gate passes
  truthfully.
- Pinned `ExampleRobolectricTest` to `@Config(sdk = [34])`; Robolectric 4.16.1
  has no SDK 36 sandbox and threw `UnsupportedOperationException`, failing the
  test gate the first time CI reached it (after the KSP crash was removed).

### Removed

- Fake placeholder font certificates (`res/values/font_certs.xml`) and the
  `ui-text-google-fonts` dependency, now that fonts are bundled locally.
- AI Studio scaffolding that My Bingwa does not use and that broke the CI build:
  the KSP plugin with its unused Room/Moshi codegen (KSP2 crashed on the runner
  during annotation processing), the `google-services` and `secrets` Gradle
  plugins, the Firebase BOM, `firebase-ai` (Gemini) and `firebase-appcheck`
  dependencies, and `.env.example`.
- Empty stray `firebase-debug.log` from the repository root.
- Broken custom `debugConfig` signing config from `app/build.gradle.kts`.
- Orphaned template `GreetingScreenshotTest.kt` (and its `greeting.png`) that
  referenced deleted template symbols (`MyApplicationTheme`, `Greeting`) and
  could not compile.

### Security

- Confirmed no keystores, `.env` files, `google-services.json`,
  service-account files or other secrets are present in the imported project.
- `.gitignore` hardened so signing material and secrets cannot be committed.

### Internal

- Phase 0 baseline audit of the Google AI Studio-generated UI recorded in
  `memory.md` and `docs/REPO_INVENTORY.md`.
