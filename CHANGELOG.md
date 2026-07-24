# Changelog

All notable changes to the My Bingwa customer app are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Sections used: `Added`, `Changed`, `Fixed`, `Removed`, `Security`, `Internal`
(`Internal` = repository change with no customer-visible effect).

## [Unreleased]

### Added

- **Checkout & payment state machine (Phase 4):** real payment logic behind a
  transport-agnostic payment-gateway interface, replacing the demo `delay()` stub.
  - Payment state machine (`core/payment`): the Plan.md §6 online (STK) and offline
    device-first transitions as a pure, unit-tested machine with the exact
    customer-facing copy; illegal transitions throw so a payment is never
    optimistically confirmed.
  - **Daraja via a backend proxy** (`data/payment`): the app calls our backend
    (`POST payments/stk`, `GET payments/status`) over Retrofit; the backend holds the
    Daraja consumer key/secret + STK passkey and owns the CallbackURL. **No Daraja
    secrets in the APK.** The backend base URL is a non-secret `BuildConfig`
    field (`PAYMENTS_BASE_URL`), empty by default.
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
