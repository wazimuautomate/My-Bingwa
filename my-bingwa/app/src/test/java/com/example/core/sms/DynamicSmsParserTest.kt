package com.example.core.sms

import com.example.core.model.OfferCategory
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.Calendar
import java.util.Locale
import java.util.TimeZone

/**
 * The dynamic SMS engine, verified against the REAL observed Safaricom /
 * Bingwa Sokoni / Ofa Moto messages.
 *
 * The headline case is [serverTaughtRule_withBrandNewSenderAndWording_isRecognised]:
 * a rule that exists only on the server, for a sender and wording this build has
 * never seen, must classify correctly with NO code change. That is the whole point
 * of Feature 2.
 */
class DynamicSmsParserTest {

    private val seed = DefaultSmsRules.SEED

    // ---- The exact observed messages ------------------------------------

    private val smsBundleReceived =
        "You have received 20 SMS Daily SMS Bundle. Expiry date: 22/05/2026 07:39AM."

    private val bingwaSokoniData =
        "You have received Sh20=250MB 24hr from Bingwa Sokoni. Dial 4449# to check balance. " +
            "Dial *444# for other exciting offers."

    private val thankYouPurchase =
        "Thank You For Purchasing 1024.0 MB hourly data bundle. " +
            "Expiry date 29-07-2026 09:57PM. Drama iko real."

    private val talkmoreMinutes =
        "You have received 45 Talkmore Minutes. Expiry date 30-07-2026 10:00PM."

    private val easyTalkCombo = "You have received Easy-Talk 50 Minutes + 50 SMS."

    private val dataBalanceLow = "Dear customer, your deal of the day data balance is 75MBs."

    private val dataBalanceVeryLow =
        "Dear customer, your deal of the day data balance is below 2MBs. " +
            "Simply dial *544# to get Data Deals that suite you."

    private val noActiveDataBundle = "Dear customer, you do not have an active data bundle."

    private val minutesBalanceLow = "Your Easy-Talk Minutes are below 10 Minutes."

    private val smsBalanceLow = "Dear customer, your deal of the day SMS balance is below 5 SMS."

    private val ofaMotoGift =
        "You have received a gift of Sh20=43 Mins,3hrs from 111327201 ! " +
            "To check balance dial *144#."

    // ---- Seed classification --------------------------------------------

    @Test
    fun smsBundle_classifiesAsSmsReceived() {
        val match = DynamicSmsParser.parse("Safaricom", smsBundleReceived, seed)
        assertNotNull(match)
        assertEquals("sms_received", match!!.ruleId)
        assertEquals(listOf(SmsEventType.SMS_RECEIVED), match.events)
        assertEquals(20, match.extraction.smsCount)
    }

    @Test
    fun bingwaSokoniData_classifiesAsDataReceived() {
        val match = DynamicSmsParser.parse("Safaricom", bingwaSokoniData, seed)
        assertNotNull(match)
        assertEquals("data_received_bingwa_sokoni", match!!.ruleId)
        assertEquals(listOf(SmsEventType.DATA_RECEIVED), match.events)
        assertEquals(250.0, match.extraction.dataMb!!, 0.001)
        assertEquals(20, match.extraction.priceKsh)
    }

    @Test
    fun thankYouPurchase_classifiesAsDataReceived_withSizeExpiryAndBundleType() {
        val match = DynamicSmsParser.parse("Safaricom", thankYouPurchase, seed)
        assertNotNull(match)
        assertEquals("data_received_thank_you_purchase", match!!.ruleId)
        assertEquals(listOf(SmsEventType.DATA_RECEIVED), match.events)
        assertEquals(1024.0, match.extraction.dataMb!!, 0.001)
        assertEquals("hourly", match.extraction.bundleType)
        assertEquals("29-07-2026 09:57PM", match.extraction.expiryText)
        assertEquals(
            nairobiMillis(2026, Calendar.JULY, 29, 21, 57),
            match.extraction.expiryMillis
        )
    }

