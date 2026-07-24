plugins {
  alias(libs.plugins.android.application)
  alias(libs.plugins.kotlin.compose)
  alias(libs.plugins.roborazzi)
}

android {
  namespace = "com.example"
  compileSdk { version = release(36) { minorApiLevel = 1 } }

  defaultConfig {
    applicationId = "com.aistudio.mybingwa.k3p9zq"
    minSdk = 24
    targetSdk = 36
    versionCode = 1
    versionName = "1.0"

    testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

    // Base URL of the My Bingwa payment backend proxy (owns Daraja + the STK
    // callback). This is a NON-SECRET configuration value, never a credential —
    // Daraja consumer key/secret and the passkey live only on that backend
    // (CLAUDE.md §2/§10). Injected from the `paymentsBaseUrl` Gradle property or
    // the PAYMENTS_BASE_URL env var; empty by default, in which case the app uses a
    // clearly-labelled local simulation instead of a real STK call.
    val paymentsBaseUrl = (project.findProperty("paymentsBaseUrl") as String?)
      ?: System.getenv("PAYMENTS_BASE_URL")
      ?: ""
    buildConfigField("String", "PAYMENTS_BASE_URL", "\"$paymentsBaseUrl\"")
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
    // Debug uses AGP's auto-generated debug keystore; never commit a keystore.
    debug {}
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
  implementation(libs.androidx.lifecycle.runtime.compose)
  implementation(libs.androidx.lifecycle.runtime.ktx)
  implementation(libs.androidx.lifecycle.viewmodel.compose)
  implementation(libs.androidx.navigation.compose)
  // Room runtime is declared ahead of Phase 6 (canonical local source); no
  // entities/DAOs exist yet, so the KSP compiler is not applied.
  implementation(libs.androidx.room.ktx)
  implementation(libs.androidx.room.runtime)
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
