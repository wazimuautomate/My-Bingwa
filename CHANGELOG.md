# Changelog

All notable changes to the My Bingwa customer app are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Sections used: `Added`, `Changed`, `Fixed`, `Removed`, `Security`, `Internal`
(`Internal` = repository change with no customer-visible effect).

## [Unreleased]

### Added

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

- **Payment callback can no longer be spoofed.** `callback.php` now requires a
  shared-secret URL token (`?token=…`) and an optional Safaricom IP allowlist before
  any DB write, and cross-checks the callback amount against the server-recomputed
  price (a mismatch is flagged, not confirmed). Previously anyone who knew a
  `CheckoutRequestID` could POST a fake receipt and flip a payment to confirmed.
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

### Fixed

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
