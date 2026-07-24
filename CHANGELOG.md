# Changelog

All notable changes to the My Bingwa customer app are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Sections used: `Added`, `Changed`, `Fixed`, `Removed`, `Security`, `Internal`
(`Internal` = repository change with no customer-visible effect).

## [Unreleased]

### Added

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

- Debug build no longer references a non-existent, git-ignored
  `debug.keystore`; it now uses AGP's auto-generated debug signing config, which
  unblocks `assembleDebug` in a clean CI environment.
- Corrected `ExampleRobolectricTest` to expect the real app name "My Bingwa"
  (was the template default "My Application"), so the unit-test gate passes
  truthfully.

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
