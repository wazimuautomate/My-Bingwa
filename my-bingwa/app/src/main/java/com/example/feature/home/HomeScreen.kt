package com.example.feature.home

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AutoAwesome
import androidx.compose.material.icons.outlined.Call
import androidx.compose.material.icons.outlined.ChatBubbleOutline
import androidx.compose.material.icons.outlined.SignalCellularAlt
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.SnackbarResult
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.example.core.model.OfferCategory
import com.example.core.model.OfferItem
import com.example.core.model.Promotion
import com.example.core.ui.MyBingwaTopAppBar
import com.example.core.ui.OfferCard
import com.example.core.ui.OfferCardSkeleton
import com.example.core.ui.OfflineStatusStrip
import com.example.ui.theme.CardShape
import com.example.ui.theme.categoryColors
import kotlinx.coroutines.launch
import java.util.Calendar

/**
 * Home — deliberately simple. After the promotion billboard the page shows only
 * two things: **Your favourites** (a vertical list) and **You may also like**
 * (a horizontally swipeable row of similar offers). There is no search bar and
 * no Popular / Bought today / Buy again sections.
 */
@Composable
fun HomeScreen(
    state: HomeUiState,
    unreadNotifCount: Int,
    reducedMotion: Boolean = false,
    listState: LazyListState = rememberLazyListState(),
    onCategoryClick: (OfferCategory) -> Unit,
    onOfferSelect: (OfferItem) -> Unit,
    onOfferBuy: (OfferItem) -> Unit,
    onFavouriteToggle: (OfferItem) -> Unit,
    onUndoFavourite: (String) -> Unit,
    onPromotionAction: (Promotion) -> Unit,
    onNotifClick: () -> Unit,
    onOfflineClick: () -> Unit
) {
    val snackbarHostState = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()

    val greeting = remember {
        when (Calendar.getInstance().get(Calendar.HOUR_OF_DAY)) {
            in 0..11 -> "Good morning"
            in 12..16 -> "Good afternoon"
            else -> "Good evening"
        }
    }

    // Toggle favourite; if it was a removal, offer a snackbar Undo.
    val favouriteToggle: (OfferItem) -> Unit = { offer ->
        val wasFavourite = offer.isFavourite
        onFavouriteToggle(offer)
        if (wasFavourite) {
            scope.launch {
                val result = snackbarHostState.showSnackbar(
                    message = "Removed from favourites",
                    actionLabel = "Undo",
                    duration = androidx.compose.material3.SnackbarDuration.Short
                )
                if (result == SnackbarResult.ActionPerformed) onUndoFavourite(offer.id)
            }
        }
    }

    Box(modifier = Modifier.fillMaxSize()) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .background(MaterialTheme.colorScheme.background)
        ) {
            MyBingwaTopAppBar(
                userName = state.greetingName,
                unreadNotifCount = unreadNotifCount,
                isOffline = state.isOffline,
                onNotifClick = onNotifClick,
                onOfflineClick = onOfflineClick
            )

            LazyColumn(
                state = listState,
                modifier = Modifier
                    .fillMaxSize()
                    .testTag("home_scroll"),
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                item(key = "greeting") {
                    Text(
                        text = "$greeting, ${state.greetingName.ifEmpty { "Customer" }}",
                        style = MaterialTheme.typography.headlineMedium,
                        color = MaterialTheme.colorScheme.onBackground,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier
                            .padding(top = 8.dp, bottom = 4.dp)
                            .testTag("home_greeting_text")
                    )
                }

                item(key = "categories") {
                    CategoryShortcutRow(onCategoryClick = onCategoryClick)
                }

                if (state.isOffline) {
                    item(key = "offline_strip") {
                        OfflineStatusStrip(onDetailsClick = onOfflineClick)
                    }
                }

                if (state.loading) {
                    items(3, key = { "skeleton_$it" }) { OfferCardSkeleton() }
                    return@LazyColumn
                }

                // One promotion billboard.
                if (state.promotions.isNotEmpty()) {
                    item(key = "billboard") {
                        PromotionBillboard(
                            promotions = state.promotions,
                            reducedMotion = reducedMotion,
                            onPromotionAction = onPromotionAction
                        )
                    }
                }

                // Your favourites — vertical list.
                item(key = "favourites_header") {
                    SectionHeader("Your favourites")
                }
                if (state.sections.favourites.isEmpty()) {
                    item(key = "favourites_empty") { FavouritesEmpty() }
                } else {
                    items(state.sections.favourites, key = { "fav_${it.id}" }) { offer ->
                        OfferCard(
                            offer = offer,
                            isOffline = state.isOffline,
                            onCardClick = { onOfferSelect(offer) },
                            onBuyClick = { onOfferBuy(offer) },
                            onFavouriteToggle = { favouriteToggle(offer) }
                        )
                    }
                }

                // You may also like — horizontally swipeable row.
                if (state.sections.suggestions.isNotEmpty()) {
                    item(key = "suggestions_header") {
                        SectionHeader("You may also like")
                    }
                    item(key = "suggestions_row") {
                        LazyRow(
                            horizontalArrangement = Arrangement.spacedBy(12.dp),
                            contentPadding = PaddingValues(vertical = 2.dp),
                            modifier = Modifier.testTag("suggestions_row")
                        ) {
                            items(state.sections.suggestions, key = { "sug_${it.id}" }) { offer ->
                                Box(modifier = Modifier.width(300.dp)) {
                                    OfferCard(
                                        offer = offer,
                                        isOffline = state.isOffline,
                                        onCardClick = { onOfferSelect(offer) },
                                        onBuyClick = { onOfferBuy(offer) },
                                        onFavouriteToggle = { favouriteToggle(offer) }
                                    )
                                }
                            }
                        }
                    }
                }

                item(key = "footer_spacer") { Spacer(Modifier.height(24.dp)) }
            }
        }

        SnackbarHost(
            hostState = snackbarHostState,
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .padding(16.dp)
        )
    }
}

