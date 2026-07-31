plugins {
  alias(libs.plugins.android.application)
  alias(libs.plugins.kotlin.compose)
  alias(libs.plugins.roborazzi)
}

android {
  namespace = "com.example"
  compileSdk { version = release(36) { minorApiLevel = 1 } }

  defaultConfig {
    // Permanent production application ID (Play + direct). This is the app's
    // permanent identity on Google Play and for sideloaded updates — never change
    // it after the first public release. The internal `namespace` above stays
    // `com.example` on purpose: it only names the generated R/BuildConfig classes
    // and the ~200 existing source files, is invisible to users and Play, and
    // renaming it would be a large, risk-only refactor with no product benefit.
    applicationId = "com.bingwasokoni"
    minSdk = 24
    targetSdk = 36
    // Semantic versionName + monotonically increasing versionCode. Both the direct
    // APK and the Play AAB ship the SAME version so a user can move between the
    // GitHub and Play channels and updates supersede correctly (same signing
    // identity — see signingConfigs + docs/RELEASE_PLAYSTORE.md). Bump BOTH for
    // every release; versionCode must only ever increase.
    versionCode = 3
    versionName = "1.0.2"

    testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

    // Launcher label. Overridden to "My Bingwa Dev" for debug builds so a debug
    // install is visually distinct from and installable alongside the release app.
    manifestPlaceholders["appLabel"] = "My Bingwa"

    // Where the direct-APK (GitHub) channel advertises its latest version. Play
    // distribution updates itself natively; this drives ONLY the sideload/GitHub
    // in-app update check. Non-secret. See update.json at the repo root and
    // core/update/UpdateChecker.kt. Overridable via the `updateManifestUrl` Gradle
    // property or the UPDATE_MANIFEST_URL env var.
    val updateManifestUrl = (project.findProperty("updateManifestUrl") as String?)?.takeIf { it.isNotBlank() }
      ?: System.getenv("UPDATE_MANIFEST_URL")?.takeIf { it.isNotBlank() }
      ?: "https://raw.githubusercontent.com/wazimuautomate/My-Bingwa/main/update.json"
    buildConfigField("String", "UPDATE_MANIFEST_URL", "\"$updateManifestUrl\"")

    // Base URL of the My Bingwa payment backend proxy (owns Daraja + the STK
    // callback). This is a NON-SECRET configuration value, never a credential —
    // Daraja consumer key/secret and the passkey live only on that backend
    // (CLAUDE.md §2/§10). Overridable via the `paymentsBaseUrl` Gradle property or
    // the PAYMENTS_BASE_URL env var; defaults to the production API host so a signed
    // build talks to the real backend. Real STK still also requires PAYMENTS_APP_KEY
    // (see below); without it a release build fails payments honestly rather than
    // faking a success, and a debug build falls back to a labelled local simulation.
    val paymentsBaseUrl = (project.findProperty("paymentsBaseUrl") as String?)?.takeIf { it.isNotBlank() }
      ?: System.getenv("PAYMENTS_BASE_URL")?.takeIf { it.isNotBlank() }
      ?: "https://mybingwa.blazetechscope.com/"
    buildConfigField("String", "PAYMENTS_BASE_URL", "\"$paymentsBaseUrl\"")

    // Shared app-key sent as the X-App-Key header so only our app can call the
    // payment API. NOT a Daraja credential (those stay on the server). Injected from
    // the `paymentsAppKey` Gradle property or the PAYMENTS_APP_KEY env var.
    val paymentsAppKey = (project.findProperty("paymentsAppKey") as String?)
      ?: System.getenv("PAYMENTS_APP_KEY")
      ?: ""
    buildConfigField("String", "PAYMENTS_APP_KEY", "\"$paymentsAppKey\"")
  }

  // Two distribution channels from one codebase and one signing identity.
  flavorDimensions += "distribution"
  productFlavors {
    // GitHub / direct-download build. Keeps the local Safaricom SMS
    // delivery-detection feature (RECEIVE_SMS), which Google Play restricts.
    create("direct") {
      dimension = "distribution"
      // The direct channel IS the GitHub channel: sideloaded users have no store
      // to update them, so the in-app GitHub updater is their only upgrade path.
      buildConfigField("boolean", "GITHUB_UPDATER_ENABLED", "true")
    }
    // Google Play build. src/play/AndroidManifest.xml removes RECEIVE_SMS and
    // SmsDeliveryReceiver so the Play submission needs no restricted-permission
    // declaration and cannot be rejected for it. Identical applicationId and
    // signing identity as `direct`, so the two channels are update-compatible.
    create("play") {
      dimension = "distribution"
      // Play distribution updates itself natively. Shipping a second, in-app
      // update channel there is redundant and violates Play policy, so the
      // GitHub updater is compiled out of this flavour. The implementation is
      // retained (not deleted) behind this flag so it can be re-enabled if
      // distribution ever changes.
      buildConfigField("boolean", "GITHUB_UPDATER_ENABLED", "false")
    }
  }

  signingConfigs {
    create("release") {
      // Release signing is supplied only in the protected CI release job via env vars.
      // No keystore is committed. Missing values are tolerated until a release variant is signed.
      val keystorePath = System.getenv("KEYSTORE_PATH") ?: "${rootDir}/my-upload-key.jks"
      storeFile = file(keystorePath)
      storePassword = System.getenv("STORE_PASSWORD")
      keyAlias = System.getenv("KEY_ALIAS") ?: "upload"
      keyPassword = System.getenv("KEY_PASSWORD")
    }
  }

  buildTypes {
    release {
      isCrunchPngs = false
      isMinifyEnabled = false
      proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
      signingConfig = signingConfigs.getByName("release")
    }
    // Debug is installable alongside release: a distinct application-id suffix and
    // the "My Bingwa Dev" launcher label. Uses AGP's auto-generated debug
    // keystore — never a committed one, and never the permanent release identity.
    debug {
      applicationIdSuffix = ".debug"
      versionNameSuffix = "-debug"
      manifestPlaceholders["appLabel"] = "My Bingwa Dev"
      // A buildType field overrides the flavour field, so debug builds always keep
      // the GitHub updater — including the `play` debug variant, which testers
      // install by sideloading and therefore still need an in-app upgrade path.
      buildConfigField("boolean", "GITHUB_UPDATER_ENABLED", "true")
    }
  }
  compileOptions {
    sourceCompatibility = JavaVersion.VERSION_11
    targetCompatibility = JavaVersion.VERSION_11
  }
  buildFeatures {
    compose = true
    buildConfig = true
  }
  testOptions { unitTests { isIncludeAndroidResources = true } }
}

