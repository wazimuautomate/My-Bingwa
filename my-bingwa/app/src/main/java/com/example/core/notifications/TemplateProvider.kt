package com.example.core.notifications

/**
 * Supplies the [TemplateSet] the parser should use right now.
 *
 * The default [LocalSeedTemplateProvider] returns the in-APK seed. A future
 * implementation can layer a server-synced set on top (keeping the higher
 * [TemplateSet.version]) via [RemoteTemplateSync] — that is the single seam
 * where the backend plugs in.
 */
@Suppress("DEPRECATION")
@Deprecated(
    message = "Superseded by com.example.core.sms.SmsRuleProvider.",
    level = DeprecationLevel.WARNING
)
interface TemplateProvider {
    fun current(): TemplateSet
}

/** Returns the seed templates shipped in the APK. Always available, offline. */
@Suppress("DEPRECATION")
@Deprecated(
    message = "Superseded by com.example.core.sms.SmsRuleProvider (seed + server-synced cache).",
    level = DeprecationLevel.WARNING
)
class LocalSeedTemplateProvider : TemplateProvider {
    override fun current(): TemplateSet = DefaultTemplates.SEED
}

/**
 * SERVER PLUG-IN POINT (stub — no network implementation here).
 *
 * A future data-layer class will implement this to fetch a newer [TemplateSet]
 * from the My Bingwa backend (the same backend that owns Daraja), cache it
 * locally, and expose it through a [TemplateProvider] that prefers the synced
 * set when its [TemplateSet.version] is higher than the seed. Returning null
 * means "no newer templates / offline" and the caller keeps using the seed.
 *
 * Kept as an interface only so SMS logic stays framework-light and this scope
 * ships no networking.
 */
@Suppress("DEPRECATION")
@Deprecated(
    message = "Superseded by com.example.core.sms.RemoteSmsRuleSource.",
    level = DeprecationLevel.WARNING
)
interface RemoteTemplateSync {
    /** Fetch the latest templates, or null when unavailable/offline. */
    suspend fun fetchLatest(): TemplateSet?
}
