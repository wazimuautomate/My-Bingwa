package com.example.feature.home

import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.performClick
import com.example.core.model.Promotion
import com.example.core.model.PromotionAccent
import com.example.core.model.PromotionKind
import com.example.ui.theme.MyBingwaTheme
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

/**
 * Compose behaviour for [PromotionBillboard]. `reducedMotion = true` suppresses
 * the infinite CTA breathing animation so the tests never wait on animation.
 */
@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34])
class PromotionBillboardComposeTest {

    @get:Rule
    val composeRule = createComposeRule()

    private fun promo(id: String): Promotion = Promotion(
        id = id,
        kind = PromotionKind.OFFER,
        tag = "HOT DEAL",
        headline = "Headline $id",
        subhead = "Subhead $id",
        ctaLabel = "Buy now",
        accent = PromotionAccent.GREEN
    )

    @Test
    fun `empty promotions render no billboard`() {
        composeRule.setContent {
            MyBingwaTheme {
                PromotionBillboard(promotions = emptyList(), reducedMotion = true, onPromotionAction = {})
            }
        }
        composeRule.onNodeWithTag("promotion_billboard").assertDoesNotExist()
    }

    @Test
    fun `non-empty billboard shows first slide and cta invokes callback`() {
        val first = promo("p1")
        var actioned: Promotion? = null
        composeRule.setContent {
            MyBingwaTheme {
                PromotionBillboard(
                    promotions = listOf(first, promo("p2")),
                    reducedMotion = true,
                    onPromotionAction = { actioned = it }
                )
            }
        }
        composeRule.onNodeWithTag("promotion_billboard").assertExists()
        composeRule.onNodeWithTag("promotion_slide_p1", useUnmergedTree = true).assertExists()
        composeRule.onNodeWithTag("promotion_cta_p1", useUnmergedTree = true).performClick()
        assertEquals(first, actioned)
    }
}
