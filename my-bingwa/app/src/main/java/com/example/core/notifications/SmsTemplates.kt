package com.example.core.notifications

import com.example.core.model.OfferCategory

/**
 * Server-syncable SMS templates for detecting bundle **delivery** and
 * **low-balance** events from Safaricom messages.
 *
 * The message formats live in DATA (these templates), never in code branches.
 * [SafaricomSmsParser] is a pure matcher over a [TemplateSet]; a future server
 * can ship an updated [TemplateSet] (see [RemoteTemplateSync]) without any app
 * release. Add or edit templates, not `when` branches.
 *
 * NOTE ON SERIALIZATION: kotlinx.serialization is not yet wired into this module
 * (the Gradle plugin was intentionally deferred to the network phase — see
 * app/build.gradle.kts). These are therefore plain data classes *shaped* for
 * serialization. When the plugin lands, annotate each with `@Serializable` and
 * add a `Json` decoder inside the [RemoteTemplateSync] implementation — no
 * structural change is required.
 *
 * SMS permissions are Play-restricted, so this file stays framework-light (pure
 * Kotlin, no Android imports) and all matching is isolated in the parser.
 */

/**
 * A template that recognises a **delivery** confirmation SMS.
 *
 * @param id         stable template id (also useful as a de-dup key on updates).
 * @param senderId   the SMS sender id to match, e.g. "Safaricom" (case-insensitive).
 * @param category   the offer category this delivery relates to (DATA/SMS/MINUTES/...).
 * @param pattern    a Java/Kotlin regex matched case-insensitively against the body.
 * @param description human note about what real message this was built from.
 */
@Deprecated(
    message = "Superseded by com.example.core.sms.SmsRule (server-taught, multi-event).",
    level = DeprecationLevel.WARNING
)
data class DeliveryTemplate(
    val id: String,
    val senderId: String,
    val category: OfferCategory,
    val pattern: String,
    val description: String = ""
)

/**
 * A template that recognises a **low-balance** nudge SMS for [category].
 * Same field meanings as [DeliveryTemplate].
 */
@Deprecated(
    message = "Superseded by com.example.core.sms.SmsRule (server-taught, multi-event).",
    level = DeprecationLevel.WARNING
)
data class LowBalanceTemplate(
    val id: String,
    val senderId: String,
    val category: OfferCategory,
    val pattern: String,
    val description: String = ""
)

/**
 * A versioned bundle of templates. [version] lets the app keep the newest set
 * when the server syncs a replacement.
 */
@Suppress("DEPRECATION")
@Deprecated(
    message = "Superseded by com.example.core.sms.SmsRuleSet (versioned, server-synced).",
    level = DeprecationLevel.WARNING
)
data class TemplateSet(
    val version: Int,
    val delivery: List<DeliveryTemplate>,
    val lowBalance: List<LowBalanceTemplate>
)
