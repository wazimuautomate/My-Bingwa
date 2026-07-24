package com.example.core.model

enum class DailyRule(val displayText: String) {
    ONCE_PER_DAY("Once per day"),
    BUY_AGAIN_TODAY("Buy again today")
}

data class OfferItem(
    val id: String,
    val name: String,
    val allowance: String,
    val priceKsh: Int,
    val validity: String,
    val category: OfferCategory,
    val dailyRule: DailyRule,
    val commercialLabel: String? = null, // e.g. "Best value", "Popular", "Limited offer"
    val isPopular: Boolean = false,
    val isFavourite: Boolean = false,
    val isBoughtToday: Boolean = false,
    val description: String = "",
    val offlineInstructionsExpired: Boolean = false
)
