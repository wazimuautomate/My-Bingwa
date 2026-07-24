package com.example.core.model

import androidx.annotation.DrawableRes

/**
 * What a promotion slide is about. Drives the tag copy and the default action:
 *
 * - [OFFER]        promotes a specific buyable offer ([Promotion.linkedOfferId]).
 * - [ANNOUNCEMENT] promotes a filtered offer list or a general message
 *                  ([Promotion.linkedCategory]); the action browses offers.
 * - [UPDATE]       app news / service notice; the action is informational only.
 */
enum class PromotionKind { OFFER, ANNOUNCEMENT, UPDATE }

/**
 * A billboard slide's solid brand palette. design.md forbids gradients, so each
 * slide paints a single saturated brand colour. Palettes are chosen so the
 * content colour never becomes white-on-bright-green / sky-blue / orange
 * (design.md §7.4): the orange slide uses navy content, the rest use white.
 * These media colours are intentionally constant across light/dark — a
 * billboard is its own vivid surface, like a TV — while staying contrast-safe.
 */
enum class PromotionAccent { GREEN, NAVY, BLUE, ORANGE }

/**
 * A single promotion / announcement shown on the Home billboard.
 *
 * The billboard is the app's advert surface: it rotates the seller's biggest
 * offers (weekly / monthly / high-value) plus occasional announcements and app
 * updates. In version 1 the pool is provided by the (fake) repository; Phase 6/7
 * syncs it from the backend into Room. [imageRes] carries an optional bundled
 * drawable; remote images and animated media need an image-loading library and
 * are deferred (see memory.md).
 */
data class Promotion(
    val id: String,
    val kind: PromotionKind,
    val tag: String,
    val headline: String,
    val subhead: String,
    val ctaLabel: String,
    val accent: PromotionAccent,
    val linkedOfferId: String? = null,
    val linkedCategory: OfferCategory? = null,
    @DrawableRes val imageRes: Int? = null,
    val priorityWeight: Int = 0,
    val startMillis: Long = 0L,
    val endMillis: Long = Long.MAX_VALUE
) {
    fun isActive(nowMillis: Long): Boolean = nowMillis in startMillis..endMillis
}
