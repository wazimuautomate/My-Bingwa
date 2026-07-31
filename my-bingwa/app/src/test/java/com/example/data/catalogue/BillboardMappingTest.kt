package com.example.data.catalogue

import com.example.core.model.PromotionAccent
import com.example.core.model.PromotionClickAction
import com.example.core.model.PromotionKind
import com.example.core.model.PromotionMediaType
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
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
        endsAt: String? = null,
        imageUrl: String? = null,
        altText: String? = null,
        ctaDestination: String? = null,
        mediaUrl: String? = null,
        mediaType: String? = null,
        mediaVersion: String? = null,
        clickAction: String? = null,
        clickTarget: String? = null
    ) = BillboardDto(
        id = id, kind = kind, priority = priority, linkedOfferId = linkedOfferId,
        tag = "TAG", headline = headline, body = body, ctaLabel = "Buy now",
        ctaDestination = ctaDestination, imageUrl = imageUrl, altText = altText,
        startsAt = startsAt, endsAt = endsAt,
        mediaUrl = mediaUrl, mediaType = mediaType, mediaVersion = mediaVersion,
        clickAction = clickAction, clickTarget = clickTarget
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

    // --- timestamp formats (Nairobi-local support) ---------------------------

    @Test
    fun parsesNairobiLocalWindow_spaceSeparated() {
        // Africa/Nairobi is UTC+3 all year (no DST): 09:00 local == 06:00 UTC.
        val p = dto(
            startsAt = "2026-07-26 09:00:00",
            endsAt = "2026-07-26 21:30:00"
        ).toPromotion()!!
        assertEquals(Instant.parse("2026-07-26T06:00:00Z").toEpochMilli(), p.startMillis)
        assertEquals(Instant.parse("2026-07-26T18:30:00Z").toEpochMilli(), p.endMillis)
    }

    @Test
    fun parsesNairobiLocalWindow_isoWithoutZone() {
        val p = dto(startsAt = "2026-07-26T09:00:00").toPromotion()!!
        assertEquals(Instant.parse("2026-07-26T06:00:00Z").toEpochMilli(), p.startMillis)
    }

    @Test
    fun parsesDateOnlyAsNairobiMidnight() {
        val p = dto(startsAt = "2026-07-26").toPromotion()!!
        assertEquals(Instant.parse("2026-07-25T21:00:00Z").toEpochMilli(), p.startMillis)
    }

    @Test
    fun utcAndNairobiFormsOfTheSameInstantAgree() {
        val utc = dto(startsAt = "2026-07-26T06:00:00Z").toPromotion()!!.startMillis
        val local = dto(startsAt = "2026-07-26 09:00:00").toPromotion()!!.startMillis
        assertEquals(utc, local)
    }

    @Test
    fun millisecondUtcFormIsAccepted() {
        val p = dto(startsAt = "2026-07-26T06:00:00.000Z").toPromotion()!!
        assertEquals(Instant.parse("2026-07-26T06:00:00Z").toEpochMilli(), p.startMillis)
    }

    // --- media ---------------------------------------------------------------

    @Test
    fun mediaDefaultsToNoneWhenNothingIsPublished() {
        val p = dto().toPromotion()!!
        assertEquals("", p.mediaUrl)
        assertEquals(PromotionMediaType.NONE, p.mediaTypeOrNone)
        assertEquals("", p.mediaVersion)
        assertEquals("", p.mediaAltText)
        assertFalse(p.hasRemoteMedia)
    }

    @Test
    fun legacyImageUrlBecomesImageMedia() {
        val p = dto(imageUrl = "https://cdn.example.com/a.png", altText = "Weekend deal").toPromotion()!!
        assertEquals("https://cdn.example.com/a.png", p.mediaUrl)
        assertEquals(PromotionMediaType.IMAGE, p.mediaTypeOrNone)
        assertEquals("Weekend deal", p.mediaAltText)
        assertTrue(p.hasRemoteMedia)
    }

    @Test
    fun gifIsDetectedFromTypeOrExtension() {
        assertEquals(
            PromotionMediaType.GIF,
            dto(mediaUrl = "https://cdn.example.com/a.png", mediaType = "gif").toPromotion()!!.mediaTypeOrNone
        )
        assertEquals(
            PromotionMediaType.GIF,
            dto(mediaUrl = "https://cdn.example.com/a.GIF?v=2").toPromotion()!!.mediaTypeOrNone
        )
    }

    @Test
    fun unknownMediaTypeFallsBackToImageRatherThanLosingTheArtwork() {
        val p = dto(mediaUrl = "https://cdn.example.com/a.webp", mediaType = "lottie").toPromotion()!!
        assertEquals(PromotionMediaType.IMAGE, p.mediaTypeOrNone)
    }

    @Test
    fun explicitNoneOrNonHttpMediaIsDropped() {
        assertEquals(
            PromotionMediaType.NONE,
            dto(mediaUrl = "https://cdn.example.com/a.png", mediaType = "none").toPromotion()!!.mediaTypeOrNone
        )
        val unsafe = dto(mediaUrl = "file:///sdcard/a.png").toPromotion()!!
        assertEquals(PromotionMediaType.NONE, unsafe.mediaTypeOrNone)
        assertEquals("", unsafe.mediaUrl)
    }

    @Test
    fun mediaVersionIsCarriedForCacheBusting() {
        val p = dto(mediaUrl = "https://cdn.example.com/a.png", mediaVersion = " v7 ").toPromotion()!!
        assertEquals("v7", p.mediaVersion)
    }

    // --- click action --------------------------------------------------------

    @Test
    fun clickActionIsParsedAndUnknownDegradesToNone() {
        assertEquals(
            PromotionClickAction.CATEGORY,
            dto(clickAction = "category", clickTarget = "SMS").toPromotion()!!.clickActionOrNone
        )
        assertEquals(
            PromotionClickAction.INTERNAL_ROUTE,
            dto(clickAction = "internal_route", clickTarget = "offers").toPromotion()!!.clickActionOrNone
        )
        // Unknown token → NONE (the slide keeps its legacy kind-based behaviour).
        assertEquals(
            PromotionClickAction.NONE,
            dto(clickAction = "teleport", clickTarget = "somewhere").toPromotion()!!.clickActionOrNone
        )
        // A declared action with nowhere to go → NONE.
        assertEquals(
            PromotionClickAction.NONE,
            dto(clickAction = "category", clickTarget = "").toPromotion()!!.clickActionOrNone
        )
    }

    @Test
    fun externalLinkOnlyEverPointsAtAWebUrl() {
        val web = dto(clickAction = "external_link", clickTarget = "https://bingwa.example/promo").toPromotion()!!
        assertEquals(PromotionClickAction.EXTERNAL_LINK, web.clickActionOrNone)
        assertEquals("https://bingwa.example/promo", web.clickTarget)

        // A non-web scheme can never become an external link.
        val unsafe = dto(clickAction = "external_link", clickTarget = "intent://evil").toPromotion()!!
        assertEquals(PromotionClickAction.NONE, unsafe.clickActionOrNone)
        assertEquals("", unsafe.clickTarget)
    }

    @Test
    fun offerSlideWithALinkedOfferGetsAnOfferAction() {
        val p = dto(kind = "offer", linkedOfferId = "data_1").toPromotion()!!
        assertEquals(PromotionClickAction.OFFER, p.clickActionOrNone)
        assertEquals("data_1", p.clickTarget)
        // No linked offer and no target → no action at all.
        assertEquals(PromotionClickAction.NONE, dto(kind = "offer").toPromotion()!!.clickActionOrNone)
    }
}
