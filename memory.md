# My Bingwa — Project Memory

**Purpose:** Durable project continuity and execution record  
**Time zone:** Africa/Nairobi  
**Last updated:** 2026-07-24  
**Current phase:** Phase 0 — repository baseline (imported UI made build-safe)

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

- Kotlin `2.2.10`, AGP `9.1.1`, Gradle wrapper `9.1.0` (added), Compose BOM
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
