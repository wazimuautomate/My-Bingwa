package com.example.feature.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.example.core.model.OfferDailyState
import com.example.core.model.OfferItem
import com.example.core.model.Promotion
import com.example.core.model.PurchaseRecord
import com.example.data.fake.BingwaRepository
import com.example.data.fake.OfferFilterState
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

/** Immutable Home state (Plan.md §5.2 content order). */
data class HomeUiState(
    val loading: Boolean = true,
    val greetingName: String = "",
    val isOffline: Boolean = false,
    val promotions: List<Promotion> = emptyList(),
    val sections: HomeSections = HomeSections(emptyList(), emptyList(), emptyList(), emptyList(), emptyList(), emptyList()),
    val purchases: List<PurchaseRecord> = emptyList(),
    val recipientNumber: String = "",
    val nowMillis: Long = 0L
) {
    val hasAnyContent: Boolean
        get() = sections.popular.isNotEmpty() || sections.favourites.isNotEmpty() ||
            sections.buyAgain.isNotEmpty() || sections.boughtToday.isNotEmpty() ||
            sections.moreOffers.isNotEmpty() || sections.suggestions.isNotEmpty()
}

/** Immutable Offers state: filtered/sorted results plus which empty case applies. */
data class OffersUiState(
    val loading: Boolean = true,
    val isOffline: Boolean = false,
    val filter: OfferFilterState = OfferFilterState(),
    val results: List<OfferItem> = emptyList(),
    val totalOffers: Int = 0,
    val purchases: List<PurchaseRecord> = emptyList(),
    val recipientNumber: String = "",
    val nowMillis: Long = 0L
) {
    val resultCount: Int get() = results.size
    /** True when there are offers in the catalogue but the current filters hide them all. */
    val emptyFromFilters: Boolean get() = results.isEmpty() && totalOffers > 0
}

/**
 * Screen-level ViewModel for the catalogue experience (Home + Offers). It only
 * derives immutable UI state from the repository flows and forwards user intents;
 * it holds no mutable catalogue truth of its own. A [clock] is injected so the
 * daily-purchase and promotion logic is deterministic under test.
 */
class CatalogueViewModel(
    private val repository: BingwaRepository,
    private val clock: () -> Long = { System.currentTimeMillis() }
) : ViewModel() {

    // isOffline + catalogueLoading folded into one flow so each UI state combines
    // exactly five flows (the type-safe combine arity), no casts.
    private val connectivity: Flow<Pair<Boolean, Boolean>> =
        combine(repository.isOffline, repository.catalogueLoading) { offline, loading -> offline to loading }

    val homeUiState: StateFlow<HomeUiState> =
        combine(
            repository.offers,
            repository.promotions,
            repository.purchases,
            repository.userProfile,
            connectivity
        ) { offers, promotions, purchases, profile, flags ->
            val (offline, loading) = flags
            val now = clock()
            HomeUiState(
                loading = loading && offers.isEmpty(),
                greetingName = profile.name,
                isOffline = offline,
                promotions = selectPromotions(promotions, offers, now, seed = nairobiDayIndex(now)),
                sections = deriveHomeSections(offers, purchases, now),
                purchases = purchases,
                recipientNumber = profile.primaryNumber,
                nowMillis = now
            )
        }.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), HomeUiState())

    val offersUiState: StateFlow<OffersUiState> =
        combine(
            repository.offers,
            repository.filterState,
            repository.purchases,
            repository.userProfile,
            connectivity
        ) { offers, filter, purchases, profile, flags ->
            val (offline, loading) = flags
            OffersUiState(
                loading = loading && offers.isEmpty(),
                isOffline = offline,
                filter = filter,
                results = filterAndSortOffers(offers, filter),
                totalOffers = offers.size,
                purchases = purchases,
                recipientNumber = profile.primaryNumber,
                nowMillis = clock()
            )
        }.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), OffersUiState())

    // --- Intents ---------------------------------------------------------

    fun setSearchQuery(query: String) = repository.setSearchQuery(query)
    fun setCategory(category: com.example.core.model.OfferCategory) = repository.setCategoryFilter(category)
    fun setFilter(filter: OfferFilterState) = repository.setFilterState(filter)
    fun clearFilters() = repository.clearFilters()

    fun toggleFavourite(offer: OfferItem) = repository.setFavourite(offer.id, !offer.isFavourite)
    fun setFavourite(offerId: String, isFavourite: Boolean) = repository.setFavourite(offerId, isFavourite)

    fun refresh() {
        viewModelScope.launch { repository.refreshCatalogue() }
    }

    /** Daily-purchase presentation for an offer against the profile's own number. */
    fun dailyState(offer: OfferItem, state: HomeUiState): OfferDailyState =
        dailyStateFor(offer, state.purchases, state.recipientNumber, state.nowMillis)
}
