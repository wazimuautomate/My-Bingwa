package com.example.data.catalogue

import com.example.core.model.PromotionAccent
import com.example.core.model.PromotionKind
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotEquals
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
        val p = dto(id = 42L, headline = "8GB", body = "Mega bundle").toPromotion(0)!!
        assertEquals("42", p.id)
        assertEquals("8GB", p.headline)
        assertEquals("Mega bundle", p.subhead)
        assertEquals("Buy now", p.ctaLabel)
        assertEquals("TAG", p.tag)
        assertNull(p.imageRes)
        assertNull(p.linkedCategory)
    }

    @Test
    fun kindAndPositionDetermineAccent() {
        // The FIRST slide of each kind keeps the colour it has always had.
        assertEquals(PromotionAccent.GREEN, dto(kind = "offer").toPromotion(0)!!.accent)
        assertEquals(PromotionAccent.BLUE, dto(kind = "announcement").toPromotion(0)!!.accent)
        assertEquals(PromotionAccent.NAVY, dto(kind = "update").toPromotion(0)!!.accent)
        // Case-insensitive on the server string.
        assertEquals(PromotionKind.UPDATE, dto(kind = "UPDATE").toPromotion(0)!!.kind)
        // Unknown kind → ANNOUNCEMENT, and its accents are the informational pair.
        val unknown = dto(kind = "flashsale").toPromotion(0)!!
        assertEquals(PromotionKind.ANNOUNCEMENT, unknown.kind)
        assertEquals(PromotionAccent.BLUE, unknown.accent)
    }

    /**
     * A board of offers used to paint every card the same green, because the accent came
     * from the kind alone. It now cycles with the published position.
     */
    @Test
    fun consecutiveOfferSlidesGetDifferentAccents() {
        val accents = (0..3).map { dto(kind = "offer").toPromotion(it)!!.accent }
        assertEquals(4, accents.toSet().size)
        // Adjacent slides always differ, including where the cycle wraps.
        val wrapped = (0..4).map { dto(kind = "offer").toPromotion(it)!!.accent }
        wrapped.zipWithNext().forEach { (a, b) -> assertNotEquals(a, b) }
        // Fifth slide restarts the rotation — deterministic, so a re-sync never reshuffles.
        assertEquals(accents[0], dto(kind = "offer").toPromotion(4)!!.accent)
    }

    /** Announcements alternate between the two informational colours; app news stays navy. */
    @Test
    fun announcementsAlternate_andUpdatesStayNavy() {
        assertEquals(PromotionAccent.NAVY, dto(kind = "announcement").toPromotion(1)!!.accent)
        assertEquals(PromotionAccent.BLUE, dto(kind = "announcement").toPromotion(2)!!.accent)
        assertEquals(PromotionAccent.NAVY, dto(kind = "update").toPromotion(3)!!.accent)
    }

    @Test
    fun priorityIsNegatedSoLowerPrioritySortsFirst() {
        assertEquals(0, dto(priority = 0).toPromotion(0)!!.priorityWeight)
        assertEquals(-5, dto(priority = 5).toPromotion(0)!!.priorityWeight)
        assertEquals(0, dto(priority = null).toPromotion(0)!!.priorityWeight)
    }

    @Test
    fun blankLinkedOfferIdBecomesNull() {
        assertNull(dto(linkedOfferId = "").toPromotion(0)!!.linkedOfferId)
        assertNull(dto(linkedOfferId = null).toPromotion(0)!!.linkedOfferId)
        assertEquals("data_1", dto(linkedOfferId = "data_1").toPromotion(0)!!.linkedOfferId)
    }

    @Test
    fun missingIdOrHeadlineIsSkipped() {
        assertNull(dto(id = null).toPromotion(0))
        assertNull(dto(headline = "").toPromotion(0))
        assertNull(dto(headline = null).toPromotion(0))
    }

    @Test
    fun parsesIsoWindow_andDefaultsWhenAbsent() {
        val p = dto(
            startsAt = "2026-07-26T00:00:00Z",
            endsAt = "2026-07-27T00:00:00Z"
        ).toPromotion(0)!!
        assertEquals(Instant.parse("2026-07-26T00:00:00Z").toEpochMilli(), p.startMillis)
        assertEquals(Instant.parse("2026-07-27T00:00:00Z").toEpochMilli(), p.endMillis)

        // Null/blank/garbage → sensible always-active defaults.
        assertEquals(0L, dto(startsAt = null).toPromotion(0)!!.startMillis)
        assertEquals(Long.MAX_VALUE, dto(endsAt = null).toPromotion(0)!!.endMillis)
        assertEquals(0L, dto(startsAt = "  ").toPromotion(0)!!.startMillis)
        assertEquals(0L, dto(startsAt = "not-a-date").toPromotion(0)!!.startMillis)
    }
}
