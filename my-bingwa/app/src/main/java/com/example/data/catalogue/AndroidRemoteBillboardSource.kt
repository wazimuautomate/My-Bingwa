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

    override suspend fun fetch(): List<Promotion>? {
        val response = try {
            api?.getBillboards() ?: return null
        } catch (t: Throwable) {
            return null
        }
        val mapped = response.billboards.mapNotNull { it.toPromotion() }
        return mapped.ifEmpty { null }
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
     * core visible content is missing (no id or blank headline). The accent is derived
     * from the kind — never from the server — so a billboard can never paint
     * white-on-orange (design.md §7 reserves orange for real discounts).
     */
    fun toPromotion(): Promotion? {
        val safeId = id?.toString()?.takeIf { it.isNotBlank() } ?: return null
        val safeHeadline = headline?.takeIf { it.isNotBlank() } ?: return null
        val promoKind = when ((kind ?: "").trim().lowercase()) {
            "offer" -> PromotionKind.OFFER
            "announcement" -> PromotionKind.ANNOUNCEMENT
            "update" -> PromotionKind.UPDATE
            else -> PromotionKind.ANNOUNCEMENT
        }
        val promoAccent = when (promoKind) {
            PromotionKind.OFFER -> PromotionAccent.GREEN
            PromotionKind.ANNOUNCEMENT -> PromotionAccent.BLUE
            PromotionKind.UPDATE -> PromotionAccent.NAVY
        }
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