// The imported Google AI Studio project shipped Gemini/Firebase and KSP codegen
// scaffolding that My Bingwa does not use (Plan.md excludes Gemini and Firebase-AI
// from the APK; no Room entities or Moshi codegen exist yet). Those plugins have
// been removed here: they added APK weight and, on CI, the KSP2 processor crashed
// during annotation processing. Room and kotlinx.serialization are reintroduced
// with real code in later phases (see docs/CLAUDE_KICKOFF_AND_BUILD_PHASES.md).
dependencies {
  implementation(platform(libs.androidx.compose.bom))
  implementation(libs.androidx.activity.compose)
  implementation(libs.androidx.compose.material.icons.core)
  implementation(libs.androidx.compose.material.icons.extended)
  implementation(libs.androidx.compose.material3)
  implementation(libs.androidx.compose.ui)
  // Fonts (Outfit + Poppins) are bundled under res/font; the downloadable
  // Google Fonts provider is intentionally not used (see ui/theme/Type.kt).
  implementation(libs.androidx.compose.ui.graphics)
  implementation(libs.androidx.compose.ui.tooling.preview)
  implementation(libs.androidx.core.ktx)
  implementation(libs.androidx.core.splashscreen)
  // Billboard media: Coil renders synced billboard images and animated GIFs from
  // its own on-disk cache, so a slide that was fetched once keeps rendering
  // OFFLINE. coil-gif adds the animated-GIF decoders (ImageDecoder on API 28+,
  // Movie below it). No network is required for a cache hit.
  implementation(libs.coil.compose)
  implementation(libs.coil.gif)
  implementation(libs.androidx.lifecycle.runtime.compose)
  implementation(libs.androidx.lifecycle.runtime.ktx)
  implementation(libs.androidx.lifecycle.viewmodel.compose)
  implementation(libs.androidx.navigation.compose)
  // Preferences DataStore backs the real on-device persistence (LocalStore): the
  // installation-local profile, favourites, Activity, notifications and any in-flight
  // order survive process death. No KSP/codegen — the snapshot is Moshi JSON.
  implementation(libs.androidx.datastore.preferences)
  // Room runtime is declared ahead of a future migration to a relational local
  // source; no entities/DAOs exist yet, so the KSP compiler is not applied.
  implementation(libs.androidx.room.ktx)
  implementation(libs.androidx.room.runtime)
  // WorkManager runs the periodic background catalogue/config sync
  // (CatalogueSyncWorker) under a CONNECTED constraint with exponential backoff.
  // Scheduled once from MyBingwaApplication; never blocks startup.
  implementation(libs.androidx.work.runtime.ktx)
  // Retrofit/OkHttp/Moshi are declared ahead of the Phase 7 network layer.
  implementation(libs.converter.moshi)
  implementation(libs.kotlinx.coroutines.android)
  implementation(libs.kotlinx.coroutines.core)
  implementation(libs.logging.interceptor)
  implementation(libs.moshi.kotlin)
  implementation(libs.okhttp)
  implementation(libs.retrofit)
  testImplementation(libs.androidx.compose.ui.test.junit4)
  testImplementation(libs.androidx.core)
  testImplementation(libs.androidx.junit)
  testImplementation(libs.junit)
  testImplementation(libs.kotlinx.coroutines.test)
  testImplementation(libs.robolectric)
  testImplementation(libs.roborazzi)
  testImplementation(libs.roborazzi.compose)
  testImplementation(libs.roborazzi.junit.rule)
  androidTestImplementation(platform(libs.androidx.compose.bom))
  androidTestImplementation(libs.androidx.compose.ui.test.junit4)
  androidTestImplementation(libs.androidx.espresso.core)
  androidTestImplementation(libs.androidx.junit)
  androidTestImplementation(libs.androidx.runner)
  debugImplementation(libs.androidx.compose.ui.test.manifest)
  debugImplementation(libs.androidx.compose.ui.tooling)
}
