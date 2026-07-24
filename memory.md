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
