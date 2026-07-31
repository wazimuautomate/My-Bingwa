package com.example.core.notifications

import com.example.core.model.OfferCategory
import com.example.core.sms.SmsMatch

/**
 * The result of inspecting one incoming SMS.
 *
 * Pure, Android-free so it is fully unit-testable.
 *
 * `sealed`, deliberately. A server-taught rule does NOT add a case here: new
 * message formats arrive as DATA (a new [com.example.core.sms.SmsRule] producing
 * [com.example.core.sms.SmsEventType]s inside [EventDetected]), and an event name
 * the app does not know maps to `UNKNOWN` rather than a new type. So the set of
 * signal SHAPES is genuinely closed, and sealing buys exhaustiveness: adding a
 * shape later must be handled everywhere rather than silently swallowed by an
 * `else ->`. Feature 2's "no app release for a new message" guarantee is upheld
 * by the rule engine, not by leaving this hierarchy open.
 */
sealed interface SmsSignal {
    val category: OfferCategory
    val rawBody: String

    /** A bundle-delivery confirmation was recognised for [category]. */
    data class DeliveryDetected(
        override val category: OfferCategory,
        override val rawBody: String
    ) : SmsSignal

    /** A low-balance nudge was recognised for [category]. */
    data class LowBalanceDetected(
        override val category: OfferCategory,
        override val rawBody: String
    ) : SmsSignal

    /**
     * The RICH signal from the dynamic engine: which server rule matched, every
     * event it means, and the amounts/expiry extracted from the message.
     *
     * [DeliveryDetected] and [LowBalanceDetected] are still emitted alongside this
     * for the existing reconciliation path, so a collector can adopt
     * [EventDetected] whenever it is ready without anything breaking in between.
     *
     * [category] is the best single category for the match, or
     * [OfferCategory.ALL] when the match is not tied to one category.
     */
    data class EventDetected(
        val match: SmsMatch,
        override val rawBody: String,
        override val category: OfferCategory
    ) : SmsSignal
}

/**
 * PURE, data-driven Safaricom SMS classifier. No Android imports.
 *
 * Matching is entirely a function of the supplied [TemplateSet]: first the
 * sender id must match a template, then that template's regex must be found in
 * the body. The category comes from the matched template — never from a code
 * branch. Delivery templates are checked before low-balance templates.
 *
 * SUPERSEDED by [com.example.core.sms.DynamicSmsParser], which understands
 * multi-event messages, several match types, amount/expiry extraction and rules
 * the server can add at any time without an app release. Kept (and kept passing
 * its tests) so nothing that still references it breaks; do not build new
 * behaviour on it.
 */
@Suppress("DEPRECATION")
@Deprecated(
    message = "Use com.example.core.sms.DynamicSmsParser with SmsRuleProvider (server-taught rules).",
    level = DeprecationLevel.WARNING
)
object SafaricomSmsParser {

    /**
     * Classify one message. Returns a [SmsSignal] or null when nothing matches.
     *
     * @param sender    the SMS sender id (e.g. "Safaricom", "SAF_Balance").
     * @param body      the full message text.
     * @param templates the active template set (seed or server-synced).
     */
    fun parse(sender: String, body: String, templates: TemplateSet): SmsSignal? {
        val trimmedSender = sender.trim()
        val trimmedBody = body.trim()
        if (trimmedSender.isEmpty() || trimmedBody.isEmpty()) return null

        for (template in templates.delivery) {
            if (!template.senderId.equals(trimmedSender, ignoreCase = true)) continue
            if (matches(template.pattern, trimmedBody)) {
                return SmsSignal.DeliveryDetected(template.category, body)
            }
        }
        for (template in templates.lowBalance) {
            if (!template.senderId.equals(trimmedSender, ignoreCase = true)) continue
            if (matches(template.pattern, trimmedBody)) {
                return SmsSignal.LowBalanceDetected(template.category, body)
            }
        }
        return null
    }

    /** Case-insensitive, dot-matches-newline partial match. Invalid regex → no match. */
    private fun matches(pattern: String, body: String): Boolean {
        val regex = try {
            Regex(pattern, setOf(RegexOption.IGNORE_CASE, RegexOption.DOT_MATCHES_ALL))
        } catch (e: IllegalArgumentException) {
            return false
        }
        return regex.containsMatchIn(body)
    }
}
