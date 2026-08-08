package com.example.core.sms

/**
 * The SEED [SmsRuleSet] shipped inside the APK.
 *
 * Purpose: a brand-new install (or a phone that has never reached the server)
 * still recognises every Safaricom / Bingwa Sokoni message we have actually
 * observed, completely offline. The server ships the SAME rules as version 1 and
 * then supersedes them — see `SmsRuleProvider`, which prefers a stored set only
 * when its [SmsRuleSet.version] is HIGHER than [SEED_VERSION].
 *
 * IMPORTANT: sender ids ("Safaricom", "SAF_Balance", "SAF_OfaMOTO") are DATA in
 * this list, never a code branch. When Safaricom invents a new short code the
 * server adds a rule and every installed app picks it up — no release.
 *
 * PRIORITY DESIGN (lower = evaluated first, first match wins):
 * ```
 *  10  NO_DATA          highest consequence: the customer has nothing at all
 *  20  VERY_LOW_DATA    more specific than LOW_DATA ("... is below 2MBs")
 *  24  LOW_SMS
 *  26  LOW_MINUTES
 *  30  LOW_DATA         the general "balance is <n>MBs" nudge
 *  40  GIFT_RECEIVED
 *  45  combo delivery   "50 Minutes + 50 SMS" must beat the single-event rules
 *  50  DATA_RECEIVED    Bingwa Sokoni wording (most specific data delivery)
 *  55  DATA_RECEIVED    "Thank you for purchasing ... MB"
 *  58  DATA_RECEIVED    generic "received ... MB/GB" catch-all
 *  60  SMS_RECEIVED
 *  62  MINUTES_RECEIVED
 * ```
 * Patterns are triple-quoted raw strings so regex backslashes need no escaping,
 * and are matched case-insensitively with DOT_MATCHES_ALL (see [DynamicSmsParser]).
 */
object DefaultSmsRules {

    /** Version of the in-APK seed. A server set must exceed this to be preferred. */
    const val SEED_VERSION: Int = 1

    private const val SAFARICOM = "Safaricom"
    private const val SAF_BALANCE = "SAF_Balance"
    private const val SAF_OFAMOTO = "SAF_OfaMOTO"

    val SEED: SmsRuleSet = SmsRuleSet(
        version = SEED_VERSION,
        updatedAt = 0L,
        rules = listOf(
            // ---------------------------------------------------------------
            // Balance warnings (SAF_Balance). Highest consequence first.
            // ---------------------------------------------------------------

            // "Dear customer, you do not have an active data bundle."
            SmsRule(
                id = "no_active_data_bundle",
                name = "No active data bundle",
                senderId = SAF_BALANCE,
                pattern = """do(?:\s+not|n't)\s+have\s+an\s+active\s+data\s+bundle""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.NO_DATA.name),
                priority = 10,
                description = "Customer has no data bundle at all — the strongest signal, " +
                    "so it is evaluated before every low-balance rule."
            ),

            // "Dear customer, your deal of the day data balance is below 2MBs."
            // Deliberately requires the literal word "below" so it cannot be
            // confused with the plain "balance is 75MBs" wording below.
            SmsRule(
                id = "data_balance_very_low",
                name = "Data balance below threshold",
                senderId = SAF_BALANCE,
                pattern = """data\s+balance\s+is\s+below""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.VERY_LOW_DATA.name),
                priority = 20,
                description = "\"...data balance is below 2MBs\" — almost exhausted. Must win " +
                    "over the general LOW_DATA rule, hence the lower priority number."
            ),

            // Modelled on the SAF_Balance data wording, e.g.
            // "Dear customer, your deal of the day SMS balance is below 5 SMS."
            SmsRule(
                id = "sms_balance_low",
                name = "SMS balance low",
                senderId = SAF_BALANCE,
                pattern = """(?:sms\s+balance\s+is\s+below|sms\s+are\s+below|sms\s+balance\s+is\s+\d)""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.LOW_SMS.name),
                priority = 24,
                description = "SMS bundle running out. Modelled on the SAF_Balance data wording; " +
                    "the server can refine it the moment a real sample is captured."
            ),

            // "Your Easy-Talk Minutes are below 10 Minutes."
            SmsRule(
                id = "minutes_balance_low",
                name = "Minutes balance low",
                senderId = SAF_BALANCE,
                pattern = """(?:minutes?\s+are\s+below|mins?\s+are\s+below|minutes?\s+balance\s+is\s+below)""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.LOW_MINUTES.name),
                priority = 26,
                description = "Talk minutes running out (\"...Minutes are below 10 Minutes\")."
            ),

