# Changelog

All notable changes to the My Bingwa customer app are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Sections used: `Added`, `Changed`, `Fixed`, `Removed`, `Security`, `Internal`
(`Internal` = repository change with no customer-visible effect).

## [Unreleased]

### Added

- Checked-in Gradle wrapper (Gradle 9.1.0) under `my-bingwa/gradle/wrapper/`
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

- Repository layout: planning documents (`Plan.md`, `design.md`,
  `CLAUDE_KICKOFF_AND_BUILD_PHASES.md`) moved into `docs/`. Operating brain
  (`CLAUDE.md`), `memory.md` and `CHANGELOG.md` remain at the repository root.
- Brand asset kit folder renamed from `assests/` to `assets/` (typo fix).

### Fixed

- Debug build no longer references a non-existent, git-ignored
  `debug.keystore`; it now uses AGP's auto-generated debug signing config, which
  unblocks `assembleDebug` in a clean CI environment.

### Removed

- Empty stray `firebase-debug.log` from the repository root.
- Broken custom `debugConfig` signing config from `app/build.gradle.kts`.

### Security

- Confirmed no keystores, `.env` files, `google-services.json`,
  service-account files or other secrets are present in the imported project.
- `.gitignore` hardened so signing material and secrets cannot be committed.

### Internal

- Phase 0 baseline audit of the Google AI Studio-generated UI recorded in
  `memory.md` and `docs/REPO_INVENTORY.md`.
