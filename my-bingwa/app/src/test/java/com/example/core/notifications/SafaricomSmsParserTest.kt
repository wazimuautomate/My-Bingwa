package com.example.core.notifications

import com.example.core.model.OfferCategory
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Verifies the pure, data-driven parser against the four real seed samples plus
 * the negative cases (wrong sender, unrelated body).
 */
@Suppress("DEPRECATION") // Legacy engine, kept green until every caller moves to DynamicSmsParser.
class SafaricomSmsParserTest {

    private val templates = DefaultTemplates.SEED

    @Test
    fun dataDelivery_fromBingwaSokoni_isDeliveryData() {
        val body = "You have received Sh20=250MB 24hr from Bingwa Sokoni . " +
            "Dial *444*9# to check balance. Dial *444# for other exciting offers."
        val signal = SafaricomSmsParser.parse("Safaricom", body, templates)
        assertTrue(signal is SmsSignal.DeliveryDetected)
        assertEquals(OfferCategory.DATA, signal!!.category)
    }

    @Test
    fun smsDelivery_isDeliverySms() {
        val body = "You have received 20 SMS Daily SMS Bundle.  Expiry date: 22/05/2026 07:39AM."
        val signal = SafaricomSmsParser.parse("Safaricom", body, templates)
        assertTrue(signal is SmsSignal.DeliveryDetected)
        assertEquals(OfferCategory.SMS, signal!!.category)
    }

    @Test
    fun minutesGift_fromOfaMoto_isDeliveryMinutes() {
        val body = "You have received a gift of Sh20=43 Mins,3hrs from 111327201 ! " +
            "To check balance dial *144#."
        val signal = SafaricomSmsParser.parse("SAF_OfaMOTO", body, templates)
        assertTrue(signal is SmsSignal.DeliveryDetected)
        assertEquals(OfferCategory.MINUTES, signal!!.category)
    }

    @Test
    fun dataLowBalance_fromSafBalance_isLowBalanceData() {
        val body = "Dear customer, your deal of the day data balance is below 2MBs. " +
            "Simply dial *544# to get Data Deals that suite you."
        val signal = SafaricomSmsParser.parse("SAF_Balance", body, templates)
        assertTrue(signal is SmsSignal.LowBalanceDetected)
        assertEquals(OfferCategory.DATA, signal!!.category)
    }

    @Test
    fun senderIsCaseInsensitive() {
        val body = "You have received 20 SMS Daily SMS Bundle. Expiry date: 22/05/2026 07:39AM."
        val signal = SafaricomSmsParser.parse("safaricom", body, templates)
        assertTrue(signal is SmsSignal.DeliveryDetected)
    }

    @Test
    fun wrongSender_forDeliveryBody_isNull() {
        val body = "You have received Sh20=250MB 24hr from Bingwa Sokoni ."
        // A delivery body but from a random short code that no template claims.
        assertNull(SafaricomSmsParser.parse("MPESA", body, templates))
    }

    @Test
    fun unrelatedBody_fromKnownSender_isNull() {
        val body = "Your M-PESA balance was 0.00 on 24/7/26. Thank you for using M-PESA."
        assertNull(SafaricomSmsParser.parse("Safaricom", body, templates))
    }

    @Test
    fun blankInputs_areNull() {
        assertNull(SafaricomSmsParser.parse("", "some body", templates))
        assertNull(SafaricomSmsParser.parse("Safaricom", "   ", templates))
    }
}
