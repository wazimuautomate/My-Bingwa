package com.example.core.notifications

import com.example.core.model.OfferCategory

/**
 * The SEED [TemplateSet] shipped in the APK, built from real Safaricom / Bingwa
 * Sokoni sample messages. This is the offline fallback until (and if) the server
 * syncs a newer set via [RemoteTemplateSync].
 *
 * Every regex here is deliberately tolerant of amounts, dates and whitespace so
 * small wording changes keep matching. Patterns are triple-quoted raw strings so
 * regex backslashes need no escaping, and they are matched case-insensitively
 * (see [SafaricomSmsParser]). Keep the lists easily extendable — add a new
 * template object rather than adding logic to the parser.
 */
@Suppress("DEPRECATION")
@Deprecated(
    message = "Superseded by com.example.core.sms.DefaultSmsRules.SEED.",
    level = DeprecationLevel.WARNING
)
object DefaultTemplates {

    val SEED: TemplateSet = TemplateSet(
        version = 1,
        delivery = listOf(
            // DATA delivery, sender "Safaricom".
            // Sample: "You have received Sh20=250MB 24hr from Bingwa Sokoni . Dial
            // *444*9# to check balance. Dial *444# for other exciting offers."
            DeliveryTemplate(
                id = "data_bingwa_sokoni",
                senderId = "Safaricom",
                category = OfferCategory.DATA,
                pattern = """received\b.*?\d+\s*(?:MB|GB).*?from\s+Bingwa\s+Sokoni""",
                description = "Bingwa Sokoni data bundle delivery (Sh<amount>=<size>MB/GB)."
            ),
            // SMS delivery, sender "Safaricom".
            // Sample: "You have received 20 SMS Daily SMS Bundle.  Expiry date:
            // 22/05/2026 07:39AM."
            DeliveryTemplate(
                id = "sms_daily_bundle",
                senderId = "Safaricom",
                category = OfferCategory.SMS,
                pattern = """received\s+\d+\s+SMS""",
                description = "SMS bundle delivery (received <n> SMS ...)."
            ),
            // MINUTES delivery, sender "SAF_OfaMOTO".
            // Sample: "You have received a gift of Sh20=43 Mins,3hrs from 111327201 ! ..."
            DeliveryTemplate(
                id = "minutes_gift",
                senderId = "SAF_OfaMOTO",
                category = OfferCategory.MINUTES,
                pattern = """received\s+a\s+gift\s+of.*?\d+\s*Mins""",
                description = "Minutes/talktime gift delivery (Sh<amount>=<n> Mins)."
            )
        ),
        lowBalance = listOf(
            // DATA low-balance, sender "SAF_Balance".
            // Sample: "Dear customer, your deal of the day data balance is below
            // 2MBs. Simply dial *544# to get Data Deals that suite you."
            LowBalanceTemplate(
                id = "data_low_balance",
                senderId = "SAF_Balance",
                category = OfferCategory.DATA,
                pattern = """data\s+balance\s+is\s+below""",
                description = "Deal-of-the-day data running low."
            )
            // NOTE: real SMS and MINUTES low-balance message formats are not known
            // yet. When samples arrive they will be supplied by the server as new
            // LowBalanceTemplate entries (senderId + regex + category). Nothing in
            // the parser needs to change — just extend this list.
        )
    )
}
