package com.example.core.sms

import com.example.core.model.OfferCategory

/**
 * Bridges the OPEN, server-teachable [SmsEventType] vocabulary to the CLOSED set
 * of things the rest of the app already understands: an [OfferCategory], and the
 * two legacy signal shapes (delivery vs low-balance).
 *
 * This is the one documented place where that translation happens, so a new
 * server event type only ever needs a decision here — never a change scattered
 * through the UI. Pure Kotlin, no Android imports.
 */
object SmsEventMapping {

    /** Events that mean "the carrier says something ARRIVED". */
    private val DELIVERY_EVENTS = setOf(
        SmsEventType.DATA_RECEIVED,
        SmsEventType.SMS_RECEIVED,
        SmsEventType.MINUTES_RECEIVED,
        SmsEventType.GIFT_RECEIVED
    )

    /** Events that mean "the carrier says a balance is running out". */
    private val LOW_BALANCE_EVENTS = setOf(
        SmsEventType.LOW_DATA,
        SmsEventType.VERY_LOW_DATA,
        SmsEventType.NO_DATA,
        SmsEventType.LOW_SMS,
        SmsEventType.LOW_MINUTES
    )

    /** True when [event] is a delivery signal. */
    fun isDelivery(event: SmsEventType): Boolean = event in DELIVERY_EVENTS

    /** True when [event] is a low-balance signal. */
    fun isLowBalance(event: SmsEventType): Boolean = event in LOW_BALANCE_EVENTS

    /**
     * The offer category an event refers to, or null when it is not
     * category-specific.
     *
     * `GIFT_RECEIVED` is deliberately ambiguous — a gift can be data, minutes or
     * SMS — so it is resolved from what was actually extracted from the message
     * (minutes first, because every observed Ofa Moto gift so far is talktime).
     */
    fun categoryFor(event: SmsEventType, extraction: SmsExtraction): OfferCategory? =
        when (event) {
            SmsEventType.DATA_RECEIVED,
            SmsEventType.LOW_DATA,
            SmsEventType.VERY_LOW_DATA,
            SmsEventType.NO_DATA -> OfferCategory.DATA

            SmsEventType.SMS_RECEIVED,
            SmsEventType.LOW_SMS -> OfferCategory.SMS

            SmsEventType.MINUTES_RECEIVED,
            SmsEventType.LOW_MINUTES -> OfferCategory.MINUTES

            SmsEventType.GIFT_RECEIVED -> when {
                extraction.minutes != null -> OfferCategory.MINUTES
                extraction.dataMb != null -> OfferCategory.DATA
                extraction.smsCount != null -> OfferCategory.SMS
                else -> null
            }

            SmsEventType.UNKNOWN -> null
        }

    /**
     * The single category that best describes a whole [SmsMatch]. Used for the
     * `category` field of the new signal; [OfferCategory.ALL] means "matched, but
     * not tied to one category".
     */
    fun primaryCategory(match: SmsMatch): OfferCategory {
        for (event in match.events) {
            val category = categoryFor(event, match.extraction)
            if (category != null) return category
        }
        return OfferCategory.ALL
    }
}