    @Test
    fun talkmoreMinutes_classifiesAsMinutesReceived() {
        val match = DynamicSmsParser.parse("Safaricom", talkmoreMinutes, seed)
        assertNotNull(match)
        assertEquals("minutes_received", match!!.ruleId)
        assertEquals(listOf(SmsEventType.MINUTES_RECEIVED), match.events)
        assertEquals(45, match.extraction.minutes)
    }

    @Test
    fun easyTalkCombo_yieldsBothMinutesAndSmsEvents() {
        val match = DynamicSmsParser.parse("Safaricom", easyTalkCombo, seed)
        assertNotNull(match)
        assertEquals("minutes_and_sms_combo_received", match!!.ruleId)
        assertTrue(match.events.contains(SmsEventType.MINUTES_RECEIVED))
        assertTrue(match.events.contains(SmsEventType.SMS_RECEIVED))
        assertEquals(2, match.events.size)
        assertEquals(50, match.extraction.minutes)
        assertEquals(50, match.extraction.smsCount)
    }

    @Test
    fun dataBalance75Mbs_isLowData() {
        val match = DynamicSmsParser.parse("SAF_Balance", dataBalanceLow, seed)
        assertNotNull(match)
        assertEquals("data_balance_low", match!!.ruleId)
        assertEquals(listOf(SmsEventType.LOW_DATA), match.events)
        assertEquals(75.0, match.extraction.dataMb!!, 0.001)
    }

    @Test
    fun dataBalanceBelow2Mbs_isVeryLowData_notLowData() {
        val match = DynamicSmsParser.parse("SAF_Balance", dataBalanceVeryLow, seed)
        assertNotNull(match)
        assertEquals("data_balance_very_low", match!!.ruleId)
        assertEquals(listOf(SmsEventType.VERY_LOW_DATA), match.events)
        assertFalse(match.events.contains(SmsEventType.LOW_DATA))
    }

    @Test
    fun noActiveDataBundle_winsOnPriority() {
        val match = DynamicSmsParser.parse("SAF_Balance", noActiveDataBundle, seed)
        assertNotNull(match)
        assertEquals("no_active_data_bundle", match!!.ruleId)
        assertEquals(listOf(SmsEventType.NO_DATA), match.events)
    }

    @Test
    fun minutesBelowThreshold_isLowMinutes() {
        val match = DynamicSmsParser.parse("SAF_Balance", minutesBalanceLow, seed)
        assertNotNull(match)
        assertEquals("minutes_balance_low", match!!.ruleId)
        assertEquals(listOf(SmsEventType.LOW_MINUTES), match.events)
        assertEquals(10, match.extraction.minutes)
    }

    @Test
    fun smsBalanceBelowThreshold_isLowSms() {
        val match = DynamicSmsParser.parse("SAF_Balance", smsBalanceLow, seed)
        assertNotNull(match)
        assertEquals("sms_balance_low", match!!.ruleId)
        assertEquals(listOf(SmsEventType.LOW_SMS), match.events)
        assertEquals(5, match.extraction.smsCount)
    }

    @Test
    fun ofaMotoGift_isGiftReceived_andResolvesToMinutes() {
        val match = DynamicSmsParser.parse("SAF_OfaMOTO", ofaMotoGift, seed)
        assertNotNull(match)
        assertEquals("gift_received_ofamoto", match!!.ruleId)
        assertEquals(listOf(SmsEventType.GIFT_RECEIVED), match.events)
        assertEquals(43, match.extraction.minutes)
        assertEquals(20, match.extraction.priceKsh)
        assertEquals(
            OfferCategory.MINUTES,
            SmsEventMapping.categoryFor(SmsEventType.GIFT_RECEIVED, match.extraction)
        )
    }

    @Test
    fun senderMatchingIsCaseInsensitiveAndTrimmed() {
        val match = DynamicSmsParser.parse("  safaricom ", smsBundleReceived, seed)
        assertNotNull(match)
        assertEquals("sms_received", match!!.ruleId)
    }

    // ---- Negative cases --------------------------------------------------

    @Test
    fun unknownSender_isNull() {
        assertNull(DynamicSmsParser.parse("MPESA", bingwaSokoniData, seed))
    }

