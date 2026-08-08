package com.example.data.catalogue

import com.example.core.model.Promotion
import com.example.core.model.PromotionAccent
import com.example.core.model.PromotionKind
import com.squareup.moshi.Json
import com.squareup.moshi.Moshi
import com.squareup.moshi.kotlin.reflect.KotlinJsonAdapterFactory
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.moshi.MoshiConverterFactory
import retrofit2.http.GET
import java.text.SimpleDateFormat
import java.util.Locale
import java.util.TimeZone
import java.util.concurrent.TimeUnit

/**
 * Retrofit implementation of [RemoteBillboardSource] backed by `get_billboards.php`.
 * Maps the admin's published billboards into [Promotion]. Any failure returns null so
 * the caller keeps the local billboards. No secrets — only the non-secret base URL +
 * app-key header. Remote images are deferred (no image-loading library), so a slide
 * renders as a coloured text slide exactly like the seeded promotions.
 */
class AndroidRemoteBillboardSource(
    baseUrl: String,
    appKey: String,
    enableLogging: Boolean = false
) : RemoteBillboardSource {

    private val api: BillboardsApi? = if (baseUrl.startsWith("https://")) {
        val client = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .addInterceptor(Interceptor { chain ->
                chain.proceed(chain.request().newBuilder().header("X-App-Key", appKey).build())
            })
            .build()
        val moshi = Moshi.Builder().add(KotlinJsonAdapterFactory()).build()
        Retrofit.Builder()
            .baseUrl(if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/")
            .client(client)
            .addConverterFactory(MoshiConverterFactory.create(moshi))
            .build()
            .create(BillboardsApi::class.java)
    } else {
        null
    }

    /**
     * Null means "could not ask" (no client, transport failure) and the caller keeps
     * what it already has. An EMPTY list means the owner really has published no
     * billboards, and the caller clears the board — no billboard is hardcoded, so
     * removing them all in the admin must remove them on the device too.
     *
     * The published index is passed into the mapping so consecutive slides get
     * different colours (see [BillboardDto.toPromotion]).
     */
    override suspend fun fetch(): List<Promotion>? {
        val response = try {
            api?.getBillboards() ?: return null
        } catch (t: Throwable) {
            return null
        }
        return response.billboards.mapIndexedNotNull { index, dto -> dto.toPromotion(index) }
    }
}

interface BillboardsApi {
    @GET("get_billboards.php")
    suspend fun getBillboards(): BillboardsResponseDto
}

data class BillboardsResponseDto(
    @Json(name = "billboards") val billboards: List<BillboardDto> = emptyList()
)

data class BillboardDto(
    @Json(name = "id") val id: Long? = null,
    @Json(name = "kind") val kind: String? = null,
    @Json(name = "priority") val priority: Int? = null,
    @Json(name = "linkedOfferId") val linkedOfferId: String? = null,
    @Json(name = "tag") val tag: String? = null,
    @Json(name = "headline") val headline: String? = null,
    @Json(name = "body") val body: String? = null,
    @Json(name = "ctaLabel") val ctaLabel: String? = null,
    @Json(name = "ctaDestination") val ctaDestination: String? = null,
    @Json(name = "imageUrl") val imageUrl: String? = null,
    @Json(name = "altText") val altText: String? = null,
    @Json(name = "audienceRule") val audienceRule: String? = null,
    @Json(name = "frequencyCap") val frequencyCap: Int? = null,
    @Json(name = "startsAt") val startsAt: String? = null,
    @Json(name = "endsAt") val endsAt: String? = null
) {
    /**
     * Map one published billboard row into a [Promotion]. Returns null (skipped) when the
     * core visible content is missing (no id or blank headline).
     *
     * The accent is chosen HERE, never sent by the server, so a billboard can never
     * paint an unreadable combination — every palette in `PromotionBillboard` is
     * contrast-checked, and the server cannot reach past that.
     *
     * It varies by published position rather than by kind alone. Deriving it from the
     * kind made every offer slide green, so a board of three offers was three identical
     * green cards; cycling by [index] gives neighbouring slides different colours while
     * staying deterministic (the same board always paints the same way, no flicker
     * between syncs). Orange stays on offer slides only — design.md §7 reserves it for
     * a real promotion or discount, which is exactly what an offer billboard is.
     */
    fun toPromotion(index: Int): Promotion? {
        val safeId = id?.toString()?.takeIf { it.isNotBlank() } ?: return null
        val safeHeadline = headline?.takeIf { it.isNotBlank() } ?: return null
        val promoKind = when ((kind ?: "").trim().lowercase()) {
            "offer" -> PromotionKind.OFFER
            "announcement" -> PromotionKind.ANNOUNCEMENT
            "update" -> PromotionKind.UPDATE
            else -> PromotionKind.ANNOUNCEMENT
        }
        val palette = when (promoKind) {
            PromotionKind.OFFER -> OFFER_ACCENTS
            PromotionKind.ANNOUNCEMENT -> ANNOUNCEMENT_ACCENTS
            PromotionKind.UPDATE -> UPDATE_ACCENTS
        }
        val promoAccent = palette[Math.floorMod(index, palette.size)]
        return Promotion(
            id = safeId,
            kind = promoKind,
            tag = tag.orEmpty(),
            headline = safeHeadline,
            subhead = body.orEmpty(),
            ctaLabel = ctaLabel.orEmpty(),
            accent = promoAccent,
            linkedOfferId = linkedOfferId?.takeIf { it.isNotBlank() },
            linkedCategory = null,
            imageRes = null,
            // The server orders by priority ASC (lower = shown first); the app's billboard
            // selection (CatalogueLogic.selectPromotions) sorts by priorityWeight DESC, so
            // negate to preserve the published order.
            priorityWeight = -(priority ?: 0),
            startMillis = parseIsoMillis(startsAt, 0L),
            endMillis = parseIsoMillis(endsAt, Long.MAX_VALUE)
        )
    }

    private companion object {
        /**
         * Accent rotations. Four distinct colours for offer slides so a board of offers
         * never repeats until the fifth card; announcements alternate between the two
         * informational colours; app news is always navy so it reads as a system notice
         * rather than a sale.
         */
        private val OFFER_ACCENTS = listOf(
            PromotionAccent.GREEN,
            PromotionAccent.ORANGE,
            PromotionAccent.NAVY,
            PromotionAccent.BLUE,
        )
        private val ANNOUNCEMENT_ACCENTS = listOf(PromotionAccent.BLUE, PromotionAccent.NAVY)
        private val UPDATE_ACCENTS = listOf(PromotionAccent.NAVY)

        /**
         * Parse the admin's UTC ISO-8601 timestamp ("yyyy-MM-dd'T'HH:mm:ss'Z'") to epoch
         * millis. Null/blank/unparseable → [default] (0L for start, Long.MAX_VALUE for
         * end), so a missing window means "always active". SimpleDateFormat is used (not
         * java.time) because minSdk 24 has no core-library desugaring.
         */
        private fun parseIsoMillis(value: String?, default: Long): Long {
            val raw = value?.trim().orEmpty()
            if (raw.isEmpty()) return default
            return try {
                val fmt = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", Locale.US)
                fmt.timeZone = TimeZone.getTimeZone("UTC")
                fmt.isLenient = false
                fmt.parse(raw)?.time ?: default
            } catch (t: Throwable) {
                default
            }
        }
    }
}
