package com.example.core.sms

import java.text.ParseException
import java.text.SimpleDateFormat
import java.util.Locale
import java.util.TimeZone

/**
 * Facts pulled out of a matched message by GENERIC extractors.
 *
 * These are best-effort and every field is independently nullable: a rule can
 * match while nothing useful is extractable, and an extractor failing must never
 * fail the match. Nothing here is per-message hardcoding — the same extractors
 * run against every matched body, including bodies taught by the server long
 * after this build shipped.
 *
 * @param dataMb      data allowance normalised to MEGABYTES (GB is multiplied by 1024).
 * @param minutes     talk minutes.
 * @param smsCount    number of SMS.
 * @param priceKsh    price in whole shillings ("Sh20=", "Sh 20", "Ksh 20").
 * @param expiryMillis expiry as epoch millis interpreted in Africa/Nairobi, or
 *                    null when the date text could not be parsed.
 * @param expiryText  the raw expiry text exactly as it appeared — kept even when
 *                    [expiryMillis] is null so a new carrier format is still visible.
 * @param bundleType  "hourly" | "daily" | "weekly" | "monthly" | "" (unknown).
 */
data class SmsExtraction(
    val dataMb: Double? = null,
    val minutes: Int? = null,
    val smsCount: Int? = null,
    val priceKsh: Int? = null,
    val expiryMillis: Long? = null,
    val expiryText: String = "",
    val bundleType: String = ""
)

/**
 * The outcome of classifying one SMS: WHICH rule matched, what it means, and the
 * numbers we could read out of it.
 */
data class SmsMatch(
    val ruleId: String,
    val ruleName: String,
    val events: List<SmsEventType>,
    val extraction: SmsExtraction
)

/**
 * PURE, data-driven SMS classifier. **No Android imports** — fully unit-testable
 * on the JVM, and (CLAUDE.md §10) it runs entirely on-device: no body, sender or
 * extracted number is logged, persisted or transmitted by this file.
 *
 * Behaviour contract:
 * - Only [SmsRule.enabled] rules are considered.
 * - Rules are evaluated in ascending [SmsRule.priority]; equal priorities keep
 *   declaration order (Kotlin's sort is stable). FIRST MATCH WINS.
 * - No match returns null: the message is ignored SILENTLY. An SMS the app does
 *   not understand must never crash it and must never produce a notification.
 * - A malformed rule (bad regex, unknown match type, no recognisable event) is
 *   SKIPPED, never thrown — one bad server rule cannot break detection for the
 *   rules around it.
 *
 * minSdk 24 note: `java.time` is unavailable (no desugaring), so date handling
 * uses [SimpleDateFormat] / [TimeZone] only.
 */
object DynamicSmsParser {

    /** All Kenyan carrier timestamps are local time; Nairobi is UTC+3 year-round. */
    private const val NAIROBI = "Africa/Nairobi"

    private val REGEX_OPTIONS = setOf(RegexOption.IGNORE_CASE, RegexOption.DOT_MATCHES_ALL)

    /**
     * Classify one message against [ruleSet].
     *
     * @param sender  the SMS sender id, e.g. "Safaricom". Compared trimmed and
     *                case-insensitively. A rule with a BLANK senderId matches any
     *                sender (server catch-all).
     * @param body    the full message text (multi-part messages already joined).
     * @return the winning [SmsMatch], or null when nothing matched.
     */
    fun parse(sender: String, body: String, ruleSet: SmsRuleSet): SmsMatch? {
        val trimmedSender = sender.trim()
        val trimmedBody = body.trim()
        if (trimmedBody.isEmpty()) return null

        val ordered = ruleSet.rules
            .filter { it.enabled }
            .sortedBy { it.priority }

        for (rule in ordered) {
            if (!senderMatches(rule.senderId, trimmedSender)) continue
            if (!bodyMatches(rule, trimmedBody)) continue

            // Unknown event names (a newer server, an older app) are dropped
            // rather than crashing. A rule that ends up meaning NOTHING is not a
            // match at all, so evaluation continues with the next rule.
            val events = rule.eventTypes.mapNotNull { SmsEventType.fromNameOrNull(it) }
            if (events.isEmpty()) continue

            return SmsMatch(
                ruleId = rule.id,
                ruleName = rule.name,
                events = events,
                extraction = extract(trimmedBody)
            )
        }
        return null
    }

    /** Blank rule sender = catch-all. Otherwise trimmed, case-insensitive equality. */
    private fun senderMatches(ruleSender: String, sender: String): Boolean {
        val expected = ruleSender.trim()
        if (expected.isEmpty()) return true
        return expected.equals(sender, ignoreCase = true)
    }

