package com.example.data.config

/**
 * Seller details that can change over time and are therefore synced from the
 * server (Till, Paybill, support contacts). They are FETCHED when online but always
 * available OFFLINE from a cache/defaults — the server is only for syncing, the app
 * always has a usable copy (Phase 6).
 */
data class AppConfig(
    val tillNumber: String,
    val paybillNumber: String,
    val supportNumber: String,
    val supportWhatsapp: String
) {
    companion object {
        /**
         * Blank defaults — NO seller numbers are baked into the app. The real Till,
         * Paybill and support numbers are set by the owner in the admin and synced from
         * the server; until the first sync completes a fresh install simply has no
         * numbers to show (screens treat blanks as "not available yet").
         */
        val DEFAULT = AppConfig(
            tillNumber = "",
            paybillNumber = "",
            supportNumber = "",
            supportWhatsapp = ""
        )
    }
}

/**
 * Source of [AppConfig]. [cached] always returns something usable (last sync or
 * defaults) so the app never blocks offline; [fetch] pulls a fresh copy from the
 * server and persists it. Pure interface so the repository stays Android-free and
 * testable; the Android implementation ([com.example.data.config.AndroidRemoteConfigSource])
 * uses SharedPreferences + Retrofit.
 */
interface RemoteConfigSource {
    /** Last synced config, or the baked-in defaults. Never blocks, never fails. */
    fun cached(): AppConfig

    /** Fetch + persist a fresh config when online; returns null on any failure. */
    suspend fun fetch(): AppConfig?
}
