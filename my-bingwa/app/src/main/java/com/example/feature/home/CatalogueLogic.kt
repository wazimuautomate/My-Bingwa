package com.example.feature.home

import com.example.core.model.DailyStateKind
import com.example.core.model.OfferCategory
import com.example.core.model.OfferDailyState
import com.example.core.model.OfferItem
import com.example.core.model.PaymentStatus
import com.example.core.model.Promotion
import com.example.core.model.PromotionKind
import com.example.core.model.PurchasePolicy
import com.example.core.model.PurchaseRecord
import com.example.data.fake.OfferFilterState
import com.example.data.fake.SortOption
import com.example.data.fake.ValidityFilter
import java.util.Calendar
import java.util.TimeZone

/**
 * Pure, framework-free catalogue logic for Phase 3 (Home, Offers, favourites,
 * daily purchase awareness, promotions). Everything here is deterministic and
 * unit-tested — no Compose, no Android UI, no clocks read internally (callers
 * pass `nowMillis`) so tests are stable.
 *
 * Product behaviour follows Plan.md §5.2/5.3/5.12/5.13; purchase awareness is
 * never a data-usage recommender.
 */

private val NAIROBI: TimeZone = TimeZone.getTimeZone("Africa/Nairobi")

/** Absolute day number in Africa/Nairobi, so "today" resets at local midnight. */
fun nairobiDayIndex(millis: Long): Long {
    val cal = Calendar.getInstance(NAIROBI)
    cal.timeInMillis = millis
    val year = cal.get(Calendar.YEAR)
    val dayOfYear = cal.get(Calendar.DAY_OF_YEAR)
    // Encode as a monotonically increasing key; exact value is irrelevant, only equality/order.
    return year * 1000L + dayOfYear
}

fun isSameNairobiDay(a: Long, b: Long): Boolean = nairobiDayIndex(a) == nairobiDayIndex(b)

/**
 * Approximate validity length in minutes, used only for Shortest/Longest sort.
 * Parses the human validity string ("1 hour", "24 hours", "7 days", "Weekend",
 * "Till midnight"). Unknown strings sort in the middle.
 */
fun validityRankMinutes(validity: String): Int {
    val v = validity.lowercase().trim()
    val number = Regex("\\d+").find(v)?.value?.toIntOrNull()
    return when {
        v.contains("month") -> (number ?: 1) * 30 * 24 * 60
        v.contains("week") || v.contains("weekend") -> (number ?: 1) * 7 * 24 * 60
        v.contains("day") -> (number ?: 1) * 24 * 60
        v.contains("midnight") -> 12 * 60 // "midnight": treat as a partial day
        v.contains("hour") || v.contains("hr") -> (number ?: 1) * 60
        v.contains("min") -> number ?: 30
        else -> 24 * 60
    }
}

private fun matchesValidityFilter(offer: OfferItem, filter: ValidityFilter): Boolean {
    if (filter == ValidityFilter.ALL) return true
    // Prefer the explicit band (HOURLY/DAILY/WEEKLY/MONTHLY) when the offer sets one.
    if (offer.validityBand.isNotEmpty()) {
        return offer.validityBand.equals(filter.name, ignoreCase = true)
    }
    val v = offer.validity.lowercase()
    return when (filter) {
        ValidityFilter.ALL -> true
        ValidityFilter.HOURLY -> v.contains("hour") || v.contains("hr")
        ValidityFilter.DAILY -> v.contains("day") || v.contains("24") || v.contains("midnight")
        ValidityFilter.WEEKLY -> v.contains("week") || v.contains("7")
        ValidityFilter.MONTHLY -> v.contains("month") || v.contains("30")
    }
}

private fun matchesSearch(offer: OfferItem, rawQuery: String): Boolean {
    val query = rawQuery.trim().lowercase()
    if (query.isEmpty()) return true
    return offer.name.lowercase().contains(query) ||
        offer.allowance.lowercase().contains(query) ||
        offer.category.label.lowercase().contains(query) ||
        offer.description.lowercase().contains(query) ||
        offer.priceKsh.toString().contains(query)
}

/** Full Offers-screen pipeline: category + favourites + search + price + validity, then sort. */
fun filterAndSortOffers(offers: List<OfferItem>, filter: OfferFilterState): List<OfferItem> {
    val filtered = offers.filter { offer ->
        val matchesCategory = when (filter.selectedCategory) {
            OfferCategory.ALL -> true
            OfferCategory.FAVOURITES -> offer.isFavourite
            else -> offer.category == filter.selectedCategory
        }
        matchesCategory &&
            matchesSearch(offer, filter.searchQuery) &&
            offer.priceKsh <= filter.maxPriceKsh &&
            matchesValidityFilter(offer, filter.selectedValidity)
    }
    return sortOffers(filtered, filter.selectedSort)
}

