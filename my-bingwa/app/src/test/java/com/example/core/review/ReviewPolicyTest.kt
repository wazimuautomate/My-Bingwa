package com.example.core.review

import com.example.core.model.PaymentMethod
import com.example.core.model.PaymentStatus
import com.example.core.model.PurchaseRecord
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * When My Bingwa is allowed to ask for a Play rating. The rules only ever get
 * stricter than Google's own quota, so every test here is about NOT asking.
 */
class ReviewPolicyTest {

    private val day = 86_400_000L
    private val now = 1_800_000_000_000L

    private fun purchase(status: PaymentStatus, id: String = "p") = PurchaseRecord(
        id = id,
        offerId = "data_2",
        offerName = "250MB",
        allowance = "250MB",
        priceKsh = 20,
        recipientNumber = "0712345678",
        payerNumber = "0712345678",
        mpesaCode = "ABC123",
        timestampMillis = now,
        status = status,
        paymentMethod = PaymentMethod.STK_PUSH
    )

    private fun received(count: Int) = (1..count).map { purchase(PaymentStatus.RECEIVED, "p$it") }

    @Test
    fun `never asks before two received purchases`() {
        assertFalse(ReviewPolicy.shouldPrompt(emptyList(), 0L, now))
        assertFalse(ReviewPolicy.shouldPrompt(received(1), 0L, now))
        assertTrue(ReviewPolicy.shouldPrompt(received(2), 0L, now))
    }

    @Test
    fun `only received purchases count towards the threshold`() {
        val mixed = listOf(
            purchase(PaymentStatus.RECEIVED, "a"),
            purchase(PaymentStatus.FAILED, "b"),
            purchase(PaymentStatus.WAITING_VERIFY, "c"),
            purchase(PaymentStatus.CANCELLED, "d")
        )
        assertFalse(ReviewPolicy.shouldPrompt(mixed, 0L, now))
    }

    @Test
    fun `will not ask twice inside sixty days`() {
        val yesterday = now - day
        assertFalse(ReviewPolicy.shouldPrompt(received(5), yesterday, now))

        val fiftyNineDaysAgo = now - 59 * day
        assertFalse(ReviewPolicy.shouldPrompt(received(5), fiftyNineDaysAgo, now))

        val sixtyDaysAgo = now - 60 * day
        assertTrue(ReviewPolicy.shouldPrompt(received(5), sixtyDaysAgo, now))
    }

    @Test
    fun `a clock moved backwards does not unlock the prompt`() {
        // A manual clock change must not become a way to be asked again.
        val promptedInTheFuture = now + 10 * day
        assertFalse(ReviewPolicy.shouldPrompt(received(5), promptedInTheFuture, now))
    }

    @Test
    fun `the settle delay is seconds, not minutes`() {
        // The card has to clear the post-payment SMS pile-up without arriving so late
        // that the customer has moved on.
        assertTrue(ReviewPolicy.SETTLE_DELAY_MILLIS in 3_000L..15_000L)
    }
}