    /** Dispatch on [SmsRule.matchType]. An unknown type never matches. */
    private fun bodyMatches(rule: SmsRule, body: String): Boolean {
        val pattern = rule.pattern
        if (pattern.isBlank()) return false
        return when (rule.matchType.trim().uppercase(Locale.US)) {
            SmsMatchType.REGEX -> regexMatches(pattern, body)
            SmsMatchType.KEYWORDS -> keywordsMatch(pattern, body)
            SmsMatchType.TEMPLATE -> templateMatches(pattern, body)
            else -> false
        }
    }

    /** Case-insensitive, dot-matches-newline PARTIAL match. Invalid regex → skip the rule. */
    private fun regexMatches(pattern: String, body: String): Boolean {
        val regex = compileOrNull(pattern) ?: return false
        return regex.containsMatchIn(body)
    }

    /** ALL comma- or pipe-separated keywords must appear (case-insensitive). */
    private fun keywordsMatch(pattern: String, body: String): Boolean {
        val keywords = pattern.split(',', '|')
            .map { it.trim() }
            .filter { it.isNotEmpty() }
        if (keywords.isEmpty()) return false
        return keywords.all { body.contains(it, ignoreCase = true) }
    }

    /**
     * Literal text with `*` wildcards, e.g. `You have received * from Bingwa *`.
     * Literal segments are [Regex.escape]d so carrier punctuation (`.`, `*`, `#`,
     * `+`) is never mistaken for regex syntax.
     */
    private fun templateMatches(pattern: String, body: String): Boolean {
        val builder = StringBuilder()
        val segments = pattern.split('*')
        for ((index, segment) in segments.withIndex()) {
            if (index > 0) builder.append(".*")
            if (segment.isNotEmpty()) builder.append(Regex.escape(segment))
        }
        val regex = compileOrNull(builder.toString()) ?: return false
        return regex.containsMatchIn(body)
    }

    /** Compile a regex, or null when the server (or a typo) supplied an invalid one. */
    private fun compileOrNull(pattern: String): Regex? = try {
        Regex(pattern, REGEX_OPTIONS)
    } catch (e: Exception) {
        // PatternSyntaxException (an IllegalArgumentException) and anything else:
        // one bad rule must never take the whole engine down.
        null
    }

    // -----------------------------------------------------------------------
    // Generic extractors. Each one is individually null-safe and independent.
    // -----------------------------------------------------------------------

    /** "250MB", "1024.0 MB", "1.5GB" (→ 1536.0), "75MBs", "below 2MBs". */
    private val DATA_REGEX = Regex(
        """(\d+(?:\.\d+)?)\s*(MB|GB|KB)s?\b""",
        RegexOption.IGNORE_CASE
    )

    /** "43 Mins", "50 Minutes", "below 10 Minutes". */
    private val MINUTES_STRICT_REGEX = Regex(
        """(\d+)\s*(?:Mins?|Minutes?)\b""",
        RegexOption.IGNORE_CASE
    )

    /** "45 Talkmore Minutes" — one branded word is allowed between number and unit. */
    private val MINUTES_LOOSE_REGEX = Regex(
        """(\d+)\s*(?:[A-Za-z\-]+\s+)?(?:Mins?|Minutes?)\b""",
        RegexOption.IGNORE_CASE
    )

    /** "20 SMS", "50 SMS". */
    private val SMS_STRICT_REGEX = Regex("""(\d+)\s*SMS\b""", RegexOption.IGNORE_CASE)

    /** "20 Daily SMS" — one branded word allowed, same idea as minutes. */
    private val SMS_LOOSE_REGEX = Regex(
        """(\d+)\s*(?:[A-Za-z\-]+\s+)?SMS\b""",
        RegexOption.IGNORE_CASE
    )

    /**
     * "Sh20=", "Sh 20", "Ksh 20", "KES 20". The lookbehind stops the "sh" inside
     * an ordinary word (e.g. "cash 20") from being read as a price.
     */
    private val PRICE_REGEX = Regex(
        """(?<![A-Za-z])(?:Ksh|KES|Sh)\s*\.?\s*(\d+)""",
        RegexOption.IGNORE_CASE
    )

    /** "22/05/2026 07:39AM" and "29-07-2026 09:57PM" (and the spaced variants). */
    private val EXPIRY_REGEX = Regex(
        """\d{1,2}[/-]\d{1,2}[/-]\d{4}\s+\d{1,2}:\d{2}\s*(?:AM|PM)""",
        RegexOption.IGNORE_CASE
    )