fun sortOffers(offers: List<OfferItem>, sort: SortOption): List<OfferItem> = when (sort) {
    // "Popular" keeps flagged-popular first, then the highest-value offers, so the
    // list still reads sensibly when few offers are flagged.
    SortOption.POPULAR -> offers.sortedWith(
        compareByDescending<OfferItem> { it.isPopular }.thenByDescending { it.priceKsh }
    )
    SortOption.LOWEST_PRICE -> offers.sortedBy { it.priceKsh }
    SortOption.HIGHEST_VALUE -> offers.sortedByDescending { it.priceKsh }
    SortOption.SHORTEST_VALIDITY -> offers.sortedBy { validityRankMinutes(it.validity) }
    SortOption.LONGEST_VALIDITY -> offers.sortedByDescending { validityRankMinutes(it.validity) }
}

// ---------------------------------------------------------------------------
// Daily purchase awareness (presentation only)
// ---------------------------------------------------------------------------

/**
 * Presentation-level daily state for [offer] and a specific [recipientNumber],
 * derived only from this installation's My Bingwa payments (Plan.md §5.12).
 * Offline installs can only see local history — that limitation is honoured by
 * callers, not hidden here.
 */
fun dailyStateFor(
    offer: OfferItem,
    purchases: List<PurchaseRecord>,
    recipientNumber: String,
    nowMillis: Long
): OfferDailyState {
    val todaysForRecipient = purchases.filter {
        it.offerId == offer.id &&
            normaliseNumber(it.recipientNumber) == normaliseNumber(recipientNumber) &&
            isSameNairobiDay(it.timestampMillis, nowMillis)
    }
    val received = todaysForRecipient.count { it.status == PaymentStatus.RECEIVED }
    val waiting = todaysForRecipient.count { it.status == PaymentStatus.WAITING_VERIFY }

    return when (offer.purchasePolicy) {
        PurchasePolicy.MULTIPLE_PER_DAY ->
            OfferDailyState(DailyStateKind.AVAILABLE, "Available today")

        PurchasePolicy.ONCE_PER_RECIPIENT_PER_DAY -> when {
            received > 0 -> OfferDailyState(DailyStateKind.AVAILABLE_TOMORROW, "Available again tomorrow")
            waiting > 0 -> OfferDailyState(DailyStateKind.WAITING_VERIFY, "Waiting to verify")
            else -> OfferDailyState(DailyStateKind.AVAILABLE, "Available today")
        }

        PurchasePolicy.MAX_PER_RECIPIENT_PER_DAY -> {
            val max = offer.maxPurchasesPerDay ?: 1
            val left = (max - received).coerceAtLeast(0)
            when {
                left <= 0 -> OfferDailyState(DailyStateKind.AVAILABLE_TOMORROW, "Available again tomorrow")
                waiting > 0 && left - waiting <= 0 -> OfferDailyState(DailyStateKind.WAITING_VERIFY, "Waiting to verify")
                else -> OfferDailyState(
                    DailyStateKind.PURCHASES_LEFT,
                    if (left == 1) "1 purchase left today" else "$left purchases left today",
                    purchasesLeft = left
                )
            }
        }
    }
}

/** True when at least one successful purchase for this offer happened today (any recipient). */
fun isBoughtTodayByInstall(offer: OfferItem, purchases: List<PurchaseRecord>, nowMillis: Long): Boolean =
    purchases.any {
        it.offerId == offer.id &&
            it.status == PaymentStatus.RECEIVED &&
            isSameNairobiDay(it.timestampMillis, nowMillis)
    }

private fun normaliseNumber(number: String): String = number.filter { it.isDigit() }

// ---------------------------------------------------------------------------
// Home sections (Plan.md §5.2 content order)
// ---------------------------------------------------------------------------

data class HomeSections(
    val popular: List<OfferItem>,
    val boughtToday: List<OfferItem>,
    val moreOffers: List<OfferItem>,
    val buyAgain: List<OfferItem>,
    val favourites: List<OfferItem>,
    val suggestions: List<OfferItem>
)

/**
 * Derive Home's ordered sections from the cached catalogue and this
 * installation's purchase history. Personalised but restrained (Plan.md §5.2):
 * we surface Popular, what was bought today, repeatable "Buy again", favourites
 * and a small "You might also like" set drawn from the categories the customer
 * actually buys/favourites — never a data-usage recommendation.
 */
