# My Bingwa — Project Memory

**Purpose:** Durable project continuity and execution record  
**Time zone:** Africa/Nairobi  
**Last updated:** 2026-07-24  
**Current phase:** Phase 3 — Home, catalogue, search, favourites and promotions
on `feature/catalogue-experience` (real catalogue logic, promotion billboard,
personalisation, nav fix). Phase 1 foundation merged to `main`; architecture
contracts (Hilt, module split, DataStore, nav registry) still pending for Phase 6.

This document records current truth, important decisions, completed work,
verification and the next step. It is not a raw command log and must never
contain secrets.

---

## 1. How to maintain this file

Claude must update this file after every execution, whether the execution:

- Changed code or documentation.
- Investigated a problem.
- Ran tests only.
- Was blocked.
- Correctly made no change.

Keep entries factual and concise. Append execution entries chronologically.
Update the current-state sections when a decision or phase changes.

Never record:

- M-Pesa PINs.
- API credentials.
- Private signing keys.
- Full access tokens.
- Complete payment payloads.
- Personal customer data that is not required to understand the project.

---

## 2. Execution entry template

Copy this block to the end of the execution log:

```markdown
### YYYY-MM-DD HH:mm EAT — Short execution title

- **Objective:** What the user requested.
- **Result:** Completed, partial, blocked or no change.
- **Changed:** Behaviour and files changed.
- **Decisions/assumptions:** Any new decision or necessary assumption.
- **Verification:** Exact tests, checks or inspections and their result.
- **Git:** Branch, commit, push, PR and merge status.
- **Risks/blockers:** Remaining problem or `None`.
- **Next:** Exact next useful action.
```

---

## 3. Current repository state

**Phase:** Phase 0 baseline complete on `feature/bootstrap-generated-ui`
(pending CI + merge to `main`).

**What the repository is:** a Google AI Studio-generated Android UI **prototype**
with a faked in-memory data layer, made build-safe and coordinated for phased
development. It is not yet a production architecture.

**Repository layout (Phase 0):**

- Root: `CLAUDE.md`, `memory.md`, `CHANGELOG.md`, `README.md`, `.gitignore`,
  `.github/workflows/`.
- `docs/`: `Plan.md`, `design.md`, `CLAUDE_KICKOFF_AND_BUILD_PHASES.md`,
  `REPO_INVENTORY.md`.
- `assets/my-bingwa-logo-kit/`: approved brand/launcher assets (folder renamed
  from misspelled `assests/`).
- `my-bingwa/`: Android Gradle project (`:app` module).

**Recorded build facts (unchanged in Phase 0):**

- Kotlin `2.2.10`, AGP `9.1.1`, Gradle wrapper `9.3.1` (added), Compose BOM
  `2024.09.00`, Material 3.
- `namespace = com.example` (placeholder), `applicationId =
  com.aistudio.mybingwa.k3p9zq` (AI Studio placeholder — unresolved).
- `minSdk 24`, `targetSdk 36`, `compileSdk = release(36){ minorApiLevel = 1 }`,
  `versionName 1.0`, `versionCode 1`.
- Single monolithic `:app` module.

**Architecture gaps vs Plan.md (to be built in Phase 1/6):** no Hilt, no
ViewModels, no Room entities/DAOs, no DataStore, uses Moshi instead of
kotlinx.serialization, Retrofit/OkHttp unused, no WorkManager/FCM/Baseline
Profile. Fonts are downloadable Google Fonts with **placeholder certs** (won't
load at runtime). Firebase-AI/Gemini/secrets/google-services are AI Studio
cruft.

**Design/product deviations found (fix in later phases, cited for handoff):**

- Payment honesty: `feature/purchase/PurchaseBottomSheet.kt` shows "Purchase
  successful" and "Your bundle will be received in a few minutes" — must become
  **Payment received** with no delivery timeframe; offline path never captures
  the M-Pesa receipt and ignores the expiry field.
- Prohibited visuals: gradients (`HomeScreen.kt`, `OnboardingScreen.kt`),
  confetti burst, glassmorphism cards, infinite rotation, bouncy/overshoot
  springs; reduced-motion never checked.
- White text on orange/gradient (`Theme.kt onTertiary`, Home hero).
- Bottom nav has 5 items incl. Settings (spec: 4).
- Compact `OfferCard` has a full Buy button (spec: no).
- Dark-theme category colours hardcoded light (`OfferCategory.kt`).
- Typography roles `labelMedium`/`bodySmall`/`titleSmall` unmapped → Roboto.
- Centred offline/help instruction paragraphs (should be start-aligned).
- Placeholders: name "Bonke", number "0727 921 038", **inconsistent Paybill**
  (`40450595` vs `4050595` in `HelpScreen.kt`), hardcoded app version "2.4.0".

Good baseline: theme already encodes correct `design.md` colour tokens and
wires Outfit/Poppins scales. No hardcoded secrets/keys found in source.

**Unresolved business/security inputs (must not be invented):** permanent
applicationId; signing key ownership/backups; backend base URL + API contract;
Daraja server integration + callbacks; production-safe Till/Paybill delivery +
decodable offline amount rules; Firebase project + notification payload/deep-link
contract; direct-APK update host + Play ownership; privacy policy/terms/support
destinations.

---

## 4. Execution log

### 2026-07-24 21:30 EAT — Phase 0: repository baseline and coordination

- **Objective:** Make the imported Google AI Studio UI safe for phased
  development: audit it, establish repo layout, add a checked-in Gradle wrapper,
  fix baseline build blockers, add feature-branch CI, produce a repo inventory
  and Phase 1 contracts, initialise Git and push a feature branch. No feature
  redesign.
- **Result:** Repository restructured and build-safed. Debug APK buildability to
  be confirmed by GitHub Actions (this PC has no JVM/Android SDK, so the CI run
  is the authoritative build; result recorded in the follow-up entry below).
- **Changed:**
  - Audited all 28 generated Kotlin sources + build config (via subagent);
    findings captured in section 3 and `docs/REPO_INVENTORY.md`.
  - Added checked-in Gradle wrapper (`my-bingwa/gradlew`, `gradlew.bat`,
    `gradle/wrapper/gradle-wrapper.jar` + `.properties`), Gradle `9.1.0`,
    distribution SHA-256 pinned. Wrapper jar verified (PK magic; sha256
    `76805e32…93f3`).
  - Fixed debug-signing blocker in `my-bingwa/app/build.gradle.kts`: removed the
    broken `debugConfig` referencing a non-existent, git-ignored
    `debug.keystore`; debug now uses AGP's auto-generated debug keystore.
  - Moved planning docs into `docs/`; renamed `assests/` → `assets/`; removed
    empty stray `firebase-debug.log`.
  - Added root `.gitignore` (secrets/keystores/build/IDE), `CHANGELOG.md`,
    `README.md`; replaced the Android-Studio-mandating `my-bingwa/README.md`.
  - Added CI `.github/workflows/feature-debug-build.yml` (assemble debug APK →
    upload `my-bingwa-debug-<sha>` → run test/lint → upload reports; branch
    concurrency cancels obsolete runs).
  - Added `docs/REPO_INVENTORY.md` (inventory, ownership boundaries, Phase 1
    shared contracts, unresolved inputs).
  - Added a "Repository layout" section to `CLAUDE.md` pointing to `docs/`.
- **Files changed:** `my-bingwa/gradlew`, `my-bingwa/gradlew.bat`,
  `my-bingwa/gradle/wrapper/gradle-wrapper.jar`,
  `my-bingwa/gradle/wrapper/gradle-wrapper.properties`,
  `my-bingwa/app/build.gradle.kts`, `my-bingwa/README.md`, `.gitignore`,
  `CHANGELOG.md`, `README.md`, `.github/workflows/feature-debug-build.yml`,
  `CLAUDE.md`, `memory.md`, `docs/REPO_INVENTORY.md`; moved `Plan.md`,
  `design.md`, `CLAUDE_KICKOFF_AND_BUILD_PHASES.md` → `docs/`; renamed
  `assests/` → `assets/`; removed `firebase-debug.log`.
- **Decisions/assumptions:**
  - Kept the Android project in `my-bingwa/`; CI targets it via
    `working-directory`. Minimal change, preserves the imported project.
  - Pinned Gradle `9.1.0` to match AGP `9.1.1` (both verified to exist on
    their repositories). If CI reports an exact different minimum, adjust.
  - Did **not** change `applicationId`, `namespace`, SDK levels or version — all
    recorded as-is; permanent applicationId left unresolved.
  - Did **not** fix design/architecture deviations (out of Phase 0 scope; owned
    by later phases). Only baseline build/config safety addressed.
  - CI assembles the APK **before** the test/lint gate so a Roborazzi screenshot
    failure (no bundled fonts in CI) still yields an installable debug APK.
  - GitHub repo to be created **private** by default under `wazimuautomate`
    (repo owner); the active `gh` account is `Wazimu90`, so a switch to
    `wazimuautomate` is required to create/push.
- **Verification:** No local build possible — this PC has no `java`, `gradle`,
  Android SDK or `adb` (permanent constraint). Toolchain present: `git`, `gh`
  (authed: `Wazimu90` active, `wazimuautomate` available), `curl`. Wrapper jar
  and scripts fetched from the official `gradle/gradle` v9.1.0 tag and validated.
  Authoritative build delegated to GitHub Actions (result in follow-up entry).
- **Git:** To be initialised; baseline committed on
  `feature/bootstrap-generated-ui`; pushed to
  `https://github.com/wazimuautomate/My-Bingwa.git`. `main` merged only after CI
  passes. (Exact SHAs in the follow-up entry.)
- **Risks/blockers:** Bleeding-edge AGP `9.1.1` + `compileSdk 36 (minorApiLevel
  1)` may require SDK components not present on the CI runner — the main CI risk.
  Recorded, not worked around.
- **Next:** Initialise Git, push `feature/bootstrap-generated-ui`, watch the
  GitHub Actions run, record the real CI result, and merge to `main` only if it
  passes.

### 2026-07-24 21:55 EAT — Phase 0 follow-up: pushed; CI blocked on billing

- **Objective:** Push the baseline and record the authoritative CI result.
- **Result:** **Blocked.** Baseline committed and pushed to the feature branch,
  but GitHub Actions could not run, so **debug-APK buildability is UNVERIFIED**.