    @Test
    fun unknownMessage_fromKnownSender_isNull() {
        val body = "Your M-PESA balance was 0.00 on 24/7/26. Thank you for using M-PESA."
        assertNull(DynamicSmsParser.parse("Safaricom", body, seed))
    }

    @Test
    fun blankBody_isNull() {
        assertNull(DynamicSmsParser.parse("Safaricom", "   ", seed))
    }

    @Test
    fun disabledRule_isSkipped() {
        val disabledSeed = seed.copy(rules = seed.rules.map { it.copy(enabled = false) })
        assertNull(DynamicSmsParser.parse("Safaricom", bingwaSokoniData, disabledSeed))
    }

    // ---- Robustness ------------------------------------------------------

    @Test
    fun invalidRegexRule_isSkipped_withoutThrowing() {
        val broken = SmsRule(
            id = "broken_rule",
            name = "Broken",
            senderId = "Safaricom",
            pattern = "[unclosed(",
            matchType = SmsMatchType.REGEX,
            eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
            priority = 1
        )
        val set = SmsRuleSet(version = 2, rules = listOf(broken) + seed.rules)
        val match = DynamicSmsParser.parse("Safaricom", bingwaSokoniData, set)
        assertNotNull(match)
        assertEquals("data_received_bingwa_sokoni", match!!.ruleId)
    }

    @Test
    fun unknownMatchType_neverMatches() {
        val rule = SmsRule(
            id = "fuzzy",
            senderId = "Safaricom",
            pattern = "received",
            matchType = "FUZZY_MAGIC",
            eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
            priority = 1
        )
        val set = SmsRuleSet(version = 2, rules = listOf(rule) + seed.rules)
        val match = DynamicSmsParser.parse("Safaricom", bingwaSokoniData, set)
        assertEquals("data_received_bingwa_sokoni", match!!.ruleId)
    }

    @Test
    fun ruleWithOnlyUnknownEventTypes_isSkipped() {
        val rule = SmsRule(
            id = "future_event",
            senderId = "Safaricom",
            pattern = "received",
            matchType = SmsMatchType.REGEX,
            eventTypes = listOf("TELEPORT_RECEIVED"),
            priority = 1
        )
        val set = SmsRuleSet(version = 2, rules = listOf(rule) + seed.rules)
        val match = DynamicSmsParser.parse("Safaricom", bingwaSokoniData, set)
        assertEquals("data_received_bingwa_sokoni", match!!.ruleId)
    }

    @Test
    fun unknownEventTypesAreDropped_butKnownOnesSurvive() {
        val rule = SmsRule(
            id = "mixed_events",
            senderId = "Safaricom",
            pattern = "received",
            matchType = SmsMatchType.REGEX,
            eventTypes = listOf("TELEPORT_RECEIVED", "DATA_RECEIVED"),
            priority = 1
        )
        val set = SmsRuleSet(version = 2, rules = listOf(rule) + seed.rules)
        val match = DynamicSmsParser.parse("Safaricom", bingwaSokoniData, set)
        assertEquals("mixed_events", match!!.ruleId)
        assertEquals(listOf(SmsEventType.DATA_RECEIVED), match.events)
    }

    @Test
    fun lowerPriorityNumberWins_regardlessOfDeclarationOrder() {
        val late = SmsRule(
            id = "late_but_urgent",
            senderId = "Safaricom",
            pattern = "received",
            matchType = SmsMatchType.REGEX,
            eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
            priority = 1
        )
        // Declared LAST, but priority 1 must still be evaluated first.
        val set = SmsRuleSet(version = 2, rules = seed.rules + late)
        val match = DynamicSmsParser.parse("Safaricom", bingwaSokoniData, set)
        assertEquals("late_but_urgent", match!!.ruleId)
    }

    // ---- Match types -----------------------------------------------------

    @Test
    fun keywordsMatchType_requiresEveryKeyword() {
        val rule = SmsRule(
            id = "kw",
            senderId = "SAF_NEW",
            pattern = "umepokea, bando",
            matchType = SmsMatchType.KEYWORDS,
            eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
            priority = 1
        )
        val set = SmsRuleSet(version = 2, rules = listOf(rule))
        assertNotNull(DynamicSmsParser.parse("SAF_NEW", "Umepokea BANDO la 500MB", set))
        assertNull(DynamicSmsParser.parse("SAF_NEW", "Umepokea salamu", set))
    }

