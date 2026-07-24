package com.example.core.model

enum class DailyRule(val displayText: String) {
    ONCE_PER_DAY("Once per day"),
    BUY_AGAIN_TODAY("Buy many times")
}

/**
 * How often an offer may be bought per recipient in a single Nairobi day
 * (Plan.md §5.12). This is purchase awareness, never a data-usage recommender.
 *
 * - [MULTIPLE_PER_DAY]           no per-day cap; always keeps "Buy again".
 * - [ONCE_PER_RECIPIENT_PER_DAY] one purchase per recipient per day.
 * - [MAX_PER_RECIPIENT_PER_DAY]  up to [OfferItem.maxPurchasesPerDay] per
 *                                recipient per day.
 *
 * The canonical per-recipient, Nairobi-day ledger is built in Phase 6; Phase 3
 * only presents the state ([com.example.feature.home.dailyStateFor]).
 */
enum class PurchasePolicy {
    MULTIPLE_PER_DAY,
    ONCE_PER_RECIPIENT_PER_DAY,
    MAX_PER_RECIPIENT_PER_DAY
}

data class OfferItem(
    val id: String,
    val name: String,
    val allowance: String,
    val priceKsh: Int,
    val validity: String,
    val category: OfferCategory,
    val dailyRule: DailyRule,
    val purchasePolicy: PurchasePolicy = when (dailyRule) {
        DailyRule.ONCE_PER_DAY -> PurchasePolicy.ONCE_PER_RECIPIENT_PER_DAY
        DailyRule.BUY_AGAIN_TODAY -> PurchasePolicy.MULTIPLE_PER_DAY
    },
    val maxPurchasesPerDay: Int? = null,
    val commercialLabel: String? = null, // e.g. "Best value", "Popular", "Limited offer"
    val isPopular: Boolean = false,
    val isFavourite: Boolean = false,
    val isBoughtToday: Boolean = false,
    val description: String = "",
    val offlineInstructionsExpired: Boolean = false
)
