package com.example.core.update

import com.example.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.util.concurrent.TimeUnit

/**
 * In-app update check for the DIRECT (GitHub / sideload) distribution channel.
 *
 * Google Play distribution updates itself natively, so this check only matters
 * for users who installed the direct APK. It fetches a small, non-secret JSON
 * manifest ([BuildConfig.UPDATE_MANIFEST_URL], the repo's `update.json`) and
 * compares its `latestVersionCode` against this build's [BuildConfig.VERSION_CODE].
 *
 * It never downloads or installs anything itself — on an available update the UI
 * simply opens the published APK URL in the browser, so the user consciously
 * installs a build signed with the same permanent identity as their current one.
 */
sealed interface UpdateResult {
    /** A newer direct-channel build is published. */
    data class Available(
        val versionName: String,
        val apkUrl: String,
        val notes: String,
        val mandatory: Boolean,
    ) : UpdateResult

    /** This build is the latest published direct-channel build. */
    data object UpToDate : UpdateResult

    /** The check could not complete (offline, bad response, malformed manifest). */
    data class Error(val message: String) : UpdateResult
}

object UpdateChecker {

    private val client: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .callTimeout(15, TimeUnit.SECONDS)
            .build()
    }

    suspend fun check(
        currentVersionCode: Int = BuildConfig.VERSION_CODE,
        manifestUrl: String = BuildConfig.UPDATE_MANIFEST_URL,
    ): UpdateResult = withContext(Dispatchers.IO) {
        try {
            val request = Request.Builder()
                .url(manifestUrl)
                .header("Cache-Control", "no-cache")
                .build()
            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) {
                    return@withContext UpdateResult.Error(
                        "Could not check for updates right now. Please try again later.",
                    )
                }
                val body = response.body?.string().orEmpty()
                val json = JSONObject(body)
                val latestCode = json.optInt("latestVersionCode", 0)
                if (latestCode > currentVersionCode) {
                    UpdateResult.Available(
                        versionName = json.optString("latestVersionName", ""),
                        apkUrl = json.optString("apkUrl", ""),
                        notes = json.optString("releaseNotes", ""),
                        mandatory = json.optBoolean("mandatory", false),
                    )
                } else {
                    UpdateResult.UpToDate
                }
            }
        } catch (e: Exception) {
            UpdateResult.Error(
                "Could not check for updates. Check your connection and try again.",
            )
        }
    }
}