    @Test
    fun keywordsMatchType_acceptsPipeSeparator() {
        val rule = SmsRule(
            id = "kw_pipe",
            senderId = "SAF_NEW",
            pattern = "umepokea|bando",
            matchType = SmsMatchType.KEYWORDS,
            eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
            priority = 1
        )
        val set = SmsRuleSet(version = 2, rules = listOf(rule))
        assertNotNull(DynamicSmsParser.parse("SAF_NEW", "Umepokea bando la 500MB", set))
    }

    @Test
    fun templateMatchType_treatsStarAsWildcardAndEscapesLiterals() {
        val rule = SmsRule(
            id = "tpl",
            senderId = "Safaricom",
            pattern = "You have received * from Bingwa *",
            matchType = SmsMatchType.TEMPLATE,
            eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
            priority = 1
        )
        val set = SmsRuleSet(version = 2, rules = listOf(rule))
        assertNotNull(DynamicSmsParser.parse("Safaricom", bingwaSokoniData, set))
        assertNull(DynamicSmsParser.parse("Safaricom", smsBundleReceived, set))
    }

    @Test
    fun templateMatchType_literalRegexCharactersAreNotRegex() {
        // "*444#" must be matched literally, not read as a regex quantifier.
        val rule = SmsRule(
            id = "tpl_literal",
            senderId = "Safaricom",
            pattern = "Dial *444# for other exciting offers.",
            matchType = SmsMatchType.TEMPLATE,
            eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
            priority = 1
        )
        val set = SmsRuleSet(version = 2, rules = listOf(rule))
        assertNotNull(DynamicSmsParser.parse("Safaricom", bingwaSokoniData, set))
    }

    @Test
    fun blankSenderIdRule_matchesAnySender() {
        val rule = SmsRule(
            id = "catch_all",
            senderId = "",
            pattern = "bonasi",
            matchType = SmsMatchType.REGEX,
            eventTypes = listOf(SmsEventType.GIFT_RECEIVED.name),
            priority = 1
        )
        val set = SmsRuleSet(version = 2, rules = listOf(rule))
        val match = DynamicSmsParser.parse("A_BRAND_NEW_SHORTCODE", "Umepata bonasi ya 100MB", set)
        assertNotNull(match)
        assertEquals("catch_all", match!!.ruleId)
    }

    // ---- THE HEADLINE REQUIREMENT ---------------------------------------

    /**
     * A rule that exists ONLY on the server, for a sender id and a wording this
     * build has never seen, is recognised — and its amounts extracted — without a
     * single line of app code changing. This is the entire justification for the
     * dynamic engine.
     */
    @Test
    fun serverTaughtRule_withBrandNewSenderAndWording_isRecognised() {
        val serverRule = SmsRule(
            id = "srv_2027_swahili_data",
            name = "Bando Bomba (server-taught)",
            senderId = "SAF_BandoBomba",
            pattern = """umepokea\s+\d+(?:\.\d+)?\s*(?:MB|GB)""",
            matchType = SmsMatchType.REGEX,
            eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
            priority = 5,
            description = "Wording Safaricom invented after this APK was built."
        )
        val serverSet = SmsRuleSet(
            version = seed.version + 1,
            updatedAt = 1_800_000_000_000L,
            rules = seed.rules + serverRule
        )

        // The seed alone cannot possibly know this message.
        val newBody = "Umepokea 2.5GB ya wiki nzima kutoka Bando Bomba. Furahia!"
        assertNull(DynamicSmsParser.parse("SAF_BandoBomba", newBody, seed))

        // With the server-taught rule it is understood immediately.
        val match = DynamicSmsParser.parse("SAF_BandoBomba", newBody, serverSet)
        assertNotNull(match)
        assertEquals("srv_2027_swahili_data", match!!.ruleId)
        assertEquals(listOf(SmsEventType.DATA_RECEIVED), match.events)
        assertEquals(2560.0, match.extraction.dataMb!!, 0.001)
        assertEquals(OfferCategory.DATA, SmsEventMapping.primaryCategory(match))
    }

