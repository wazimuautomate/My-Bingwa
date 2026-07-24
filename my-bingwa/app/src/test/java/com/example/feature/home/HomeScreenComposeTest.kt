package com.example.feature.home

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertTextContains
import androidx.compose.ui.test.hasTestTag
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollToNode
import com.example.core.model.DailyRule
import com.example.core.model.OfferCategory
import com.example.core.model.OfferItem
import com.example.core.model.Promotion
import com.example.core.model.PromotionAccent
import com.example.core.model.PromotionKind
import com.example.ui.theme.MyBingwaTheme
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

/**
 * Compose behaviour for [HomeScreen] driven by a hand-built [HomeUiState].
 * `reducedMotion = true` keeps the billboard CTA static.
 */
@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34])
class HomeScreenComposeTest {

    @get:Rule
    val composeRule = createComposeRule()

    private fun offer(id: String): OfferItem = OfferItem(
        id = id,
        name = "Offer $id",
        allowance = "1 GB",
        priceKsh = 50,
        validity = "24 hours",
        category = OfferCategory.DATA,
        dailyRule = DailyRule.BUY_AGAIN_TODAY,
        isPopular = true
    )

    private val promotion = Promotion(
        id = "p1",
        kind = PromotionKind.OFFER,
        tag = "HOT DEAL",
        headline = "Headline",
        subhead = "Subhead",
        ctaLabel = "Buy now",
        accent = PromotionAccent.GREEN
    )

    private fun state(): HomeUiState = HomeUiState(
        loading = false,
        greetingName = "Bonke",
        promotions = listOf(promotion),
        sections = HomeSections(
            popular = listOf(offer("off_1"), offer("off_2")),
            boughtToday = emptyList(),
            moreOffers = emptyList(),
            buyAgain = emptyList(),
            favourites = emptyList(),
            suggestions = emptyList()
        ),
        nowMillis = 1_704_877_200_000L
    )

    private fun setHome(onSearchClick: () -> Unit = {}) {
        composeRule.setContent {
            MyBingwaTheme {
                HomeScreen(
                    state = state(),
                    unreadNotifCount = 0,
                    reducedMotion = true,
                    onCategoryClick = {},
                    onOfferSelect = {},
                    onFavouriteToggle = {},
                    onUndoFavourite = {},
                    onPromotionAction = {},
                    onNotifClick = {},
                    onProfileClick = {},
                    onSearchClick = onSearchClick
                )
            }
        }
    }

    @Test
    fun `greeting shows the customer name`() {
        setHome()
        composeRule.onNodeWithTag("home_greeting_text").assertIsDisplayed()
        composeRule.onNodeWithTag("home_greeting_text").assertTextContains("Bonke", substring = true)
    }

    @Test
    fun `category shortcut and search entry are present`() {
        setHome()
        composeRule.onNodeWithTag("category_tile_data").assertExists()
        composeRule.onNodeWithTag("home_search_entry").assertExists()
    }

    @Test
    fun `tapping search entry invokes onSearchClick`() {
        var searched = false
        setHome(onSearchClick = { searched = true })
        composeRule.onNodeWithTag("home_search_entry").performClick()
        assertTrue(searched)
    }

    @Test
    fun `popular offers section header renders`() {
        setHome()
        composeRule.onNodeWithTag("home_scroll")
            .performScrollToNode(hasTestTag("section_header_Popular offers"))
        composeRule.onNodeWithTag("section_header_Popular offers").assertExists()
    }
}
