package com.example.core.sms

/**
 * DYNAMIC, SERVER-TAUGHT SMS DETECTION — data model.
 *
 * Safaricom keeps changing sender ids, wording, formatting and bundle names. If
 * those shapes live in Kotlin `when` branches, every carrier tweak costs an app
 * release. So the shapes live HERE, in data: a versioned [SmsRuleSet] that the
 * server can replace at any time. The APK only ships a SEED set
 * ([DefaultSmsRules]) so a brand-new install still recognises today's messages
 * offline.
 *
 * Privacy (CLAUDE.md §10): rules travel from server to app; message bodies and
 * originating numbers NEVER travel anywhere. All matching happens on-device.
 *
 * Serialisation is Moshi **reflection** (no codegen in this module), therefore:
 * - every persisted field has a default value, so an older cached document still
 *   deserialises after the model gains a field;
 * - enums are carried as [String] names, never as Kotlin enums, so an unknown
 *   value invented by a newer server can never crash deserialisation — it is
 *   simply dropped at match time.
 */

/**
 * What a matched message MEANS to the app.
 *
 * `*_RECEIVED` = the carrier says something arrived (a delivery signal).
 * `LOW_*` / `NO_DATA` = the carrier says a balance is running out.
 * [UNKNOWN] is the safe landing spot for a server value this build does not know.
 */
enum class SmsEventType {
    DATA_RECEIVED,
    SMS_RECEIVED,
    MINUTES_RECEIVED,
    GIFT_RECEIVED,
    LOW_DATA,
    VERY_LOW_DATA,
    NO_DATA,
    LOW_SMS,
    LOW_MINUTES,
    UNKNOWN;

    companion object {
        /**
         * Parse a server-supplied name. Unknown/blank values return null so the
         * caller can DROP that single event instead of crashing the parse.
         */
        fun fromNameOrNull(raw: String): SmsEventType? {
            val key = raw.trim()
            if (key.isEmpty()) return null
            for (value in SmsEventType.values()) {
                if (value.name.equals(key, ignoreCase = true)) return value
            }
            return null
        }
    }
}

/** The three ways a rule's [SmsRule.pattern] can be interpreted. */
object SmsMatchType {
    /** [SmsRule.pattern] is a regex: case-insensitive, DOT_MATCHES_ALL, partial match. */
    const val REGEX: String = "REGEX"

    /** [SmsRule.pattern] is a comma- or pipe-separated list; ALL must appear. */
    const val KEYWORDS: String = "KEYWORDS"

    /** [SmsRule.pattern] is literal text with `*` wildcards. */
    const val TEMPLATE: String = "TEMPLATE"
}

/**
 * One server-teachable detection rule.
 *
 * Mirrors the admin form exactly: Rule Name, Sender ID, Message Pattern, Event
 * Type, Priority, Enabled, Description.
 *
 * @param id          stable id (de-dup key across syncs, and what tests assert on).
 * @param name        human rule name shown in the admin UI.
 * @param senderId    SMS sender id to match, trimmed and case-insensitive.
 *                    **A BLANK senderId matches ANY sender** — that is how the
 *                    server ships a catch-all when a new short code appears.
 * @param pattern     interpreted according to [matchType].
 * @param matchType   one of [SmsMatchType]. An unknown value never matches.
 * @param eventTypes  [SmsEventType] names. One message may carry several events
 *                    (e.g. "50 Minutes + 50 SMS"). Unknown names are dropped.
 * @param priority    ascending evaluation order; LOWER runs first. Most specific
 *                    and highest-consequence rules take the lowest numbers.
 * @param enabled     disabled rules are skipped entirely (server kill-switch).
 * @param description why the rule exists / which real message it came from.
 */
data class SmsRule(
    val id: String = "",
    val name: String = "",
    val senderId: String = "",
    val pattern: String = "",
    val matchType: String = SmsMatchType.REGEX,
    val eventTypes: List<String> = emptyList(),
    val priority: Int = 100,
    val enabled: Boolean = true,
    val description: String = ""
)

/**
 * A versioned bundle of rules.
 *
 * [version] is how the app decides whether a cached/server set supersedes the
 * in-APK seed (see `SmsRuleProvider`). [updatedAt] is informational only
 * (epoch millis, server clock) and is never used for matching.
 */
data class SmsRuleSet(
    val version: Int = 0,
    val updatedAt: Long = 0L,
    val rules: List<SmsRule> = emptyList()
)