    // ---- Extractors ------------------------------------------------------

    @Test
    fun extractor_dataMb_handlesMbGbAndDecimals() {
        assertEquals(250.0, DynamicSmsParser.extract("Sh20=250MB 24hr").dataMb!!, 0.001)
        assertEquals(1024.0, DynamicSmsParser.extract("1024.0 MB hourly").dataMb!!, 0.001)
        assertEquals(1536.0, DynamicSmsParser.extract("You got 1.5GB today").dataMb!!, 0.001)
        assertEquals(75.0, DynamicSmsParser.extract("balance is 75MBs.").dataMb!!, 0.001)
        assertEquals(2.0, DynamicSmsParser.extract("balance is below 2MBs.").dataMb!!, 0.001)
        assertNull(DynamicSmsParser.extract("no size at all here").dataMb)
    }

    @Test
    fun extractor_minutes_handlesBrandedBundleNames() {
        assertEquals(45, DynamicSmsParser.extract("received 45 Talkmore Minutes.").minutes)
        assertEquals(50, DynamicSmsParser.extract("Easy-Talk 50 Minutes + 50 SMS.").minutes)
        assertEquals(43, DynamicSmsParser.extract("Sh20=43 Mins,3hrs").minutes)
        assertEquals(10, DynamicSmsParser.extract("Minutes are below 10 Minutes.").minutes)
        assertNull(DynamicSmsParser.extract("received 20 SMS Daily SMS Bundle.").minutes)
    }

    @Test
    fun extractor_smsCount() {
        assertEquals(20, DynamicSmsParser.extract("received 20 SMS Daily SMS Bundle.").smsCount)
        assertEquals(50, DynamicSmsParser.extract("Easy-Talk 50 Minutes + 50 SMS.").smsCount)
        assertNull(DynamicSmsParser.extract("Sh20=250MB 24hr from Bingwa Sokoni.").smsCount)
    }

    @Test
    fun extractor_priceKsh() {
        assertEquals(20, DynamicSmsParser.extract("You have received Sh20=250MB").priceKsh)
        assertEquals(20, DynamicSmsParser.extract("costs Sh 20 only").priceKsh)
        assertEquals(99, DynamicSmsParser.extract("Ksh 99 bundle").priceKsh)
        assertNull(DynamicSmsParser.extract("no price mentioned").priceKsh)
    }

    @Test
    fun extractor_bundleType() {
        assertEquals("hourly", DynamicSmsParser.extract("1024.0 MB hourly data bundle").bundleType)
        assertEquals("daily", DynamicSmsParser.extract("20 SMS Daily SMS Bundle").bundleType)
        assertEquals("weekly", DynamicSmsParser.extract("2GB weekly bundle").bundleType)
        assertEquals("monthly", DynamicSmsParser.extract("10GB monthly bundle").bundleType)
        // No explicit period word: a 24-hour bundle is daily, a shorter one hourly.
        assertEquals("daily", DynamicSmsParser.extract("Sh20=250MB 24hr from X").bundleType)
        assertEquals("hourly", DynamicSmsParser.extract("Sh20=43 Mins,3hrs from X").bundleType)
        assertEquals("", DynamicSmsParser.extract("nothing periodic here").bundleType)
    }

    @Test
    fun extractor_expiry_slashFormat() {
        val extraction = DynamicSmsParser.extract(
            "You have received 20 SMS Daily SMS Bundle. Expiry date: 22/05/2026 07:39AM."
        )
        assertEquals("22/05/2026 07:39AM", extraction.expiryText)
        assertEquals(nairobiMillis(2026, Calendar.MAY, 22, 7, 39), extraction.expiryMillis)
    }

    @Test
    fun extractor_expiry_dashFormat() {
        val extraction = DynamicSmsParser.extract("Expiry date 29-07-2026 09:57PM. Drama iko real.")
        assertEquals("29-07-2026 09:57PM", extraction.expiryText)
        assertEquals(nairobiMillis(2026, Calendar.JULY, 29, 21, 57), extraction.expiryMillis)
    }

