package com.example.core.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import com.example.core.model.DailyRule
import com.example.core.model.DailyStateKind
import com.example.core.model.OfferCategory
import com.example.core.model.OfferDailyState
import com.example.core.model.OfferItem
import com.example.ui.theme.MyBingwaTheme
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

/**
 * Compose behaviour for [OfferCard]. Robolectric is pinned to SDK 34 because
 * Robolectric 4.16.1 ships no SDK 36 sandbox.
 */
@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34])
class OfferCardComposeTest {

    @get:Rule
    val composeRule = createComposeRule()

    private fun offer(id: String = "off_x", favourite: Boolean = false): OfferItem = OfferItem(
        id = id,
        name = "1 GB Hourly",
        allowance = "1 GB",
        priceKsh = 19,
        validity = "1 hour",
        category = OfferCategory.DATA,
        dailyRule = DailyRule.BUY_AGAIN_TODAY,
        isFavourite = favourite
    )

    @Test
    fun `card has no compact buy button`() {
        composeRule.setContent {
            MyBingwaTheme {
                OfferCard(offer = offer(), onCardClick = {}, onFavouriteToggle = {})
            }
        }
        composeRule.onNodeWithTag("buy_button_off_x").assertDoesNotExist()
    }

    @Test
    fun `favourite button exists and click invokes callback`() {
        var toggled = false
        composeRule.setContent {
            MyBingwaTheme {
                OfferCard(offer = offer(), onCardClick = {}, onFavouriteToggle = { toggled = true })
            }
        }
        composeRule.onNodeWithTag("favourite_button_off_x").assertExists()
        composeRule.onNodeWithTag("favourite_button_off_x").performClick()
        assertTrue(toggled)
    }

    @Test
    fun `card exists and click invokes onCardClick`() {
        var clicked = false
        composeRule.setContent {
            MyBingwaTheme {
                OfferCard(offer = offer(), onCardClick = { clicked = true }, onFavouriteToggle = {})
            }
        }
        composeRule.onNodeWithTag("offer_card_off_x").assertExists()
        composeRule.onNodeWithTag("offer_card_off_x").performClick()
        assertTrue(clicked)
    }

    @Test
    fun `bought today daily state renders its label`() {
        composeRule.setContent {
            MyBingwaTheme {
                OfferCard(
                    offer = offer(),
                    dailyState = OfferDailyState(DailyStateKind.BOUGHT_TODAY, "Bought today"),
                    onCardClick = {},
                    onFavouriteToggle = {}
                )
            }
        }
        composeRule.onNodeWithText("Bought today", useUnmergedTree = true).assertIsDisplayed()
    }
}