            // "Dear customer, your deal of the day data balance is 75MBs."
            // Requires a DIGIT straight after "is" so the "below" wording above
            // can never be swallowed by this more general rule.
            SmsRule(
                id = "data_balance_low",
                name = "Data balance low",
                senderId = SAF_BALANCE,
                pattern = """data\s+balance\s+is\s+\d""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.LOW_DATA.name),
                priority = 30,
                description = "\"...data balance is 75MBs\" — running low but still usable."
            ),

            // ---------------------------------------------------------------
            // Deliveries.
            // ---------------------------------------------------------------

            // "You have received a gift of Sh20=43 Mins,3hrs from 111327201 !"
            SmsRule(
                id = "gift_received_ofamoto",
                name = "Gift received",
                senderId = SAF_OFAMOTO,
                pattern = """received\s+a\s+gift\s+of""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.GIFT_RECEIVED.name),
                priority = 40,
                description = "Ofa Moto gift (data or minutes). The actual category is resolved " +
                    "from the extracted amounts, not from the sender."
            ),

            // "You have received Easy-Talk 50 Minutes + 50 SMS."
            // One message, TWO events — and it must beat the single-event
            // SMS/minutes rules, otherwise only half the message is understood.
            SmsRule(
                id = "minutes_and_sms_combo_received",
                name = "Minutes + SMS combo received",
                senderId = SAFARICOM,
                pattern = """received\s+.*?\d+\s*minutes?\s*\+\s*\d+\s*sms""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(
                    SmsEventType.MINUTES_RECEIVED.name,
                    SmsEventType.SMS_RECEIVED.name
                ),
                priority = 45,
                description = "Easy-Talk style combo bundle: minutes AND SMS in one message."
            ),

            // "You have received Sh20=250MB 24hr from Bingwa Sokoni. Dial 4449# ..."
            SmsRule(
                id = "data_received_bingwa_sokoni",
                name = "Bingwa Sokoni data received",
                senderId = SAFARICOM,
                pattern = """received\b.*?\d+(?:\.\d+)?\s*(?:MB|GB)\b.*?from\s+Bingwa\s+Sokoni""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
                priority = 50,
                description = "The exact wording of a Bingwa Sokoni data delivery — the seller's " +
                    "own channel, so it is checked before the generic data rules."
            ),

            // "Thank You For Purchasing 1024.0 MB hourly data bundle. Expiry date
            //  29-07-2026 09:57PM. ..."
            SmsRule(
                id = "data_received_thank_you_purchase",
                name = "Data purchase confirmation",
                senderId = SAFARICOM,
                pattern = """thank\s*you\s+for\s+purchasing\b.*?\d+(?:\.\d+)?\s*(?:MB|GB)\b""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
                priority = 55,
                description = "\"Thank You For Purchasing <n> MB <period> data bundle\" wording."
            ),

            // Catch-all so a re-worded data delivery is still understood.
            SmsRule(
                id = "data_received_generic",
                name = "Data received (generic)",
                senderId = SAFARICOM,
                pattern = """received\b.*?\d+(?:\.\d+)?\s*(?:MB|GB)\b""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
                priority = 58,
                description = "Any \"received ... <n>MB/GB\" delivery whose exact wording we have " +
                    "not seen yet. Deliberately last of the data rules."
            ),

            // "You have received 20 SMS Daily SMS Bundle. Expiry date: 22/05/2026 07:39AM."
            SmsRule(
                id = "sms_received",
                name = "SMS bundle received",
                senderId = SAFARICOM,
                pattern = """received\b.*?\d+\s*SMS\b""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.SMS_RECEIVED.name),
                priority = 60,
                description = "\"You have received <n> SMS ...\" bundle delivery."
            ),

            // "You have received 45 Talkmore Minutes. Expiry date ..."
            // The optional word between the number and "Minutes" absorbs branded
            // bundle names (Talkmore, Easy-Talk, ...) without a new rule each time.
            SmsRule(
                id = "minutes_received",
                name = "Minutes bundle received",
                senderId = SAFARICOM,
                pattern = """received\b.*?\d+\s*(?:[A-Za-z\-]+\s+)?(?:Mins?|Minutes?)\b""",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.MINUTES_RECEIVED.name),
                priority = 62,
                description = "\"You have received <n> <brand> Minutes ...\" bundle delivery."
            )
        )
    )
}