    /** Trailing "24hr", "3hrs" when no explicit period word is present. */
    private val HOURS_REGEX = Regex("""(\d+)\s*hrs?\b""", RegexOption.IGNORE_CASE)

    private val HOURLY_WORD = Regex("""\bhourly\b""", RegexOption.IGNORE_CASE)
    private val DAILY_WORD = Regex("""\bdaily\b""", RegexOption.IGNORE_CASE)
    private val WEEKLY_WORD = Regex("""\bweekly\b""", RegexOption.IGNORE_CASE)
    private val MONTHLY_WORD = Regex("""\bmonthly\b""", RegexOption.IGNORE_CASE)

    /**
     * Date patterns tried in order against the NORMALISED expiry text (whitespace
     * collapsed, the space before AM/PM removed, uppercased).
     */
    private val EXPIRY_PATTERNS = listOf(
        "dd/MM/yyyy hh:mma",
        "d/M/yyyy hh:mma",
        "dd-MM-yyyy hh:mma",
        "d-M-yyyy hh:mma"
    )

    /** Run every extractor over [body]. Any single failure yields null for that field only. */
    fun extract(body: String): SmsExtraction {
        val expiryText = EXPIRY_REGEX.find(body)?.value ?: ""
        return SmsExtraction(
            dataMb = extractDataMb(body),
            minutes = extractMinutes(body),
            smsCount = extractSmsCount(body),
            priceKsh = extractPriceKsh(body),
            expiryMillis = if (expiryText.isEmpty()) null else parseExpiryMillis(expiryText),
            expiryText = expiryText,
            bundleType = extractBundleType(body)
        )
    }

    /** Normalises KB/MB/GB to megabytes. Returns null when no size is present. */
    private fun extractDataMb(body: String): Double? {
        val match = DATA_REGEX.find(body) ?: return null
        val amount = match.groupValues.getOrNull(1)?.toDoubleOrNull() ?: return null
        return when (match.groupValues.getOrNull(2)?.uppercase(Locale.US)) {
            "GB" -> amount * 1024.0
            "KB" -> amount / 1024.0
            "MB" -> amount
            else -> null
        }
    }

    private fun extractMinutes(body: String): Int? {
        val strict = MINUTES_STRICT_REGEX.find(body)?.groupValues?.getOrNull(1)?.toIntOrNull()
        if (strict != null) return strict
        return MINUTES_LOOSE_REGEX.find(body)?.groupValues?.getOrNull(1)?.toIntOrNull()
    }

    private fun extractSmsCount(body: String): Int? {
        val strict = SMS_STRICT_REGEX.find(body)?.groupValues?.getOrNull(1)?.toIntOrNull()
        if (strict != null) return strict
        return SMS_LOOSE_REGEX.find(body)?.groupValues?.getOrNull(1)?.toIntOrNull()
    }

    private fun extractPriceKsh(body: String): Int? =
        PRICE_REGEX.find(body)?.groupValues?.getOrNull(1)?.toIntOrNull()

    /**
     * Explicit period words win; otherwise a trailing hour count decides —
     * "24hr" is a one-day bundle, anything shorter is hourly.
     */
    private fun extractBundleType(body: String): String {
        if (HOURLY_WORD.containsMatchIn(body)) return "hourly"
        if (DAILY_WORD.containsMatchIn(body)) return "daily"
        if (WEEKLY_WORD.containsMatchIn(body)) return "weekly"
        if (MONTHLY_WORD.containsMatchIn(body)) return "monthly"
        val hours = HOURS_REGEX.find(body)?.groupValues?.getOrNull(1)?.toIntOrNull() ?: return ""
        return if (hours >= 24) "daily" else "hourly"
    }

    /**
     * Parse an expiry stamp to epoch millis in Africa/Nairobi.
     * Unparseable text returns null — the caller keeps the raw text regardless.
     */
    fun parseExpiryMillis(rawExpiry: String): Long? {
        val normalised = rawExpiry
            .trim()
            .replace(Regex("""\s+"""), " ")
            .replace(Regex("""\s+([AP]M)""", RegexOption.IGNORE_CASE), "$1")
            .uppercase(Locale.US)
        if (normalised.isEmpty()) return null

        for (pattern in EXPIRY_PATTERNS) {
            val millis = try {
                val format = SimpleDateFormat(pattern, Locale.US)
                format.timeZone = TimeZone.getTimeZone(NAIROBI)
                format.isLenient = false
                format.parse(normalised)?.time
            } catch (e: ParseException) {
                null
            } catch (e: IllegalArgumentException) {
                null
            }
            if (millis != null) return millis
        }
        return null
    }
}
