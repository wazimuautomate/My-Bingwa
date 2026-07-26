package com.example.data.catalogue

import com.example.core.model.PromotionAccent
import com.example.core.model.PromotionKind
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.time.Instant

/**
 * Verifies the deterministic, design-safe server→[com.example.core.model.Promotion]
 * mapping in [BillboardDto.toPromotion]. Runs on the host JVM, so [Instant] is used only
 * to compute expected epoch millis independently of the SimpleDateFormat under test.
 */
class BillboardMappingTest {

    private fun dto(
        id: Long? = 1L,
        kind: String? = "offer",
        priority: Int? = 0,
        linkedOfferId: String? = null,
        headline: String? = "Headline",
        body: String? = "Body",
        startsAt: String? = null,
        endsAt: String? = null
    ) = BillboardDto(
        id = id, kind = kind, priority = priority, linkedOfferId = linkedOfferId,
        tag = "TAG", headline = headline, body = body, ctaLabel = "Buy now",
        startsAt = startsAt, endsAt = endsAt
    )

    @Test
    fun mapsCoreFields() {
        val p = dto(id = 42L, headline = "8GB", body = "Mega bundle").toPromotion()!!
        assertEquals("42", p.id)
        assertEquals("8GB", p.headline)
        assertEquals("Mega bundle", p.subhead)
        assertEquals("Buy now", p.ctaLabel)
        assertEquals("TAG", p.tag)
        assertNull(p.imageRes)
        assertNull(p.linkedCategory)
    }

    @Test
    fun kindDeterminesAccent_neverOrange() {
        assertEquals(PromotionAccent.GREEN, dto(kind = "offer").toPromotion()!!.accent)
        assertEquals(PromotionAccent.BLUE, dto(kind = "announcement").toPromotion()!!.accent)
        assertEquals(PromotionAccent.NAVY, dto(kind = "update").toPromotion()!!.accent)
        // Case-insensitive on the server string.
        assertEquals(PromotionKind.UPDATE, dto(kind = "UPDATE").toPromotion()!!.kind)
        // Unknown kind → ANNOUNCEMENT/BLUE, never ORANGE.
        val unknown = dto(kind = "flashsale").toPromotion()!!
        assertEquals(PromotionKind.ANNOUNCEMENT, unknown.kind)
        assertEquals(PromotionAccent.BLUE, unknown.accent)
    }

    @Test
    fun priorityIsNegatedSoLowerPrioritySortsFirst() {
        assertEquals(0, dto(priority = 0).toPromotion()!!.priorityWeight)
        assertEquals(-5, dto(priority = 5).toPromotion()!!.priorityWeight)
        assertEquals(0, dto(priority = null).toPromotion()!!.priorityWeight)
    }

    @Test
    fun blankLinkedOfferIdBecomesNull() {
        assertNull(dto(linkedOfferId = "").toPromotion()!!.linkedOfferId)
        assertNull(dto(linkedOfferId = null).toPromotion()!!.linkedOfferId)
        assertEquals("data_1", dto(linkedOfferId = "data_1").toPromotion()!!.linkedOfferId)
    }

    @Test
    fun missingIdOrHeadlineIsSkipped() {
        assertNull(dto(id = null).toPromotion())
        assertNull(dto(headline = "").toPromotion())
        assertNull(dto(headline = null).toPromotion())
    }

    @Test
    fun parsesIsoWindow_andDefaultsWhenAbsent() {
        val p = dto(
            startsAt = "2026-07-26T00:00:00Z",
            endsAt = "2026-07-27T00:00:00Z"
        ).toPromotion()!!
        assertEquals(Instant.parse("2026-07-26T00:00:00Z").toEpochMilli(), p.startMillis)
        assertEquals(Instant.parse("2026-07-27T00:00:00Z").toEpochMilli(), p.endMillis)

        // Null/blank/garbage → sensible always-active defaults.
        assertEquals(0L, dto(startsAt = null).toPromotion()!!.startMillis)
        assertEquals(Long.MAX_VALUE, dto(endsAt = null).toPromotion()!!.endMillis)
        assertEquals(0L, dto(startsAt = "  ").toPromotion()!!.startMillis)
        assertEquals(0L, dto(startsAt = "not-a-date").toPromotion()!!.startMillis)
    }
}
