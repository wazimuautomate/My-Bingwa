package com.example.feature.home

import com.example.core.model.OfferCategory
import com.example.data.fake.FakeBingwaRepositoryImpl
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.launch
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * State tests for [CatalogueViewModel]. The clock is fixed so promotion and
 * daily-purchase derivation is deterministic. Because the exposed state uses
 * `WhileSubscribed`, every test keeps a collector alive on `backgroundScope`
 * before reading `.value`, and drains work with `runCurrent()` after intents.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class CatalogueViewModelTest {

    // 2024-01-10 12:00 Africa/Nairobi; fixed so selectPromotions/day logic is stable.
    private val fixedClock: () -> Long = { 1_704_877_200_000L }
    private val mainDispatcher = UnconfinedTestDispatcher()

    @Before
    fun setUp() {
        Dispatchers.setMain(mainDispatcher)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun newViewModel() = CatalogueViewModel(FakeBingwaRepositoryImpl(), clock = fixedClock)

    @Test
    fun `home state exposes popular offers and a highest-weight promotion first`() = runTest(mainDispatcher) {
        val vm = newViewModel()
        backgroundScope.launch { vm.homeUiState.collect {} }
        runCurrent()

        val state = vm.homeUiState.value
        assertTrue(state.sections.popular.isNotEmpty())
        assertTrue(state.promotions.isNotEmpty())
        assertEquals(
            state.promotions.maxOf { it.priorityWeight },
            state.promotions.first().priorityWeight
        )
    }

    @Test
    fun `toggleFavourite adds then removes the offer from favourites`() = runTest(mainDispatcher) {
        val vm = newViewModel()
        backgroundScope.launch { vm.homeUiState.collect {} }
        runCurrent()

        val offer = vm.homeUiState.value.sections.popular.first { !it.isFavourite }

        vm.toggleFavourite(offer)
        runCurrent()
        assertTrue(vm.homeUiState.value.sections.favourites.any { it.id == offer.id })

        val nowFavourite = vm.homeUiState.value.sections.favourites.first { it.id == offer.id }
        vm.toggleFavourite(nowFavourite)
        runCurrent()
        assertTrue(vm.homeUiState.value.sections.favourites.none { it.id == offer.id })
    }

    @Test
    fun `search and category narrow results then clearFilters restores everything`() = runTest(mainDispatcher) {
        val vm = newViewModel()
        backgroundScope.launch { vm.offersUiState.collect {} }
        runCurrent()

        val total = vm.offersUiState.value.totalOffers
        assertTrue(total > 0)

        vm.setSearchQuery("SMS")
        runCurrent()
        vm.setCategory(OfferCategory.SMS)
        runCurrent()

        val results = vm.offersUiState.value.results
        assertTrue(results.isNotEmpty())
        assertTrue(results.all { it.category == OfferCategory.SMS })

        vm.clearFilters()
        runCurrent()
        assertEquals(total, vm.offersUiState.value.results.size)
    }

    @Test
    fun `a search that matches nothing sets emptyFromFilters`() = runTest(mainDispatcher) {
        val vm = newViewModel()
        backgroundScope.launch { vm.offersUiState.collect {} }
        runCurrent()

        vm.setSearchQuery("zzzzz")
        runCurrent()

        val state = vm.offersUiState.value
        assertTrue(state.results.isEmpty())
        assertTrue(state.emptyFromFilters)
    }
}
