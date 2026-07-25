package com.example.data.fake

import com.example.core.model.OfferCategory
import com.example.core.model.PaymentMethod
import com.example.core.model.PaymentStatus
import com.example.core.model.PurchaseRecord
import com.example.core.payment.PaymentTxnState
import com.example.data.payment.SimulatedPaymentGateway
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Reconciliation of Safaricom SMS signals into local state:
 * - onBundleDeliveryDetected flips only the newest matching RECEIVED record's
 *   [com.example.core.model.PurchaseRecord.isDeliveryConfirmed] and adds a
 *   carrier-attributed in-app notification (never a "we delivered" claim).
 * - onLowBalanceDetected adds an offers suggestion using only §8-allowed
 *   language (no "running out" / "need more data").
 */
class SmsReconciliationTest {

    private fun repo() = FakeBingwaRepositoryImpl(
        gateway = SimulatedPaymentGateway(
            terminalOutcome = { PaymentTxnState.PAYMENT_CONFIRMED },
            initiateDelayMillis = 0,
            statusDelayMillis = 0
        ),
        // The app no longer seeds demo purchases (fresh install starts empty), so this
        // test provides its own RECEIVED records to reconcile against (fake numbers).
        seedPurchases = listOf(
            PurchaseRecord(
                id = "seed_data", offerId = "data_1", offerName = "1GB", allowance = "1GB",
                priceKsh = 19, recipientNumber = "0700000000", payerNumber = "0700000000",
                mpesaCode = "T1", timestampMillis = 2000L,
                status = PaymentStatus.RECEIVED, paymentMethod = PaymentMethod.STK_PUSH
            ),
            PurchaseRecord(
                id = "seed_sms", offerId = "sms_1", offerName = "10 SMS", allowance = "10 SMS",
                priceKsh = 5, recipientNumber = "0711111111", payerNumber = "0700000000",
                mpesaCode = "T2", timestampMillis = 1000L,
                status = PaymentStatus.RECEIVED, paymentMethod = PaymentMethod.STK_PUSH
            )
        )
    )

    @Test
    fun onBundleDeliveryDetected_flipsNewestMatchingReceivedRecord() {
        val repository = repo()
        // Seed: pur_101 = 1GB DATA (RECEIVED, newest), pur_102 = 10 SMS (RECEIVED).
        val dataRecordBefore = repository.purchases.value.first { it.offerId == "data_1" }
        val smsRecordBefore = repository.purchases.value.first { it.offerId == "sms_1" }
        assertFalse(dataRecordBefore.isDeliveryConfirmed)

        repository.onBundleDeliveryDetected(OfferCategory.DATA)

        val dataRecordAfter = repository.purchases.value.first { it.id == dataRecordBefore.id }
        val smsRecordAfter = repository.purchases.value.first { it.id == smsRecordBefore.id }
        assertTrue("DATA record must be carrier-confirmed", dataRecordAfter.isDeliveryConfirmed)
        assertFalse("Unrelated SMS record must stay unconfirmed", smsRecordAfter.isDeliveryConfirmed)
    }

    @Test
    fun onBundleDeliveryDetected_addsCarrierAttributedNotification() {
        val repository = repo()
        val before = repository.notifications.value.size

        repository.onBundleDeliveryDetected(OfferCategory.DATA)

        val notifications = repository.notifications.value
        assertEquals(before + 1, notifications.size)
        val newest = notifications.first()
        assertEquals("activity", newest.deepLinkRoute)
        assertFalse(newest.isRead)
        // Honest, carrier-attributed — never a banned delivery claim (§7).
        val text = (newest.title + " " + newest.body).lowercase()
        assertTrue("Must attribute to Safaricom", text.contains("safaricom"))
        assertFalse(text.contains("we delivered"))
        assertFalse(text.contains("data activated"))
        assertFalse(text.contains("delivery successful"))
    }

    @Test
    fun onBundleDeliveryDetected_secondSignalDoesNotReconfirmSameRecord() {
        val repository = repo()
        // Only one RECEIVED DATA record is seeded; a second delivery signal has no
        // further unconfirmed DATA record, so no new record is flipped.
        repository.onBundleDeliveryDetected(OfferCategory.DATA)
        val confirmedCount1 = repository.purchases.value.count { it.isDeliveryConfirmed }

        repository.onBundleDeliveryDetected(OfferCategory.DATA)
        val confirmedCount2 = repository.purchases.value.count { it.isDeliveryConfirmed }

        assertEquals(confirmedCount1, confirmedCount2)
    }

    @Test
    fun onLowBalanceDetected_addsOffersSuggestionWithAllowedLanguage() {
        val repository = repo()
        val before = repository.notifications.value.size

        repository.onLowBalanceDetected(OfferCategory.DATA)

        val notifications = repository.notifications.value
        assertEquals(before + 1, notifications.size)
        val newest = notifications.first()
        assertEquals("offers", newest.deepLinkRoute)
        assertFalse(newest.isRead)
        // §8 forbidden claims must never appear.
        val text = (newest.title + " " + newest.body).lowercase()
        assertFalse(text.contains("running out"))
        assertFalse(text.contains("you need more"))
        assertFalse(text.contains("recommended for your usage"))
    }
}