@Composable
private fun FavouritesEmpty() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
        ),
        shape = CardShape
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = "No favourite offers saved yet",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Text(
                text = "Tap the heart icon on any offer to add it here",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
            )
        }
    }
}

@Composable
private fun CategoryShortcutRow(onCategoryClick: (OfferCategory) -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        CategoryShortcutTile("Data", Icons.Outlined.SignalCellularAlt, OfferCategory.DATA) { onCategoryClick(OfferCategory.DATA) }
        CategoryShortcutTile("Minutes", Icons.Outlined.Call, OfferCategory.MINUTES) { onCategoryClick(OfferCategory.MINUTES) }
        CategoryShortcutTile("SMS", Icons.Outlined.ChatBubbleOutline, OfferCategory.SMS) { onCategoryClick(OfferCategory.SMS) }
        CategoryShortcutTile("Special", Icons.Outlined.AutoAwesome, OfferCategory.SPECIAL) { onCategoryClick(OfferCategory.SPECIAL) }
    }
}

@Composable
private fun CategoryShortcutTile(
    label: String,
    icon: ImageVector,
    category: OfferCategory,
    onClick: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier
            .clip(CardShape)
            .clickable { onClick() }
            .padding(8.dp)
            .testTag("category_tile_${category.name.lowercase()}")
    ) {
        val colors = categoryColors(category)
        Box(
            modifier = Modifier
                .size(56.dp)
                .clip(CircleShape)
                .background(colors.container),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = colors.accent,
                modifier = Modifier.size(26.dp)
            )
        }
        Spacer(Modifier.height(6.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )
    }
}

@Composable
private fun SectionHeader(title: String) {
    Text(
        text = title,
        style = MaterialTheme.typography.titleLarge,
        color = MaterialTheme.colorScheme.onBackground,
        fontWeight = FontWeight.Bold,
        modifier = Modifier
            .fillMaxWidth()
            .testTag("section_header_$title")
    )
}
