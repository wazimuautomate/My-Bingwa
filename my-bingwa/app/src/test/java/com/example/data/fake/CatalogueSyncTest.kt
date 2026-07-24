package com.example.data.fake

import com.example.core.model.DailyRule
import com.example.core.model.OfferCategory
import com.example.core.model.OfferItem
import com.example.data.catalogue.RemoteCatalogueSource
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class CatalogueSyncTest {

    private class FakeCatalogue(private val list: List<OfferItem>?) : RemoteCatalogueSource {
        override suspend fun fetch(): List<OfferItem>? = list
    }

    private fun offer(id: String, price: Int) = OfferItem(
        id = id, name = "$price bob", allowance = "$price bob", priceKsh = price,
        validity = "24 Hrs", validityBand = "Daily", category = OfferCategory.DATA,
        dailyRule = DailyRule.BUY_AGAIN_TODAY
    )

    @Test
    fun sync_replacesOffersWithServerList() = runTest {
        val repo = FakeBingwaRepositoryImpl(catalogueSource = FakeCatalogue(listOf(offer("srv_1", 42))))
        repo.syncCatalogue()
        assertEquals(1, repo.offers.value.size)
        assertEquals("srv_1", repo.offers.value.first().id)
    }

    @Test
    fun sync_nullKeepsLocalCatalogue() = runTest {
        val repo = FakeBingwaRepositoryImpl(catalogueSource = FakeCatalogue(null))
        val before = repo.offers.value.size
        assertTrue(before > 0)
        repo.syncCatalogue()
        assertEquals(before, repo.offers.value.size)
    }

    @Test
    fun sync_preservesFavourite() = runTest {
        val existingId = FakeBingwaRepositoryImpl().offers.value.first().id
        val repo = FakeBingwaRepositoryImpl(catalogueSource = FakeCatalogue(listOf(offer(existingId, 42))))
        repo.setFavourite(existingId, true)
        repo.syncCatalogue()
        assertTrue(repo.offers.value.first { it.id == existingId }.isFavourite)
    }
}