    @Test
    fun extractor_expiry_spacedAmPmStillParses() {
        assertEquals(
            nairobiMillis(2026, Calendar.MAY, 22, 7, 39),
            DynamicSmsParser.extract("Expiry date: 22/05/2026 07:39 AM.").expiryMillis
        )
    }

    @Test
    fun extractor_expiry_unparseableKeepsTextButNullMillis() {
        // A shape the regex accepts but the calendar rejects (day 32, month 13, hour 25).
        val extraction = DynamicSmsParser.extract("Expiry date 32/13/2026 25:99AM.")
        assertEquals("32/13/2026 25:99AM", extraction.expiryText)
        assertNull(extraction.expiryMillis)
    }

    @Test
    fun extractor_missingExpiry_isEmptyTextAndNullMillis() {
        val extraction = DynamicSmsParser.extract("You have received Easy-Talk 50 Minutes + 50 SMS.")
        assertEquals("", extraction.expiryText)
        assertNull(extraction.expiryMillis)
    }

    // ---- Event mapping ---------------------------------------------------

    @Test
    fun eventMapping_splitsDeliveryFromLowBalance() {
        assertTrue(SmsEventMapping.isDelivery(SmsEventType.DATA_RECEIVED))
        assertTrue(SmsEventMapping.isDelivery(SmsEventType.GIFT_RECEIVED))
        assertTrue(SmsEventMapping.isLowBalance(SmsEventType.NO_DATA))
        assertTrue(SmsEventMapping.isLowBalance(SmsEventType.LOW_MINUTES))
        assertFalse(SmsEventMapping.isDelivery(SmsEventType.LOW_DATA))
        assertFalse(SmsEventMapping.isLowBalance(SmsEventType.SMS_RECEIVED))
        assertFalse(SmsEventMapping.isDelivery(SmsEventType.UNKNOWN))
        assertFalse(SmsEventMapping.isLowBalance(SmsEventType.UNKNOWN))
    }

    @Test
    fun eventMapping_categories() {
        val empty = SmsExtraction()
        assertEquals(OfferCategory.DATA, SmsEventMapping.categoryFor(SmsEventType.VERY_LOW_DATA, empty))
        assertEquals(OfferCategory.SMS, SmsEventMapping.categoryFor(SmsEventType.LOW_SMS, empty))
        assertEquals(
            OfferCategory.MINUTES,
            SmsEventMapping.categoryFor(SmsEventType.MINUTES_RECEIVED, empty)
        )
        assertNull(SmsEventMapping.categoryFor(SmsEventType.UNKNOWN, empty))
        assertNull(SmsEventMapping.categoryFor(SmsEventType.GIFT_RECEIVED, empty))
        assertEquals(
            OfferCategory.DATA,
            SmsEventMapping.categoryFor(SmsEventType.GIFT_RECEIVED, SmsExtraction(dataMb = 500.0))
        )
    }

    @Test
    fun eventTypeParsing_isCaseInsensitiveAndSafe() {
        assertEquals(SmsEventType.NO_DATA, SmsEventType.fromNameOrNull("no_data"))
        assertEquals(SmsEventType.NO_DATA, SmsEventType.fromNameOrNull("  NO_DATA "))
        assertNull(SmsEventType.fromNameOrNull("SOMETHING_NEW"))
        assertNull(SmsEventType.fromNameOrNull(""))
    }

    // ---- Helpers ---------------------------------------------------------

    /** Epoch millis for a wall-clock time in Africa/Nairobi (UTC+3, no DST). */
    private fun nairobiMillis(year: Int, month: Int, day: Int, hour: Int, minute: Int): Long {
        val calendar = Calendar.getInstance(TimeZone.getTimeZone("Africa/Nairobi"), Locale.US)
        calendar.clear()
        calendar.set(year, month, day, hour, minute, 0)
        calendar.set(Calendar.MILLISECOND, 0)
        return calendar.timeInMillis
    }
}
