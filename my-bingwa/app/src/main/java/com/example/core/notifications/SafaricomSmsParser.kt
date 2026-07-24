package com.example.core.notifications

import com.example.core.model.OfferCategory

/**
 * The result of inspecting one Safaricom SMS against a [TemplateSet].
 *
 * Pure, Android-free so it is fully unit-testable.
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
}

/**
 * PURE, data-driven Safaricom SMS classifier. No Android imports.
 *
 * Matching is entirely a function of the supplied [TemplateSet]: first the
 * sender id must match a template, then that template's regex must be found in
 * the body. The category comes from the matched template — never from a code
 * branch. Delivery templates are checked before low-balance templates.
 */
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
