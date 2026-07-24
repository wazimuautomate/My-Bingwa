package com.example.core.model

import androidx.compose.ui.graphics.Color
import com.example.ui.theme.DataCategoryBlue
import com.example.ui.theme.DataCategoryContainer
import com.example.ui.theme.LightPrimaryText
import com.example.ui.theme.MinutesCategoryContainer
import com.example.ui.theme.MinutesCategoryGreen
import com.example.ui.theme.SmsCategoryContainer
import com.example.ui.theme.SmsCategoryDarkContent
import com.example.ui.theme.SmsCategoryPurple
import com.example.ui.theme.SpecialCategoryContainer
import com.example.ui.theme.SpecialCategoryDarkContent
import com.example.ui.theme.SpecialCategoryOrange

enum class OfferCategory(
    val label: String,
    val iconName: String,
    val accentColor: Color,
    val containerColor: Color,
    val contentColor: Color
) {
    ALL("All offers", "grid_view", DataCategoryBlue, DataCategoryContainer, LightPrimaryText),
    DATA("Data", "signal_cellular_alt", DataCategoryBlue, DataCategoryContainer, LightPrimaryText),
    SMS("SMS", "chat_bubble_outline", SmsCategoryPurple, SmsCategoryContainer, SmsCategoryDarkContent),
    MINUTES("Minutes", "call", MinutesCategoryGreen, MinutesCategoryContainer, LightPrimaryText),
    SPECIAL("Special", "auto_awesome", SpecialCategoryOrange, SpecialCategoryContainer, SpecialCategoryDarkContent),
    FAVOURITES("Favourites", "favorite", SpecialCategoryOrange, SpecialCategoryContainer, LightPrimaryText)
}