- **Git:** Repo `wazimuautomate/My-Bingwa` already existed as an empty **private**
  repo (created earlier 2026-07-24 15:14 UTC; not visible from the `Wazimu90`
  account, which is why the pre-check reported "not found"). Set the active `gh`
  account to `wazimuautomate`. Commit `d1aa76d`
  (`chore: bootstrap generated UI into a build-safe repository baseline`, 126
  files) pushed to `origin/feature/bootstrap-generated-ui`. **`main` NOT merged**
  (CI gate not passed). No force, no destructive Git.
- **Verification:** Workflow "Feature debug build" run `30106346078` ended in ~3s
  with the job **never starting**. GitHub annotation: *"The job was not started
  because recent account payments have failed or your spending limit needs to be
  increased."* This is an **account-level GitHub Actions billing block on
  `wazimuautomate`**, not a code/build failure. No job logs exist. The billing
  API needs a `user` token scope not granted, so exact minute counts are
  unavailable; the annotation is definitive.
- **Decisions/assumptions:** Did not make the repo public and did not alter
  billing — both are the owner's decisions. Committed this documentation update
  with `[skip ci]` to avoid another no-op failed run.
- **Risks/blockers:** CI is unusable until the owner either (a) fixes
  billing / raises the Actions spending limit on `wazimuautomate` (Settings →
  Billing & plans), or (b) makes the repo public (Actions minutes are free for
  public repos; the audit found no secrets, but this exposes the source and is
  the owner's call). Until then, no APK is produced and the code has not been
  compiled anywhere.
- **Next:** Owner unblocks Actions (fix billing or make repo public), then
  re-run the workflow (`gh run rerun 30106346078 --repo wazimuautomate/My-Bingwa`
  or push any commit). If it goes green with a `my-bingwa-debug-<sha>` artifact,
  merge `feature/bootstrap-generated-ui` into `main` and push. If it fails on
  AGP 9.1.1 / `compileSdk 36.1` SDK provisioning, capture the exact error and
  adjust the SDK/AGP setup — do not weaken tests.

### 2026-07-24 22:10 EAT — Phase 0 follow-up: public repo, CI ran, Gradle bumped

- **Objective:** Get a real CI result and a debug APK.
- **Result:** Progressing. Owner made the repo **public** (Actions now free), so
  the job ran. First real run **failed at `assembleDebug`** with a precise,
  fixable cause; applied the fix and re-triggered.
- **Root cause:** AGP `9.1.1` requires **Gradle ≥ 9.3.1**; the wrapper was pinned
  to `9.1.0` (AGP version numbers do not map 1:1 to Gradle). Everything else
  worked: JDK 17, Android SDK provisioning for `compileSdk 36 (minorApiLevel 1)`,
  Gradle setup, wrapper execution.
- **Changed:** Bumped wrapper to **Gradle 9.3.1** (`gradle-wrapper.properties`
  distributionUrl + SHA-256 `b266d5ff…ff06`; wrapper jar re-fetched from the
  `v9.3.1` tag, PK-valid). Removed the invalid `build-root-directory` input from
  `gradle/actions/setup-gradle@v4` in the workflow. Synced the Gradle version in
  `README.md`, `CHANGELOG.md`, `docs/REPO_INVENTORY.md` and the current-state
  section.
- **Decisions/assumptions:** Kept AGP `9.1.1` (already current) rather than
  chasing a newer AGP under time pressure — Gradle 9.3.1 is a current stable and
  the correct minimal fix. Broader dependency modernisation is Phase 1 work.
- **Git:** `wazimuautomate/My-Bingwa` is now **public**. Fix committed on
  `feature/bootstrap-generated-ui`; push auto-triggers CI. `main` still unmerged.
- **Risks/blockers:** Remaining unknown is whether the `test`/`lint` gate passes
  (Roborazzi screenshot renders without bundled fonts); the APK is uploaded
  before that gate regardless.
- **Next:** Watch the new run; if the APK assembles, merge to `main`; capture any
  test/lint failure without weakening tests.

### 2026-07-24 19:40 EAT — Phase 1: design system, branding and CI unblock

- **Objective:** Advance Phase 1 (shared foundation and design system) with the
  user's priorities: make the app use the real design system — bundled brand
  fonts (the running UI was falling back to Roboto), full logo usage (app icon,
  onboarding, in-app, splash, notification), consistent light/dark theme, and
  icons not emoji.
- **Result:** Partial (design-system + branding axis of Phase 1 complete;
  architecture contracts deferred — see Risks). Also fixed the Phase 0 CI build
  blocker so the branch can actually compile and merge.
- **Changed:**
  - **Fonts:** downloaded Outfit (variable) + Poppins (static R/M/SB/B) OFL fonts
    into `app/src/main/res/font`; OFL licences in `app/licenses/`. Rewrote
    `ui/theme/Type.kt` to use bundled fonts via `FontVariation` weight axis for
    Outfit; mapped **every** Material 3 typography role. Deleted the fake
    `res/values/font_certs.xml` and removed the `ui-text-google-fonts` dep. This
    is the fix for "the current UI fonts are not ours".
  - **Category colours:** stripped baked light hexes from `OfferCategory` (now
    semantic label+iconName only); added theme-aware `ui/theme/CategoryColors.kt`
    (`categoryColors(category)`), dark tints in `Color.kt`; updated the two
    consumers (`OfferCard`, `HomeScreen`).
  - **Launcher/branding:** proper monochrome themed-icon layer
    (`ic_mybingwa_symbol_mono.xml` + `ic_launcher_monochrome.xml`) wired into both
    adaptive icons; replaced legacy webp launcher icons with My Bingwa PNGs from
    the logo kit (API 24–25); placed monochrome `ic_stat_my_bingwa` notification
    icons for all densities. In-app logo already in the top bar; onboarding
    already shows the 140dp mark — confirmed, not changed.
  - **Splash:** added `androidx.core:core-splashscreen`, `ic_splash_logo.xml`,
    `Theme.MyBingwa.Starting` (light+dark `splash_background`), manifest theme,
    and `installSplashScreen()` in `MainActivity`.
  - **Nav:** bottom nav reduced to 4 items (removed `SETTINGS` entry).
  - **CI unblock (Phase 0 blocker):** removed the KSP plugin + unused Room/Moshi
    codegen deps (KSP2 crashed on the runner with an AWT-EventQueue NPE during
    annotation processing — root cause of every failed Phase 0 run since the
    Gradle bump). Removed unused AI Studio cruft: `google-services` + `secrets`
    plugins, Firebase BOM, `firebase-ai`, `firebase-appcheck`, `.env.example`,
    and the `googleServices.missing.passthrough` property. Added
    `-Djava.awt.headless=true` to Gradle JVM args.
  - **Emoji:** full scan of source — none present; UI already uses Material
    Symbols. No change needed.
- **Files changed:** `Type.kt`, `Color.kt`, `CategoryColors.kt` (new),
  `OfferCategory.kt`, `OfferCard.kt`, `HomeScreen.kt`, `MyBingwaBottomNav.kt`,
  `MainActivity.kt`, `AndroidManifest.xml`, `res/values/themes.xml`,
  `res/values/colors.xml`, `res/values-night/colors.xml` (new),
  `res/font/*` (new), `app/licenses/*` (new), launcher/notification/splash
  drawables + mipmaps, `app/build.gradle.kts`, `build.gradle.kts`,
  `gradle.properties`, `gradle/libs.versions.toml`; deleted `font_certs.xml`,
  `.env.example`, legacy `ic_launcher*.webp`.
- **Decisions/assumptions:**
  - Did **not** touch `namespace`/`applicationId` — the permanent applicationId
    is an unresolved business input; finalising the package/module split waits on
    it. Kept the single `:app` module.
  - Removing KSP is safe because no `@Entity`/`@Dao`/`@JsonClass` exist; Room and
    kotlinx.serialization come back with real code in Phase 6/7.
  - AGP 9.1.1 supplies built-in Kotlin (no explicit `kotlin.android` plugin), so
    removing KSP does not affect Kotlin compilation.
  - Left onboarding's confetti/rotating-glow/gradient and Home's gradient promo
    hero untouched — they are design.md violations but owned by Phases 2/3.
- **Verification:** No local build (PC has no JDK/Android SDK). Static checks:
  full-repo emoji scan (clean), grep for dangling refs to removed symbols
  (clean), font binaries verified as valid TrueType. Authoritative build is the
  GitHub Actions run on `feature/android-foundation` (result to be recorded).
- **Git:** Branch `feature/android-foundation` off Phase 0 tip. Branding work
  landed in `24cffea` (auto-committed by the environment); CI unblock + nav in
  `4304d80`. No `main` exists yet. Push + CI pending.
- **Risks/blockers:** (1) CI must confirm the KSP removal actually unblocks
  `assembleDebug` and the app compiles — unverified until the run is green.
  (2) Phase 1 architecture contracts NOT done: Hilt DI, module split
  (`core:*`/`feature:*`), DataStore, navigation route registry, Room/network
  interface shells, final namespace — deferred (namespace blocked on
  applicationId; large blind refactors are risky without local build).
- **Next:** Push `feature/android-foundation`; watch GitHub Actions. If green
  with a `my-bingwa-debug-<sha>` artifact, establish `main` from it and record
  the phone-test handoff. If red, capture the exact error and fix on-branch
  without weakening tests.

### 2026-07-24 20:00 EAT — Phase 1 follow-up: CI green, `main` established

- **Objective:** Get an authoritative green build and merge Phase 1 to `main`.
- **Result:** Done. First real end-to-end CI on the fix: run `30110137557`
  **assembled and uploaded the debug APK** (KSP removal confirmed as the correct
  unblock — the app compiles), but the `test lint` gate failed on
  `ExampleRobolectricTest` (`UnsupportedOperationException`, Robolectric 4.16.1
  has no SDK 36 sandbox — a pre-existing template defect only reachable once the
  KSP crash was gone). Pinned the test to `@Config(sdk = [34])` (commit
  `2cd3d8c`). Re-run `30110563088` = **success** (assembleDebug + test + lint).
- **Git:** `feature/android-foundation` green at `2cd3d8c`. Created and pushed
  `main` at the same commit (no prior `main` existed; this establishes it from
  the verified tip = Phase 0 baseline + Phase 1 design-system foundation). Main
  post-merge CI run `30110953057` re-runs the full gate. Working tree clean.
  Note: an auto-commit step in this environment added an unused
  `res/drawable-nodpi/img_onboarding_logo.png` (part of the green tree, not
  referenced) and split the branding work across commits `24cffea`/`4304d80`.
- **Artifact:** `my-bingwa-debug-2cd3d8c` → `My-Bingwa-Debug-2cd3d8c.apk` (debug,
  versionName 1.0 / versionCode 1). Workflow "Feature debug build" on
  `feature/android-foundation`, run `30110563088`.
- **Verification:** GitHub Actions only (no local toolchain). assembleDebug +
  unit test + lint all green. Not yet installed/tested on a physical phone.
- **Risks/blockers:** Physical-phone acceptance (fonts render, launcher/splash/
  notification icons, light+dark, 4-item nav) still pending — CI cannot confirm
  visual rendering. Phase 1 architecture contracts (Hilt, module split,
  DataStore, nav registry, Room/network shells, final namespace) remain.
- **Next:** Install `My-Bingwa-Debug-2cd3d8c.apk` on the phone; verify Outfit/
  Poppins render, adaptive+themed launcher icon, branded splash, notification
  icon, and light/dark consistency. Then schedule the remaining Phase 1
  architecture work (or fold it into Phase 6 integration) once the permanent
  applicationId is provided.

### 2026-07-24 22:25 EAT — Phase 0 follow-up: debug APK builds; fixed template tests

- **Objective:** Green CI + debug APK.
- **Result:** **Debug APK assembled and uploaded successfully** in CI (run
  `30108343324`, commit `dec2b24`): steps assemble → stage → upload all passed.
  A transient Gradle-CDN `504` on the prior attempt was cleared by a rerun. Only
  the `test`/`lint` gate was red, from broken template test code (not app code).
- **Root cause (test gate):** leftover default-template unit tests:
  `GreetingScreenshotTest.kt` referenced non-existent `MyApplicationTheme` /
  `Greeting` (→ `compileDebugUnitTestKotlin` failed), and
  `ExampleRobolectricTest` asserted the template app name "My Application".
- **Changed (genuine test corrections, not weakening):** removed
  `GreetingScreenshotTest.kt` + orphaned `greeting.png`; corrected
  `ExampleRobolectricTest` expected app name to "My Bingwa". Kept
  `ExampleUnitTest` (2+2). `ExampleInstrumentedTest` (androidTest) not run in CI;
  flagged for Phase 1 (asserts `com.example` package).
- **Verification:** proven working in CI — JDK 17, Android SDK for
  `compileSdk 36 (minorApiLevel 1)`, Gradle 9.3.1 wrapper, `assembleDebug`,
  debug APK artifact upload. Pushing the test fix to confirm a fully green run.
- **Git:** committed on `feature/bootstrap-generated-ui`; push auto-triggers CI.
  `main` merged only after a fully green run.
- **Risks/blockers:** `lint` result still unconfirmed (build stopped at the test
  compile before lint completed). If lint reports fatal errors, capture exact IDs
  and fix or baseline them honestly.
- **Next:** Watch the run; if fully green, merge `feature/bootstrap-generated-ui`
  → `main` and report the exact APK artifact.

### 2026-07-24 23:40 EAT — Phase 3: catalogue experience (Home, Offers, promotions, personalisation, nav fix)

- **Objective:** Give real logic to Home sections + category intents, cached
  catalogue UI, search/filters/sorting/result state, offer details, favourite
  toggle + Undo, once/multiple-per-day presentation, the promotion/announcement
  surface, and catalogue loading/empty/error/offline states — plus three explicit
  user requests: (1) turn the promotion banner into a swipeable, gradient-free,
  brand-coloured advert "television" with a breathing CTA, image support and
  offer rotation weighted to big (monthly/weekly/high-value) offers; (2) fix the
  bottom-nav bug where Home was unreachable from Offers and Help/Activity landed
  on Offers; (3) make the app feel personalised (bought-today awareness,
  favourites-based suggestions) without being noisy.
- **Result:** Implemented (code complete; **not yet built** — this PC has no JDK/
  Android SDK, so compilation + APK are pending the GitHub Actions run on the
  branch). Delegated the billboard component and the test suite to sub-agents.
- **Changed (behaviour + files):**
  - **New core models:** `core/model/Promotion.kt` (`Promotion` +
    `PromotionKind`/`PromotionAccent`), `core/model/OfferDailyState.kt`
    (`OfferDailyState`/`DailyStateKind`); `core/model/OfferItem.kt` gained
    `PurchasePolicy` (MULTIPLE / ONCE_PER_RECIPIENT / MAX_PER_RECIPIENT +
    `maxPurchasesPerDay`), defaulted from the existing `dailyRule`.
  - **Repository contract** (`data/fake/BingwaRepository.kt` + `FakeBingwaRepositoryImpl.kt`):
    added `promotions`, `catalogueLoading`, `setFavourite(id, isFavourite)`,
    `refreshCatalogue()`, two more `SortOption`s (shortest/longest validity),
    `MAX_OFFER_PRICE_KSH = 1500`, three larger offers (3 GB weekly, 8 GB monthly,
    Monthly Mega) and a 6-slide promotions pool. Default price filter raised to
    1500 so the new offers are not hidden.
  - **`feature/home/CatalogueLogic.kt`** (new, pure/unit-tested): Nairobi-day
    helpers, `filterAndSortOffers`, `sortOffers` (5 orders), `validityRankMinutes`,
    `dailyStateFor` (per-recipient, per-day), `deriveHomeSections`, `suggestSimilar`
    (category-affinity, empty when no signal), `selectPromotions`.
  - **`feature/home/CatalogueViewModel.kt`** (new): screen-level ViewModel,
    type-safe 5-flow combines → `HomeUiState`/`OffersUiState`, injectable clock.
  - **`feature/home/PromotionBillboard.kt`** (new, delegated): manual-swipe
    `HorizontalPager` of solid brand-colour slides (NO gradient), page dots,
    optional `imageRes` + scrim, breathing CTA suppressed under reduced motion,
    no auto-rotation.
  - **`feature/home/OfferDetailsSheet.kt`** (new): offer details bottom sheet
    with **Buy bundle**; disables buy with a plain reason when not purchasable or
    offline+once-per-day. Honest language, no delivery claims.
  - **`feature/home/HomeScreen.kt`** rebuilt to the Plan.md §5.2 order (greeting,
    search, category shortcuts, one billboard, Popular, Bought today, More offers,
    Buy again, Favourites, "You might also like"), skeleton/empty states,
    favourite Undo snackbar, hoisted list state.
  - **`feature/offers/OffersScreen.kt`** rebuilt: filter sheet has price range +
    validity + all 5 sorts; loading/empty-from-filters/empty/offline states;
    favourite Undo; scroll + filters + query + sort preserved.
  - **`core/ui/OfferCard.kt`** rebuilt as a pure selection surface — removed the
    compact Buy button (Plan.md §5.3), added calm daily-state labels.
  - **`MainActivity.kt`**: wired the ViewModel + offer-details flow + promotion
    intents + hoisted list state; **navigation fix** — every jump to a tab route
    uses one consistent `popUpTo("home"){saveState} + launchSingleTop +
    restoreState`, guarded against re-navigating the current route, with
    reselect-to-top. Repo param typed to the `BingwaRepository` interface.
  - **Tests:** `test/.../feature/home/CatalogueLogicTest.kt` (added, reviewed).
    ViewModel + Compose tests generated by a sub-agent (see Next).
  - **Docs:** CHANGELOG `[Unreleased]` updated (Added/Changed/Fixed).
- **Decisions/assumptions:**
  - **Explicit-user-override of two design.md guidelines, scoped safely:** the
    breathing CTA (design.md forbids a pulsing CTA) and the swipe carousel are
    honoured because the user was explicit, BUT only on the advert billboard
    (never on checkout/payment CTAs), the breathing freezes under reduced motion,
    and the carousel never auto-rotates (that hard prohibition is kept). Follows
    CLAUDE.md §1 and does not touch payment/security/release.
  - **Navigation fix crosses the phase's "must not own global navigation"
    boundary** — done deliberately because the user explicitly reported the bug.
    Flagged for the integration coordinator.
  - Promotion "randomly posts mostly big offers": ordered by `priorityWeight`
    (monthly/weekly/high-value highest) with a Nairobi-day seed breaking ties.
  - Kept `dailyRule` for back-compat (PurchaseBottomSheet still reads it);
    `purchasePolicy` is the new source of truth for awareness.
  - Did NOT introduce Hilt (Phase 1 deferred it); ViewModel created via a plain
    `ViewModelProvider.Factory`. Canonical per-recipient Nairobi-day ledger is
    still Phase 6 — Phase 3 only presents state from local records.
- **Verification:** No local build possible (no JDK/SDK). Static self-review: all
  `BingwaRepository` members implemented; no stale screen/card signatures; no
  `Brush`/gradient in scope (only OnboardingScreen, Phase 2); `CatalogueLogicTest`
  assertions hand-traced against the implementation and all pass. Authoritative
  compile/test is the GitHub Actions run (pending push).
- **Git:** Branch `feature/catalogue-experience` (base = Phase 1 `main` at
  `2cd3d8c`, tip `43d8dab`). Commit + push pending. `main` NOT touched.
- **Risks/blockers:**
  - **Animated GIFs / remote images NOT supported** — needs an image-loading
    library (Coil `coil-compose` + `coil-gif`), not added without a build to
    verify. Static bundled `imageRes` artwork IS supported. Adding Coil + a promo
    media host is the next step for the full "TV with gifs".
  - CI must confirm the Compose/pager/ViewModel code compiles on the runner
    (Compose BOM 2024.09.00 has `HorizontalPager`). Unverified until green.
  - Three pre-existing uncommitted copy tweaks outside Phase 3 scope
    (`ActivityScreen.kt`, `HelpScreen.kt`, `PurchaseBottomSheet.kt`) left unstaged.
- **Next:** Review the delegated ViewModel/Compose tests, commit the Phase 3
  files (excluding the 3 unrelated tweaks), push `feature/catalogue-experience`,
  watch GitHub Actions; fix any compile error on-branch without weakening tests;
  hand off to the integration coordinator with the nav-fix note. For full advert
  media, add Coil + a promo image/gif host (business input) later.

### 2026-07-24 23:55 EAT — Phase 3 follow-up: CI green, debug APK produced

- **Objective:** Get an authoritative build/test result for the Phase 3 branch.
- **Result:** **Green.** First push (`340026c`) failed `compileDebugKotlin` with
  import-only errors: `OfferDetailsSheet`/`CatalogueViewModel` referenced
  `OfferDailyState`/`DailyStateKind` without importing them (the types moved to
  `core.model`), and `PromotionBillboard` used `by animateDpAsState/animateFloatAsState`
  without `androidx.compose.runtime.getValue`. Fixed in `67a8290` (imports only,
  no behaviour change). Re-run **passed every gate**: assembleDebug + debug-APK
  upload + unit tests (`CatalogueLogicTest`, `CatalogueViewModelTest`, and the
  three Robolectric Compose tests) + lint.
- **Verification:** GitHub Actions "Feature debug build" run `30118626852` on
  `feature/catalogue-experience` @ `67a8290` = success (2m42s). All Phase 3 tests
  ran and passed on the runner. Not yet installed on a physical phone.
- **Git:** `feature/catalogue-experience` green at `67a8290`, pushed. `main`
  untouched (coordinator owns it).
- **Artifact:** `my-bingwa-debug-67a8290` (18 MB, under the 30 MB target) →
  `My-Bingwa-Debug-67a8290.apk` (debug, versionName 1.0 / versionCode 1).
  Workflow "Feature debug build", run `30118626852`.
- **Risks/blockers:** Physical-phone acceptance pending (fonts, billboard swipe +
  breathing CTA, light/dark, 200% text, reduced motion, nav Home-from-Offers).
  Animated-GIF/remote promo media still needs Coil + a media host (unchanged).
- **Next:** Install `My-Bingwa-Debug-67a8290.apk` on the phone and verify the
  nav fix + billboard + personalisation; then the integration coordinator merges
  `feature/catalogue-experience` (note the intentional MainActivity nav change).

### 2026-07-24 (later) — Owner correction: simpler Home, classic offer card, real offers

- **Objective:** The owner rejected the Phase 3 UI redesign (they wanted feature
  logic, not a redesign) and gave explicit direction: simplify Home; revert the
  offer card; load the real catalogue; move the billboard CTA; push everything to
  `main` for a fresh APK. Explicit instruction overrides Plan.md/design.md here.
- **Result:** Done in code on `feature/checkout-state-machine` (which already
  carries Phase 3 + Phase 4). Build/test authority is CI.
- **Changed:**
  - HomeScreen rewritten simple: removed search bar + Popular/Bought today/Buy
    again; after the billboard only **Your favourites** (vertical) and **You may
    also like** (horizontal LazyRow). Kept greeting + category tiles + favourite
    Undo.
  - OfferCard reverted verbatim to the pre-Phase-3 classic (Buy button + few
    details) via `git show 43d8dab:…OfferCard.kt`. OffersScreen + MainActivity
    rewired to the old signature (`onBuyClick`); card tap / Buy / promotion all
    open the purchase sheet directly. Removed the interim `OfferDetailsSheet.kt`.
  - Real catalogue (29 offers: 13 Data, 5 SMS, 8 Minutes, 3 Special) with exact
    prices, validity, buy-tags; 3 pre-set favourites so Home is populated.
    `OfferItem.validityBand` added (Hourly/Daily/Weekly/Monthly) + used by the
    validity filter; `validityRankMinutes` now understands "Hr". DailyRule label
    ONCE = "Buy once a day". `MAX_OFFER_PRICE_KSH` = 1005. Promotions relinked to
    real high-value offers.
  - Billboard CTA moved to CenterEnd with more height/padding (agent) so the
    label no longer clips.
  - Tests updated: OfferCard/Home Compose tests + ViewModel test adjusted to the
    reverted card, simpler Home and non-popular catalogue.
- **Files:** `core/model/OfferItem.kt`, `core/ui/OfferCard.kt`, `feature/home/
  HomeScreen.kt`, `feature/home/CatalogueLogic.kt`, `feature/offers/OffersScreen.kt`,
  `MainActivity.kt`, `data/fake/BingwaRepository.kt`, `data/fake/FakeBingwaRepositoryImpl.kt`,
  `feature/home/PromotionBillboard.kt`; deleted `feature/home/OfferDetailsSheet.kt`;
  updated 3 test files.
- **Git:** committing on `feature/checkout-state-machine`; per owner instruction
  this branch (Phase 3 + Phase 4 + this correction) will be **merged to `main`**
  once CI is green, so a fresh debug APK is produced.
- **Risks/blockers:** CI must confirm compilation. Physical-phone acceptance
  still pending.
- **Next:** Commit, push, watch CI; if green, merge to `main` and report the APK.

### 2026-07-24 (later) EAT — Phase 4: checkout payment state machine + Daraja-via-backend

- **Objective:** Give the checkout real logic — the payment state machine, honest
  STK states behind a payment-repository interface, offline signed-config
  Till/Paybill, and a real Daraja integration for buy-for-myself (buy-for-another
  stays mocked; user supplies backend/Daraja credentials).
- **Result:** **Implemented (source complete; unverified locally — no JDK on this
  machine, CI is the gate).** Not yet built/tested on CI or phone.
- **Key decision — Daraja is backend-proxied, never in the APK.** Asked the user
  how the app should reach Daraja; they chose the **backend proxy** (recommended).
  So the app calls *our* backend (`payments/stk`, `payments/status`), which holds
  the consumer key/secret + STK passkey and owns the Daraja CallbackURL. No secrets
  in the app (CLAUDE.md §2/§10). Direct-from-app was explicitly rejected as it would
  bake extractable secrets into the APK. Base URL is a **non-secret** BuildConfig
  field `PAYMENTS_BASE_URL` (Gradle prop `paymentsBaseUrl` / env `PAYMENTS_BASE_URL`,
  empty by default). When empty, the app uses a clearly-labelled local
  **simulation** so it stays testable — it never fakes a real "success".
- **Changed (new files):**
  - `core/payment/PaymentTxnState.kt` — the Plan.md §6 state machine states with the
    exact customer copy; `toRecordStatus()` maps to `PaymentStatus`.
  - `core/payment/PaymentStateMachine.kt` — pure transition table + events; illegal
    transitions throw (never optimistically confirm).
  - `core/payment/KenyanPhone.kt` — E.164 normalisation / display / MSISDN (Plan §5.5).
  - `data/payment/PaymentGateway.kt` — the payment-repository interface + request/result.
  - `data/payment/PaymentApi.kt` + `BackendPaymentGateway.kt` — Retrofit backend proxy
    (reflective Moshi; no KSP). Maps backend status strings → state machine.
  - `data/payment/SimulatedPaymentGateway.kt` + `PaymentGatewayProvider.kt` — labelled
    simulation + backend/simulation selector.
  - `data/payment/OfflinePaymentConfig.kt` + `OfflineEligibility.kt` — signed-config
    interface (Till/Paybill + validity/signature) with expired/invalid/missing states;
    pure eligibility: expiry, ambiguity (shared amount on same route), Till/Paybill
    route, hard-once-per-day offline block.
  - `data/payment/ActiveOrder.kt` — process-death restoration **contract** (in-memory
    now; Phase 6 persists; integration re-opens the sheet).
  - Tests: `PaymentStateMachineTest`, `KenyanPhoneTest`, `OfflineEligibilityTest`,
    `PaymentRepositoryTest` (idempotent double-tap, honest offline receipt).
- **Changed (edits):** `PaymentStatus` +EXPIRED/+NOT_CONFIRMED/+COULD_NOT_VERIFY and
  `PurchaseRecord` +clientRequestId/+orderReference; `FakeBingwaRepositoryImpl` now
  delegates to the gateway (idempotency on clientRequestId, poll-to-terminal, honest
  "still checking", bought-today/notif/recents), offline receipt → Waiting to verify
  / no receipt → Payment not confirmed, `offlineEligibility`/`offlineConfig`/`activeOrder`;
  `PurchaseBottomSheet` rewritten for honest results (Payment received with no delivery
  timeframe; Payment cancelled/failed, Request expired, Still checking, We could not
  verify), airtight double-tap, Resend-after-delay, offline signed-config steps +
  receipt entry + expired/ambiguous notices, spec labels "Bundle recipient" /
  "M-Pesa payment number"; `MainActivity` builds the gateway from BuildConfig;
  `ActivityScreen` `when`s extended for the new statuses; `build.gradle.kts` adds the
  `PAYMENTS_BASE_URL` field.
- **Decisions/assumptions:** Buy-for-another routes through a dedicated simulation
  even once the backend URL is set (kept mocked per the user). Fixed the checkout
  Till/Paybill to read from the signed config (single source of truth) — Help-screen
  card-2 Paybill `4050595` vs `40450595` mismatch remains in Phase-5-owned HelpScreen
  and is left untouched. Removed the design.md-conflicting recipient label tweak
  ("Number to receive" → spec "Bundle recipient").
- **Verification:** No local build possible (no JDK; SDK-only). Pure unit tests
  written for the state machine, phone, eligibility and repository idempotency; they
  run in CI. Not yet run.
- **Git:** Branch `feature/checkout-state-machine` off `feature/catalogue-experience`
  HEAD (`78a043c`) — that base carries the uncommitted Phase-3 tweaks + this phase.
  Not pushed yet at time of writing; `main` untouched (coordinator owns it).
- **Risks/blockers:** (1) No backend yet → buy-for-myself runs on the simulation
  until `PAYMENTS_BASE_URL` is set and the two endpoints exist. (2) CI unverified —
  first push may surface a compile error to fix on-branch. (3) Physical-phone
  acceptance pending. (4) Real signed offline config + Keystore-backed persistence +
  true process-death restore are Phase 6/7.
- **Next:** Push `feature/checkout-state-machine`, watch GitHub Actions, fix any
  compile error on-branch. Provide the backend base URL + implement `POST payments/stk`
  and `GET payments/status` (status strings PAYMENT_REQUESTED/AWAITING_APPROVAL/
  PAYMENT_CONFIRMED/CANCELLED/PAYMENT_FAILED/TIMED_OUT) to switch buy-for-myself to
  real Daraja STK.

---

## 2026-07-24 ~23:32 EAT (Africa/Nairobi) — Phase 5 (partial): notification overlay + Settings in bottom nav

- **Request/objective:** Owner-directed subset of Phase 5. (1) The notification
  page opened as a standalone route with no app chrome — convert it to an in-app
  slide-up overlay modal; notifications must be readable, copyable and clearable.
  (2) On Home the top-right had both a notification bell and a profile avatar
  (→ Settings); remove the avatar, keep the bell there, and move Settings into
  the bottom navigation as a real, navigable destination. Explicit constraint:
  **no UI redesign** of any screen; Help and Activity left untouched.
- **What changed:**
  - `feature/notifications/NotificationsScreen.kt`: `NotificationsScreen(...onBack)`
    replaced by `NotificationsSheet(...)` built on `ModalBottomSheet`
    (`BottomSheetTopShape`, 0.9f height). Same visual rows/sections/empty/disabled
    states as before (no redesign) plus per-row **Copy** (clipboard + "Copied"
    toast) and **Clear** (delete) icon buttons, a header **Clear all**, and
    deep-link routing (`onDeepLink` + auto-dismiss) for notifications that carry a
    route. Test tags added (`notifications_sheet`, `notification_row/copy/delete_*`,
    `clear_all_button`).
  - `core/ui/MyBingwaTopAppBar.kt`: removed the profile avatar block and
    `onProfileClick` param; the bell is now the only trailing control.
  - `core/ui/MyBingwaBottomNav.kt`: added `SETTINGS("settings", …, Icons.Outlined.Settings)`
    as a 5th destination; tightened row/item horizontal padding (12→6 / 16→10 dp)
    so five labelled tabs fit small-width phones.
  - `feature/home/HomeScreen.kt`: dropped `onProfileClick` (param + top-bar wiring).
  - `MainActivity.kt`: removed the `composable("notifications")` route; added
    `showNotifications` state; bell + `PromotionKind.UPDATE` now open the overlay;
    rendered `NotificationsSheet` above the scaffold body; Settings continues to
    be a route (now also reached via the bottom nav). `showBottomBar` already
    included "settings".
  - `data/fake/BingwaRepository.kt` + `FakeBingwaRepositoryImpl.kt`: added
    `deleteNotification(id)` and `clearAllNotifications()`.
  - Tests: `data/fake/NotificationRepositoryTest.kt` (read / mark-all / delete /
    clear-all); `core/ui/MyBingwaBottomNavComposeTest.kt` (Settings renders +
    routes); `HomeScreenComposeTest.kt` updated for the removed `onProfileClick`.
- **Files changed:** the eight above + `CHANGELOG.md`, `memory.md`.
- **Decisions/assumptions:** A `ModalBottomSheet` is the "overlay up modal" — its
  scrim intentionally dims the shell; dismissing returns to the same screen with
  the bottom nav intact (fixes the "standalone page, no navigation" complaint).
  Adding Settings as a 5th bottom-nav item overrides design.md §12.1 "exactly
  four" — done under the owner's explicit current instruction (source-of-truth #1)
  and recorded here. `userName` param left on the top bar (now unused) to avoid
  extra call-site churn. Deep-link seeds are currently all null, so that path is
  wired but inert until notifications carry routes.
- **Verification:** No local build possible (this PC has no JDK — CI-first).
  Static self-review only: icons (`ContentCopy`, `DeleteOutline`, `Settings`) are
  in `material-icons-extended` (already a dependency); no `allWarningsAsErrors`/
  lint-abort config; grep confirms no lingering `NotificationsScreen`/
  `onProfileClick` references. New unit + Compose tests written, not yet run.
- **Git:** Branch `feature/activity-support-settings` off
  `feature/checkout-state-machine` HEAD (`2f676f9`) — that base carries the
  Phase-3/4 deps this work needs. Feature branch to be pushed to trigger CI.
- **Risks/blockers:** (1) CI unverified — first push may surface a compile error
  to fix on-branch. (2) Physical-phone acceptance pending (light/dark/small-width/
  200%-text/reduced-motion of the overlay + 5-tab bar). (3) **`main` is diverged**
  from this line (origin/main integrated Phase 3 differently; HEAD has 14 commits
  incl. checkout not in main) — a direct merge to main would drag in unreviewed
  work and risk conflicts, so main was NOT pushed; coordinator/integration owns it.
- **Next:** Push `feature/activity-support-settings`, watch GitHub Actions, fix any
  compile error on-branch, then install the debug APK artifact and run the phone
  acceptance loop. Decide with the owner how to integrate to main given the
  divergence.

### 2026-07-24 (later) EAT — Daraja via owner's cPanel PHP (no separate backend)

- **Objective:** Owner rejected a standalone backend and rejected baking Daraja
  secrets into the APK. Decided: a tiny PHP API on the owner's existing **cPanel**
  holds the Daraja creds and runs STK; the app calls it. Build the PHP + wire the app.
- **Result:** **Implemented (source complete; unverified locally — no JDK; CI is the
  gate).** Backend not yet hosted by owner.
- **Why cPanel PHP:** Daraja STK can run without our own server (OAuth + STK + status
  poll), but the creds would be extractable from the APK. GitHub secrets hide creds
  from the repo, NOT from the shipped APK. The owner already has cPanel + MySQL, so a
  4-file PHP API keeps the Daraja key/secret/passkey on the server and gives a real
  CallbackURL. No backend "system" — ~5 short PHP files.
- **Changed (new):** `server/mybingwa-api/` — `stk.php` (OAuth→STK, server recomputes
  price from offerId, idempotent on clientRequestId), `callback.php` (Daraja posts
  result → DB), `status.php` (poll; falls back to Daraja stkpushquery if the callback
  is slow), `lib.php`/`db.php`/`offers.php`/`config.php` (placeholders, `.htaccess`
  blocks internal files), `schema.sql`, and a beginner **README.md** cPanel walkthrough
  (subdomain, AutoSSL, MySQL, phpMyAdmin import, File Manager upload, Daraja callback,
  GitHub secrets, testing).
- **Changed (app):** `PaymentApi` paths → `stk.php`/`status.php`; `BackendPaymentGateway`
  + provider now attach an `X-App-Key` header from a new non-secret `PAYMENTS_APP_KEY`
  BuildConfig field (shared app token, NOT a Daraja credential); repo STK loop now
  waits `POLL_INTERVAL_MILLIS` (3s) between up to 10 polls for real confirmation;
  CI workflow injects `PAYMENTS_BASE_URL` + `PAYMENTS_APP_KEY` from GitHub secrets (empty
  → app uses the simulation). The Kotlin payment contract was already shaped for this,
  so the change was mostly config + paths + the app-key header.
- **Decisions/assumptions:** Buy-for-myself → real Till STK via cPanel; buy-for-another
  stays the simulation (owner will add it to cPanel later, plus manage offer prices there).
  `offers.php` mirrors the catalogue prices for now (server is price-authoritative).
- **Verification:** No local build (no JDK). Existing payment unit tests unchanged and
  still pure/virtual-time (poll delay auto-advances under runTest). CI to confirm.
- **Git:** Branch `feature/activity-support-settings`. Committing only my files (server/
  + payment wiring + CI); owner's other WIP untouched. `main` not pushed.
- **Risks/blockers:** (1) Owner must host the PHP + fill creds + set the Daraja callback
  + add the two GitHub secrets before real STK works; until then the app simulates.
  (2) For a Till, `business_shortcode` must be the HO/store code tied to the till with a
  matching passkey — owner to confirm from the Daraja portal. (3) Go-Live required for
  production (sandbox first).
- **Next:** Push branch, watch CI. Walk the owner through cPanel using
  `server/mybingwa-api/README.md`; then phone-test a real STK to own number.

---

## 2026-07-25 EAT (Africa/Nairobi) — Phase 5+: notification/SMS/connectivity system, splash logo, 2 reverts + billboard fix

- **Request/objective (owner, mid-turn, "just proceed, delegate"):** (1) Settings push toggle must request notification permission; build real system-bar notifications with the brand icon, non-noisy. (2) Activity-aware notifications: bundle suggestions, app update, hot deals. (3) Know connection state (Wi-Fi/mobile/both). (4) Watch Safaricom SMS to detect DELIVERY (data/SMS/minutes) and LOW-BALANCE (`SAF_Balance`), update Activity status quietly (never shout "received" every time), suggest offers. Templates must be server-syncable, not hardcoded (server built later). (5) Fix launch splash using the real asset logo. (6) Revert the Offers filter redesign; (7) revert the payment-received modal; (8) fix billboard CTA overlapping text (screenshot). Push to main for an APK.
- **What changed / how (delegated to parallel agents, non-overlapping files; I integrated + reviewed):**
  - **Splash (me):** replaced crude `drawable/ic_splash_logo.xml` with the approved `my-bingwa-splash-mark-512.png` → `drawable-nodpi/ic_splash_logo.png`. Launcher + onboarding logos were already the real mark.
  - **Notification core (new `core/notifications/`):** `NotificationChannels` (Transactions/Offers/Reminders/Updates), `AppNotifier` (ic_stat_my_bingwa + BrandGreen, deep-link `EXTRA_DEEP_LINK_ROUTE="deep_link_route"`, quiet, dedup), `ConnectivityObserver` (`enum ConnectionState{NONE,WIFI,CELLULAR,BOTH}`), pure `SafaricomSmsParser` + `SmsTemplates`/`DefaultTemplates` (seed from the 4 real samples) + `TemplateProvider`/`LocalSeedTemplateProvider` + `RemoteTemplateSync` stub (server seam), `OfferSuggestionEngine`. kotlinx.serialization NOT wired (deferred) → template models are plain data classes shaped for it. Tests: parser + suggestion engine.
  - **Integration:** manifest adds `ACCESS_NETWORK_STATE` + `RECEIVE_SMS` + `<receiver .notifications.SmsDeliveryReceiver>` (BROADCAST_SMS, SMS_RECEIVED) + MainActivity `launchMode=singleTop`. New `notifications/SmsDeliveryReceiver` + `notifications/SmsSignalBus` (MutableSharedFlow seam). `PurchaseRecord` gains `isDeliveryConfirmed=false`. Repository gains `connectionState`/`setConnectionState`/`onBundleDeliveryDetected`/`onLowBalanceDetected` (honest, Safaricom-attributed, §8 language; quiet in-app note, no loud post). `ActivityScreen` shows a small "Safaricom confirmed delivery" line. `SettingsScreen` gains `onEnablePushNotifications`/`onEnableSmsDetection` callbacks + two rationale dialogs + SMS toggle. `MainActivity` creates channels, builds AppNotifier/ConnectivityObserver, collects connectivity + `SmsSignalBus`, handles POST_NOTIFICATIONS/RECEIVE_SMS launchers, and notification-tap deep-links (StateFlow + onNewIntent). New `SmsReconciliationTest`.
  - **Filter revert:** `feature/offers/OffersScreen.kt` — reverted Phase-3 redesign styling to classic; kept category/price/validity/5 sorts; public signature unchanged (removed only the private composable's `resultCount`).
  - **Billboard fix:** `feature/home/PromotionBillboard.kt` — CTA was `align(CenterEnd)` overlapping text; now a `Row` (text `weight(1f)` + spacer + CTA), `heightIn(min=190)`; no overlap at small width/200% font. Signature + test tags unchanged.
- **Payment-received modal — NOT changed (needs owner target):** an agent traced git history and found the result modal was **never visually redesigned** — it already IS the classic layout. The only diffs from the pre-Phase-4 baseline are the §7-mandated honesty fixes (old text falsely said "Purchase successful / bundle in a few minutes"). Reverting would reintroduce forbidden delivery language, so nothing was changed. Owner must name a concrete change (e.g. drop the green badge / the Ref row / shorten copy) if they still want it altered.
- **Files changed:** splash res (2), `core/notifications/*` (8 + 2 tests), `notifications/SmsDeliveryReceiver.kt`+`SmsSignalBus.kt`, `AndroidManifest.xml`, `MainActivity.kt`, `feature/settings/SettingsScreen.kt`, `feature/activity/ActivityScreen.kt`, `feature/offers/OffersScreen.kt`, `feature/home/PromotionBillboard.kt`, `core/model/PurchaseRecord.kt`, `data/fake/BingwaRepository.kt`+`FakeBingwaRepositoryImpl.kt`, `SmsReconciliationTest.kt`, `CHANGELOG.md`, `memory.md`.
- **Decisions/assumptions (scope overrides — owner-directed, recorded):** SMS-based delivery/balance detection EXPANDS locked v1 scope ("does not verify delivery / never show delivered") and **§16 "never invent delivery confirmation."** Justified as relaying Safaricom's OWN confirmation (not invented), phrased honestly and attributed to the carrier; delivery is reflected in Activity + a quiet in-app note, never a loud "received" and never a "we delivered/activated" claim. `RECEIVE_SMS` is Play-restricted → declared for direct-APK; Play AAB must strip it (documented in manifest + CHANGELOG Security). Minor known gap: the in-app push/SMS toggles don't persist to DataStore yet (OS permission is what matters); Phase 6 persistence.
- **Verification:** No local build (no JDK). Thorough static review of every integration point: payment `create(appKey)` + `BuildConfig.PAYMENTS_APP_KEY` resolve; `ConnectionState` import path; `OfferCategory` `when` exhaustive; `AppNotifier` icon/API; `SmsSignal` sealed shape matches MainActivity/receiver/repo; SettingsScreen/OffersScreen/PromotionBillboard public signatures unchanged. CI is the authority — pending.
- **Git:** Branch `feature/activity-support-settings`. To commit + push this branch; CI produces the debug APK. `main` NOT pushed (diverged; coordinator owns it — owner's earlier standing decision).
- **Risks/blockers:** (1) CI unverified — blind integration may surface a compile error to fix on-branch. (2) Physical-phone acceptance pending (permission prompts, real Safaricom SMS parsing, connectivity, deep-links, dark/200%/small-width). (3) Background SMS reconciliation only runs while the app is foreground (collectors in the composable) — a lifecycle/service collector is future work. (4) Payment-modal change awaits an owner-specified target.
- **Next:** Commit, push `feature/activity-support-settings`, watch GitHub Actions, fix any compile error on-branch, report the debug APK artifact; get the owner's specific payment-modal target.

### 2026-07-25 EAT — Phase 6 offline-first + Phase 7 (admin + server sync); main is unrelated history

- **Objective:** Owner: build Phase 6 (offline-first — the app's distinctiveness) then continue autonomously; server = owner's cPanel at https://mybingwa.blazetechscope.com (now the app's server for sync + admin). Push to main / get APK.
- **Result:** **P6 + P7 done and CI-green on the branch.** Main NOT merged (unrelated history — see blockers). Full Room persistence / total mock removal NOT done (deliberately — see risks).
- **Server secured:** live endpoints verified (status.php 401, stk.php 405, callback.php ack, config.php 403). `config.php` was tracked with REAL Daraja creds → untracked + gitignored; committed `config.sample.php` template (commit 9709daa).
- **P6 (a8d9c81, green):**
  - Real connectivity now drives `isOffline`: `setConnectionState(NONE)` ⇒ offline (ConnectivityObserver already wired).
  - Offline buy = manual M-Pesa: offline skips the online review; the offline step has a prominent **Copy Till/Paybill & open M-Pesa** action (copies value + opens SIM Toolkit; self=Till, another=Paybill). Honest "I've paid" receipt tracking kept.
  - Server config sync: Till/Paybill/support/WhatsApp fetched from `get_config.php` when online, cached in SharedPreferences, baked-in defaults for a fresh offline install. New `data/config/{AppConfig,RemoteConfigSource,AndroidRemoteConfigSource}`; repo `appConfig`+`syncRemoteConfig`; `offlineConfig()` + Help read it (fixes Help Paybill mismatch). Server: `get_config.php` + `settings.sql`.
- **P7 (afc8d9c, green in integrated run ac50462):**
  - **Admin panel** `server/mybingwa-api/admin/` — brand-styled, password-protected (config.php admin_user/admin_pass), manages offers, payment/support settings, notification templates; creates its own tables on first load.
  - Server `get_offers.php` + `offers.sql`/`templates.sql` seeds; `config.sample.php` gained admin creds + fallback fields.
  - App: `data/catalogue/{RemoteCatalogueSource,AndroidRemoteCatalogueSource}`; repo `syncCatalogue()` replaces offers with the server list when online (preserving favourite/bought-today), keeps the bundled catalogue offline. MainActivity syncs config+catalogue whenever online.
  - Tests: connectivity→offline, config seed/sync, catalogue sync/fallback/favourite-preserve. All green.
- **Verification:** CI run 30133932330 @ ac50462 = success (assemble + unit tests + lint). No local build (no JDK).
- **Git:** All on `feature/activity-support-settings` (owner is editing it concurrently — e.g. PaymentTxnState heading → "Purchase Successful" + matching test, uncommitted). I committed only my own files. APK artifact: `my-bingwa-debug-ac50462`.
- **Risks/blockers:**
  1. **main has UNRELATED history** to this branch (24 vs 19 commits, no common ancestor; `git merge` refuses). Cannot be auto-merged — needs an owner decision on reconciling the two lineages. NOT done. APK does not require main.
  2. Owner must UPLOAD the new server files (get_config.php, get_offers.php, admin/, updated config.sample→config with admin creds) + optionally import offers.sql/settings.sql, and add GitHub secrets PAYMENTS_BASE_URL/PAYMENTS_APP_KEY, before server sync/admin work on the phone.
  3. **NOT implemented (honest):** full Room/DataStore persistence and total removal of the in-memory `FakeBingwaRepositoryImpl` ("no mock data"), and phases 8+. Judged unsafe to rush into a branch the owner is editing concurrently; server is sync-only and the app is fully usable offline as-is.
  4. Offline once-per-day / ambiguity eligibility gate was intentionally dropped for the offline manual flow per owner ("buy just opens M-Pesa").
- **Next:** Owner decides how to reconcile branch vs unrelated main. Deploy the new server files + secrets. Then physical-phone test: offline detection, offline copy+SIM-toolkit, online sync of offers/config, admin panel. Full persistence (Room) is the next big build.

---

## 2026-07-25 EAT — Persistent logo fix (launcher + header) + Special glitter

- **Request:** App launcher icon + Home header still showed a MOCK logo (not in assets); owner tried removing it but it persisted. Find/remove it and all dependencies, replace with the real asset. Also make the Home "Special" star icon glitter. Owner moved the asset kit from root `assets/` to `my-bingwa/assets/`.
- **Root cause:** the mock was the drawn vector `drawable/ic_mybingwa_symbol.xml`. It fed BOTH the header (`MyBingwaTopAppBar`, `OnboardingScreen` via `R.drawable.ic_mybingwa_symbol`) AND the adaptive launcher foreground (`drawable/ic_launcher_foreground.xml` is a safe-zone layer-list wrapping `@drawable/ic_mybingwa_symbol`). On Android 8+, the adaptive icon (`mipmap-anydpi-v26/ic_launcher.xml`) overrides the PNG mipmaps, so replacing mipmaps alone never fixed it. The legacy mipmap PNGs were ALREADY the real logo (overwriting them was a no-op).
- **Fix:** replaced the two mock symbol drawables with real asset PNGs, keeping the existing layer-list/background wiring so the launcher stays safe-zone-correct:
  - `drawable/ic_mybingwa_symbol.xml` → DELETED; added `drawable-nodpi/ic_mybingwa_symbol.png` (from `my-bingwa/assets/my-bingwa-logo-kit/brand/my-bingwa-symbol-transparent-512.png`). Fixes header + onboarding + adaptive foreground (both use `tint=Unspecified`, full colour).
  - `drawable/ic_mybingwa_symbol_mono.xml` → DELETED; added `drawable-nodpi/ic_mybingwa_symbol_mono.png` (from asset `adaptive/ic_launcher_monochrome.png`). Fixes the themed monochrome layer.
  - Kept `ic_launcher_foreground.xml` (72dp centred layer-list → safe zone), `ic_launcher_background.xml` (#F6F9FC), `ic_launcher_monochrome.xml`, and the `anydpi-v26` adaptive XMLs — they reference the now-real drawables.
- **Special glitter:** `feature/home/HomeScreen.kt` — `CategoryShortcutTile` gains `twinkle` (SPECIAL only, `!reducedMotion`): a gentle infinite `rememberInfiniteTransition` scale 0.9→1.14 + alpha 0.7→1 (`tween 1100ms FastOutSlowInEasing`, Reverse) via `graphicsLayer`. `CategoryShortcutRow` now takes `reducedMotion`. Added animation-core + graphicsLayer + getValue imports.
- **Files changed (committed):** `HomeScreen.kt`, `res/drawable-nodpi/ic_mybingwa_symbol.png` (+`_mono.png`), deleted `res/drawable/ic_mybingwa_symbol.xml` (+`_mono.xml`), `CHANGELOG.md`, `memory.md`. NOT committed (owner's pending changes): the root `assets/`→`my-bingwa/assets/` move and `past.md`.
- **Verification:** No local build (no JDK). Static review: no leftover `*symbol*.xml`; foreground/monochrome refs resolve to the new PNGs; onboarding/header use `Color.Unspecified`; HomeScreen anim imports present. CI is the authority.
- **Git:** Branch `feature/activity-support-settings`; commit + push; watch CI. `main` still owned by coordinator.
- **Next:** Confirm CI green; owner installs the APK to verify the real icon on the launcher + header and the Special twinkle. Note: docs still reference the old root `assets/` path (owner moved it) — update if it becomes authoritative.

---

## 2026-07-25 EAT — Make the audited "fake" areas real: persistence, payment routing, callback security

- **Objective:** After a deep audit found several docs claims were mock/unwired, the
  owner asked to "implement anything claimed real but fake" and push. Delegated the
  server work to a subagent; did the Android work directly (no local build — CI is the
  gate).
- **Result:** Implemented (source complete; **CI/phone unverified at time of writing**).
- **Changed (real implementations):**
  - **On-device persistence (NEW):** `data/persistence/LocalStore.kt` — Preferences
    DataStore + Moshi JSON snapshot (no KSP). `FakeBingwaRepositoryImpl` gained
    `localStore` + `fallbackGateway` params; loads on init, `persist()` after every
    mutation. Profile, favourites, purchases/Activity, notifications, recents and the
    active order now survive process death. Backward compatible: no store injected
    (unit tests) ⇒ old in-memory behaviour. Test: `PersistedStateSerializationTest`
    (pure Moshi round-trip incl. enums).
  - **Safe process-death payment restore:** persisted `activeOrder`; on relaunch an
    unfinished order is settled to `WAITING_VERIFY` (never lost, never re-charged).
  - **Buy-for-another is now real:** both routes use the injected backend gateway;
    `StkPushRequest.forSelf`/`StkRequestDto.forSelf` added; server routes another-number
    to Paybill + recipient account. Removed the hardcoded `anotherNumberGateway`
    simulation.
  - **Honest payment config:** `PaymentGatewayProvider.isBackendConfigured(baseUrl,
    appKey)` now requires BOTH; `PAYMENTS_BASE_URL` defaults to the prod host
    (`https://mybingwa.blazetechscope.com/`, non-secret, overridable). New
    `UnavailablePaymentGateway` is the **release** fallback so a misconfigured
    production build fails honestly instead of faking success; debug still simulates.
  - **Real permission toggles:** MainActivity writes the true POST_NOTIFICATIONS /
    RECEIVE_SMS grant into the (now persisted) profile on start + on each result;
    Settings SMS toggle reads `profile.smsAlertsEnabled`. Added `UserProfile
    .smsAlertsEnabled` + repo `setNotificationsEnabled`/`setSmsAlertsEnabled`.
  - **Server hardening (subagent, `server/mybingwa-api/`):** `callback.php` now needs a
    `?token=` shared secret + optional IP allowlist and cross-checks the amount (kills
    the spoofable-callback hole); `stk.php` idempotency is atomic (insert-first on the
    unique client_request_id); `X-App-Key` fail-closed on stk/status; Paybill route in
    `lib.php`. New config keys in `config.sample.php` (no real secrets); `config.php`
    untouched.
- **Files:** NEW `data/persistence/LocalStore.kt`, `data/payment/UnavailablePaymentGateway.kt`,
  test `data/persistence/PersistedStateSerializationTest.kt`; edited `MainActivity.kt`,
  `FakeBingwaRepositoryImpl.kt`, `BingwaRepository.kt`, `UserProfile.kt`, `SettingsScreen.kt`,
  `PaymentGateway.kt`, `PaymentApi.kt`, `BackendPaymentGateway.kt`, `PaymentGatewayProvider.kt`,
  `app/build.gradle.kts`; server `callback.php`/`stk.php`/`lib.php`/`config.sample.php`/`README.md`;
  `CHANGELOG.md`, `memory.md`. Did NOT stage `docs/CLAUDE_KICKOFF_AND_BUILD_PHASES.md`
  (pre-existing working-tree change, not mine).
- **Decisions/assumptions:** Used DataStore+Moshi JSON (not Room/KSP) for persistence —
  real, survives restart, and avoids the KSP2 CI crash that removed Room codegen earlier;
  Room swap can happen later behind the same interface. Default base URL set to the prod
  host reported by the audit (from git-ignored `config.php`); overridable. FCM remote push
  and WorkManager still NOT implemented — FCM needs the owner's Firebase project +
  `google-services.json` (adding the plugin without it breaks the build), documented as
  owner-blocked.
- **Verification:** No local build (no JDK). Static self-review + grep: all
  `BingwaRepository` members implemented (only `FakeBingwaRepositoryImpl` implements it);
  all tests construct the repo with named args so the new ctor params don't break them;
  `isBackendConfigured` two-arg call site updated. Authoritative build = GitHub Actions.
- **Git:** Branch `feature/real-payments-persistence` off `feature/activity-support-settings`
  HEAD (`6c52f89`). Commit + push; drive CI green on-branch. **`main` NOT merged** — it has
  an UNRELATED history (no common ancestor with this lineage; `git merge` refuses). Forcing
  would destroy the other lineage's work, so it needs an owner decision. Recorded, not forced.
- **Risks/blockers:** (1) CI must confirm the DataStore/Moshi + payment changes compile.
  (2) Real STK in the shipping app still needs the backend deployed + `PAYMENTS_APP_KEY`
  secret in a signed release job. (3) Owner must register the tokenised Daraja CallbackURL
  (`?token=…`) + set `callback_secret`/`app_key` on the server. (4) main/branch lineage
  reconciliation is an open owner decision.
- **Next:** Push branch; watch CI; fix any compile error on-branch without weakening tests.
  Then owner: decide main reconciliation, deploy server changes + secrets, phone-test.

---

## 2026-07-25 EAT — Live STK diagnosis: fixed instant-fail + no-prompt (real KSh 1 test)

- **Objective:** Owner reported "initiating STK fails instantly" and asked me to test a
  real KSh 1 STK to 0727921038; also revert buy-for-another to a mock (different, unbuilt
  integration).
- **Result:** Diagnosed + fixed, validated with a REAL Daraja STK (owner confirmed the
  Paybill prompt arrived on the phone).
- **How tested:** No phone/JDK here, but `curl`+`openssl` + the local (git-ignored)
  `config.php` let me replicate `lib.php`'s OAuth + STK against production Daraja
  (`api.safaricom.co.ke`). Script in scratchpad (not committed; never printed secrets).
  OAuth 200 (creds valid). First push with `CustomerBuyGoodsOnline` → ResponseCode 0 but
  **no prompt delivered**. Owner confirmed `4050595` is a **Paybill** (Till is `4953696`,
  a later/separate integration). Re-fired with `CustomerPayBillOnline` → **prompt
  delivered** (owner: "it worked").
- **Two root causes + fixes:**
  1. **Instant app failure:** `server/mybingwa-api/offers.php` price map used stale ids
     `off_1..off_16`; the app sends `data_*/sms_*/min_*/spec_*`, so `stk.php` returned
     `UNKNOWN_OFFER`. **Fixed** `offers.php` to the real catalogue ids/prices (matching
     `offers.sql`). Committed.
  2. **Accepted-but-not-delivered:** shortcode `4050595` is a Paybill but config used
     `CustomerBuyGoodsOnline`. **Fixed** local `config.php` `transaction_type` →
     `CustomerPayBillOnline`. NOT committed (git-ignored secrets) — owner must UPLOAD
     `config.php` to the server.
  - **Buy-for-another reverted to a mock** in `FakeBingwaRepositoryImpl` (always a
     `SimulatedPaymentGateway`, never the real gateway) per owner. Committed.
- **Deploy required (owner):** upload `server/mybingwa-api/offers.php` AND the edited
  `config.php` to the live host. The app already reaches the real backend (the instant
  UNKNOWN_OFFER proves the app-key matches + it's configured), so once deployed, real
  Paybill STK works from the app.
- **Note:** `4050595`/`4953696` are temporary (owner: "these values will be changed
  later"). When the Till STK integration is ready, switch self back to
  `CustomerBuyGoodsOnline` with the Till's store number + its own passkey. `callback.php`
  is now fail-closed on the `?token=` secret — owner must set `callback_secret` and
  register the tokenised CallbackURL, else confirmations rely on the status-query fallback.
- **Git:** `feature/real-payments-persistence`; committing offers.php + buy-for-another
  revert + docs. `config.php` never committed.
- **Callback secret (DONE):** generated a 64-hex `callback_secret` and set BOTH
  `config.php` `callback_secret` and the `callback_url` `?token=` to it (they match).
  `callback.php` (deployed) then authenticates Daraja's callback; no Daraja-portal step
  (the CallbackURL is sent per STK request by `lib.php`). Value lives only in the
  git-ignored `config.php` (not in memory). `stk.php` AccountReference for self = "MyBingwa".
- **Full deploy set (upload to web root where callback.php is served):** `config.php`,
  `offers.php`, `stk.php`, `lib.php`, `callback.php`, `status.php`. Import `schema.sql`
  (payments table) once; `offers.sql` optional (catalogue sync). After that the online
  buy-for-myself loop is fully real: Paybill STK → callback confirms (token-authed) →
  status.php reflects it → app shows Payment received. Only buy-for-another stays mocked.
- **END-TO-END VALIDATED LIVE (2026-07-25):** owner deployed the files, imported the
  schema, and paid a real KSh 1 via the deployed `stk.php` (offerId `test_1`). Poll of
  `status.php` returned `PAYMENT_CONFIRMED` **with a real mpesaReceipt (UGPQC0JHRW)** —
  the non-null receipt proves the callback authenticated with `callback_secret` and wrote
  the row (the query-fallback never writes a receipt). Whole online payment chain confirmed
  working in production. The temporary `test_1` KSh 1 offer was removed from `offers.php`
  after the test (owner may re-upload `offers.php` to drop it from the live server too).
  NOTE: the DEPLOYED offers.php STILL has `test_1` (owner uploaded that version), so a KSh 1
  test still works live until they re-upload the cleaned file.

## 2026-07-25 EAT — Buy-for-another implemented (mocked-M-Pesa-SMS fulfilment signal)

- **Objective:** Implement buy-for-another per `docs/Buy For Another Number - Implementation
  Spec.md` (owner got it from another site), adapted to our Paybill setup. "Another AI will
  push to main."
- **Spec essence:** payer pays for a different recipient; money to a (separate) till; on
  success send a MOCKED M-Pesa SMS whose "received from" is the RECIPIENT (not payer) to the
  fulfilment phone, so the operator serves the right line. Sender id must be registered
  (SKYSCOPE in their example); provider `https://sms.blazetechscope.com/v1/bulksms`.
- **Adaptation to our site:** our validated STK is Paybill `4050595` + CustomerPayBillOnline,
  so buy-for-another uses the SAME Paybill (existing `another` route: forSelf=false →
  AccountReference = recipient number). The NEW part is the mocked SMS.
- **Changed:**
  - App `FakeBingwaRepositoryImpl`: un-mocked `anotherNumberGateway` → `gateway ?: fallback`
    (real backend when configured; forSelf=false already flows through StkPushRequest/DTO).
  - `lib.php`: `build_mocked_mpesa_message()` (byte-for-byte Safaricom format, all quirks) +
    `send_mocked_mpesa_sms()` (best-effort, never throws, skips if unconfigured).
  - `callback.php`: on the ATOMIC REQUESTED→CONFIRMED transition (dedup vs Daraja duplicates),
    if payer != recipient, send the mocked SMS with the recipient's number. Never for self.
  - `config.sample.php` + local `config.php`: new keys `fulfilment_phone`, `business_name`,
    `sms_api_url`, `sms_api_key` (owner must fill), `sms_sender_id`. `config.php` not committed.
- **Owner must provide/deploy:** upload `lib.php`, `callback.php`, `config.php`; fill
  `sms_api_key` + confirm `sms_sender_id` is REGISTERED with the SMS provider (else it won't
  deliver — can reuse `SKYSCOPE`); confirm `fulfilment_phone` (default set to 0727921038).
- **Test plan:** fire a buy-for-another STK via stk.php (payer 254727921038, a DIFFERENT
  recipient, forSelf=false, offerId test_1); owner pays; verify (a) status CONFIRMED, (b) the
  mocked M-Pesa SMS lands on the fulfilment phone naming the recipient. Not yet tested.
- **Git:** committing app + lib.php + callback.php + config.sample.php + docs on
  `feature/real-payments-persistence`; `config.php` never committed. Not merged to main (per
  owner, another AI handles main).

## 2026-07-25 EAT — CRITICAL callback fix: Daraja strips ?token= → auth by Safaricom IP

- **Symptom:** buy-for-another payment succeeded on Daraja (ResultCode 0) but our row stayed
  `PAYMENT_REQUESTED` (no confirm, no fulfilment SMS). Instrumented `callback.php` with a
  debug log; it showed Daraja DID hit the callback from IP `196.201.212.74` but with
  `token_present=no` → rejected at the token gate.
- **Root cause (big one):** Daraja **strips the query string** from the CallbackURL, so the
  `?token=<callback_secret>` gate rejected EVERY real callback. This broke ALL payment
  confirmation (buy-for-myself too — it had only worked earlier because the token gate was not
  yet deployed then). "Users pay, no bundle."
- **Fix:** authenticate the webhook by SOURCE IP instead. `lib.php` `callback_authenticated()`
  = token (if it survives, path or query) OR Safaricom IP (hardcoded `196.201.212/213/214.x`
  prefix) OR explicit `callback_ip_allowlist`; `callback.php` uses it; still cross-checks the
  amount. Owner supplied the exact 12 Safaricom callback IPs → added to `config.php`
  `callback_ip_allowlist` (belt-and-suspenders; prefix already covers them).
- **VALIDATED LIVE:** fresh KSh 5 buy-for-another → `status.php` `PAYMENT_CONFIRMED` + real
  receipt `UGPQC0JUDH`; SMS integration separately proven (SKYSCOPE_ mock delivered to
  fulfilment `0111327201`, "received from 254111699734"). Whole buy-for-another loop works.
- **Server deploy done by owner:** `lib.php`, `callback.php`, `config.php`. Debug logging then
  removed from `callback.php` (clean version committed); owner should re-upload the clean
  `callback.php` and delete `callback_debug.log`. `.htaccess` also blocks `.log`.
- **Config now:** `4050595` Paybill (self+another via CustomerPayBillOnline; another sets
  AccountReference=recipient), `transaction_type=CustomerPayBillOnline`, fulfilment_phone
  `0111327201`, sms_sender_id `SKYSCOPE_`, sms_api_key set. `callback_url` no longer needs the
  token (IP auth). Two earlier paid test rows remain unconfirmed (Daraja won't resend); ignore.

## 2026-07-25 EAT — Fresh-install cleanup: no prefilled data, start on onboarding

- **CRITICAL owner report:** the app installed with the owner's real details prefilled and
  skipped onboarding. Root cause: `FakeBingwaRepositoryImpl.defaultProfile` seeded name
  "Bonke"/number "0727 921 038" with `isOnboardingCompleted=true`; and demo purchases/
  notifications/recentRecipients/favourites carried real numbers.
- **Fix:** defaultProfile → empty + `isOnboardingCompleted=false` (MainActivity's
  `startOnboarding=!isOnboardingCompleted` now true on first launch). Removed ALL seeded demo
  data (purchases, notifications, recents, favourites). Added test-only constructor params
  `seedPurchases`/`seedNotifications`/`seedRecentRecipients` (default empty); updated
  SmsReconciliationTest + NotificationRepositoryTest + CatalogueViewModelTest to seed their own.
- **Phone normalisation:** owner reported `0112385760` → `254711238…` (invalid). But current
  code is CORRECT — `KenyanPhone.toE164/toMsisdn` and onboarding `normalizeKenyanPhone` both
  handle `07…`/`01…` (`0112385760` → `254112385760`); `OnboardingPhoneTest` covers `01…`. The
  mangling was a stale APK; the fresh APK is correct. No code change needed there.
- **Git:** `fix/onboarding-and-phone-normalisation` off `main`; CI green (run 30159213720);
  merged to `main` (fast-forward). Fresh APK from main CI.

## 2026-07-25 EAT — Phase 9/10: release identity, signing, Play/GitHub pipeline

- **Objective:** Take My Bingwa to Play Store. Register applicationId `com.bingwasokoni`;
  produce v1.0.0 signed direct APK + Play AAB; create the permanent signing keystore;
  make the app updatable on BOTH Play and GitHub. Phases 9 (release-pipeline) + 10.
- **Result:** Partial — all release ENGINEERING done, pushed to `feature/release-pipeline`;
  the three binary deliverables (direct APK, Play AAB, keystore) are produced by CI runs the
  OWNER must trigger after setting secrets (no local JDK/SDK/keytool — see below). Not merged
  to main (another AI owns main).
- **Hard machine constraint (permanent):** this PC has NO JDK, Android SDK, or keytool. So the
  keystore, APK and AAB CANNOT be built locally; they come out of GitHub Actions (the
  authoritative build env, CLAUDE.md §5.1). This shaped the whole design.
- **Decisions/assumptions:**
  - applicationId (release) = `com.bingwasokoni` (permanent). `namespace` KEPT as `com.example`
    on purpose — it only names generated R/BuildConfig + ~200 source files, is invisible to
    users/Play, and renaming = large risk-only refactor. applicationId is what Play registers.
  - Debug applicationId = `com.bingwasokoni.debug` (`.debug` suffix), label "My Bingwa Dev",
    versionNameSuffix `-debug` → installable alongside release ("My Bingwa"). AGP debug keystore.
  - versionName `1.0.0`, versionCode `1` for BOTH channels (same version). Interpreted the
    owner's "direct v1 / aab v2" as ONE app, two channels — SAME version keeps them
    update-compatible. Bumping Play to 2.0 later is a one-line change if the owner insists.
  - Product flavors on dimension `distribution`: `direct` (keeps RECEIVE_SMS + SmsDeliveryReceiver
    for GitHub build) and `play` (src/play/AndroidManifest.xml removes RECEIVE_SMS + the receiver
    so Play needs no restricted-permission declaration). Variants: directDebug/Release,
    playDebug/Release. Both share applicationId + signing identity.
  - ONE permanent signing key used for BOTH direct APK and Play AAB (CLAUDE.md §12.4). Owner must
    UPLOAD THIS SAME KEY as the Play app-signing key (not let Google generate one) so the Play
    app and the sideloaded APK share a signature and can update each other.
  - GitHub in-app update contract: repo-root `update.json` (raw.githubusercontent .../main/update.json)
    holds latestVersionCode/Name + apkUrl; `core/update/UpdateChecker.kt` (OkHttp + org.json)
    compares to BuildConfig.VERSION_CODE; Settings "Check for updates" now really checks and offers
    a "Download update" button. Play updates natively.
- **Changed (files):**
  - `my-bingwa/app/build.gradle.kts`: applicationId, version 1.0.0/1, UPDATE_MANIFEST_URL
    buildConfig field, `distribution` flavors (direct/play), debug `.debug` suffix + "My Bingwa Dev"
    label, appLabel manifestPlaceholder.
  - `my-bingwa/app/src/main/AndroidManifest.xml`: `android:label` → `${appLabel}` (app + activity).
  - `my-bingwa/app/src/play/AndroidManifest.xml` (new): removes RECEIVE_SMS + SmsDeliveryReceiver.
  - `my-bingwa/app/src/main/java/com/example/core/update/UpdateChecker.kt` (new).
  - `SettingsScreen.kt`: real "Check for updates" (was a stub) + real version (was hardcoded
    "2.4.0") via BuildConfig.VERSION_NAME + "Download update" action.
  - `.github/workflows/feature-debug-build.yml`: assembleDirectDebug + direct debug APK path.
  - `.github/workflows/release.yml` (new): tag `v*`/dispatch → signed assembleDirectRelease +
    bundlePlayRelease, SHA-256, GitHub Release with APK+sha256+AAB; keystore decoded to RUNNER_TEMP
    and removed always(); no push-branch trigger (secrets never reach feature branches).
  - `.github/workflows/bootstrap-keystore.yml` (new): one-time keytool keystore gen; uploads ONLY
    the GPG-AES256-encrypted keystore; prints only public fingerprints; plaintext removed always().
  - `update.json` (new), `PRIVACY.md`, `docs/RELEASE_PLAYSTORE.md`, `RELEASE_NOTES_v1.0.0.md`,
    `CHANGELOG.md` (docs).
- **Verification:** No local build possible (no JDK). Validating via the CI debug build on the
  pushed branch (compiles flavors + manifest merge + BuildConfig fields + UpdateChecker/Settings).
  Result recorded on push. Signed release build NOT verifiable until the keystore secret exists.
- **Secrets the owner must set (GitHub → Actions secrets):** STORE_PASSWORD, KEY_PASSWORD,
  KEY_ALIAS (=upload), then KEYSTORE_BASE64 (after bootstrap), plus PAYMENTS_BASE_URL,
  PAYMENTS_APP_KEY. None are in the repo. See docs/RELEASE_PLAYSTORE.md.
- **Risks/blockers:** (1) Signed APK/AAB + keystore require owner to set secrets + run
  bootstrap-keystore then release workflow. (2) Play Console submission (create app, choose to
  upload own signing key, data safety, content rating, listing, screenshots, privacy-policy URL,
  internal test) is manual owner work. (3) Physical-phone acceptance loop not done. (4) Not merged
  to main (another AI owns main); update.json raw URL resolves only once on main.
- **Next:** Owner: set secrets → run bootstrap-keystore → back up key + set KEYSTORE_BASE64 →
  merge branch to main → push tag v1.0.0 (or dispatch release.yml) → download APK/AAB → Play
  Console per docs/RELEASE_PLAYSTORE.md.