fun deriveHomeSections(
    offers: List<OfferItem>,
    purchases: List<PurchaseRecord>,
    nowMillis: Long,
    popularLimit: Int = 6,
    suggestionLimit: Int = 4
): HomeSections {
    val byId = offers.associateBy { it.id }

    val popular = offers.filter { it.isPopular }
        .sortedByDescending { it.priceKsh }
        .take(popularLimit)

    val boughtTodayIds = purchases
        .filter { it.status == PaymentStatus.RECEIVED && isSameNairobiDay(it.timestampMillis, nowMillis) }
        .map { it.offerId }
        .toSet()
    val boughtToday = offers.filter { it.id in boughtTodayIds }

    // "More offers you can buy" only earns its place once a once-per-day offer
    // has actually been used today (Plan.md §5.2 item 7).
    val usedAOncePerDay = boughtToday.any { it.purchasePolicy == PurchasePolicy.ONCE_PER_RECIPIENT_PER_DAY }
    val moreOffers = if (usedAOncePerDay) {
        offers.filter { it.id !in boughtTodayIds && it.purchasePolicy != PurchasePolicy.ONCE_PER_RECIPIENT_PER_DAY }
            .sortedByDescending { it.isPopular }
            .take(popularLimit)
    } else {
        emptyList()
    }

    // "Buy again": repeatable offers the customer has actually bought, newest first, de-duplicated.
    val buyAgain = purchases
        .asSequence()
        .filter { it.status == PaymentStatus.RECEIVED }
        .sortedByDescending { it.timestampMillis }
        .map { it.offerId }
        .distinct()
        .mapNotNull { byId[it] }
        .filter { it.purchasePolicy == PurchasePolicy.MULTIPLE_PER_DAY }
        .take(popularLimit)
        .toList()

    val favourites = offers.filter { it.isFavourite }
    val suggestions = suggestSimilar(offers, purchases, suggestionLimit)

    return HomeSections(
        popular = popular,
        boughtToday = boughtToday,
        moreOffers = moreOffers,
        buyAgain = buyAgain,
        favourites = favourites,
        suggestions = suggestions
    )
}

/**
 * "You might also like" — offers in the categories the customer favourites or
 * buys, excluding what they already favourite or bought today. Returns empty
 * when there is no signal, so the section stays silent rather than noisy.
 */
fun suggestSimilar(
    offers: List<OfferItem>,
    purchases: List<PurchaseRecord>,
    limit: Int = 4
): List<OfferItem> {
    val favouriteCategories = offers.filter { it.isFavourite }.map { it.category }
    val purchasedOfferIds = purchases.filter { it.status == PaymentStatus.RECEIVED }.map { it.offerId }.toSet()
    val purchasedCategories = offers.filter { it.id in purchasedOfferIds }.map { it.category }

    val affinity: Map<OfferCategory, Int> = (favouriteCategories + purchasedCategories)
        .filter { it != OfferCategory.ALL && it != OfferCategory.FAVOURITES }
        .groupingBy { it }
        .eachCount()

    if (affinity.isEmpty()) return emptyList()

    return offers
        .filter { !it.isFavourite && it.id !in purchasedOfferIds && affinity.containsKey(it.category) }
        .sortedWith(
            compareByDescending<OfferItem> { affinity[it.category] ?: 0 }
                .thenByDescending { it.isPopular }
                .thenByDescending { it.priceKsh }
        )
        .take(limit)
}

// ---------------------------------------------------------------------------
// Promotion / announcement billboard selection (Plan.md §5.13)
// ---------------------------------------------------------------------------

/**
 * Pick the promotions shown on the Home billboard. We keep only active slides
 * whose linked offer still exists, then rank so the seller's biggest offers
 * (higher price / longer validity, encoded via [Promotion.priorityWeight]) and
 * announcements lead, and finally break ties with a caller-supplied [seed] so
 * the surface feels freshly shuffled per session without ever auto-rotating.
 */
fun selectPromotions(
    pool: List<Promotion>,
    offers: List<OfferItem>,
    nowMillis: Long,
    seed: Long,
    max: Int = 5
): List<Promotion> {
    val offerIds = offers.map { it.id }.toSet()
    val active = pool.filter { promo ->
        promo.isActive(nowMillis) &&
            (promo.kind != PromotionKind.OFFER || promo.linkedOfferId in offerIds)
    }
    if (active.isEmpty()) return emptyList()

    // Deterministic per-slide jitter from the seed keeps ordering stable within a
    // session but varied between sessions (the "randomly posts offers" feel).
    fun jitter(id: String): Int = ((id.hashCode().toLong() xor seed) and 0xFFFF).toInt()

    return active
        .sortedWith(
            compareByDescending<Promotion> { it.priorityWeight }
                .thenBy { jitter(it.id) }
        )
        .take(max)
}
