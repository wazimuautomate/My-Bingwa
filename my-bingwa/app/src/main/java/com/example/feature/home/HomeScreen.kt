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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AutoAwesome
import androidx.compose.material.icons.outlined.Call
import androidx.compose.material.icons.outlined.ChatBubbleOutline
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.SignalCellularAlt
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.SnackbarResult
import androidx.compose.material3.Surface
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
import com.example.ui.theme.FieldButtonShape
import com.example.ui.theme.categoryColors
import kotlinx.coroutines.launch
import java.util.Calendar

/**
 * Home (Plan.md §5.2 / design.md §14.3). Renders the cached catalogue from
 * [HomeUiState] with the ordered sections: greeting, search, category
 * shortcuts, one restrained promotion billboard, Popular, Bought today, More
 * offers, Buy again, Favourites and a restrained "You might also like".
 *
 * The screen keeps honest language, never claims delivery, and preserves list
 * position across configuration changes via [listState].
 */
@Composable
fun HomeScreen(
    state: HomeUiState,
    unreadNotifCount: Int,
    reducedMotion: Boolean = false,
    listState: LazyListState = rememberLazyListState(),
    onCategoryClick: (OfferCategory) -> Unit,
    onOfferSelect: (OfferItem) -> Unit,
    onFavouriteToggle: (OfferItem) -> Unit,
    onUndoFavourite: (String) -> Unit,
    onPromotionAction: (Promotion) -> Unit,
    onNotifClick: () -> Unit,
    onProfileClick: () -> Unit,
    onSearchClick: () -> Unit
) {
    val snackbarHostState = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()

    // Greeting is personalised once (design.md §14.3): compute the time band here,
    // the name comes from the profile.
    val greeting = remember {
        when (Calendar.getInstance().get(Calendar.HOUR_OF_DAY)) {
            in 0..11 -> "Good morning"
            in 12..16 -> "Good afternoon"
            else -> "Good evening"
        }
    }

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
                onProfileClick = onProfileClick,
                onOfflineClick = onSearchClick
            )

            LazyColumn(
                state = listState,
                modifier = Modifier
                    .fillMaxSize()
                    .testTag("home_scroll"),
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
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

                item(key = "search") {
                    HomeSearchEntry(onClick = onSearchClick)
                }

                item(key = "categories") {
                    CategoryShortcutRow(onCategoryClick = onCategoryClick)
                }

                if (state.isOffline) {
                    item(key = "offline_strip") {
                        OfflineStatusStrip(onDetailsClick = onSearchClick)
                    }
                }

                if (state.loading) {
                    items(3, key = { "skeleton_$it" }) { OfferCardSkeleton() }
                    return@LazyColumn
                }

                // One restrained promotion (design.md §14.3 item 5).
                if (state.promotions.isNotEmpty()) {
                    item(key = "billboard") {
                        PromotionBillboard(
                            promotions = state.promotions,
                            reducedMotion = reducedMotion,
                            onPromotionAction = onPromotionAction
                        )
                    }
                }

                offerSection(
                    key = "popular",
                    title = "Popular offers",
                    offers = state.sections.popular.take(4),
                    state = state,
                    onOfferSelect = onOfferSelect,
                    onFavouriteToggle = favouriteToggle
                )

                offerSection(
                    key = "bought_today",
                    title = "Bought today",
                    offers = state.sections.boughtToday,
                    state = state,
                    onOfferSelect = onOfferSelect,
                    onFavouriteToggle = favouriteToggle
                )

                offerSection(
                    key = "more_offers",
                    title = "More offers you can buy",
                    offers = state.sections.moreOffers.take(4),
                    state = state,
                    onOfferSelect = onOfferSelect,
                    onFavouriteToggle = favouriteToggle
                )

                offerSection(
                    key = "buy_again",
                    title = "Buy again",
                    offers = state.sections.buyAgain.take(4),
                    state = state,
                    onOfferSelect = onOfferSelect,
                    onFavouriteToggle = favouriteToggle
                )

                offerSection(
                    key = "favourites",
                    title = "Your favourites",
                    offers = state.sections.favourites,
                    state = state,
                    onOfferSelect = onOfferSelect,
                    onFavouriteToggle = favouriteToggle
                )

                offerSection(
                    key = "suggestions",
                    title = "You might also like",
                    offers = state.sections.suggestions,
                    state = state,
                    onOfferSelect = onOfferSelect,
                    onFavouriteToggle = favouriteToggle
                )

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

/**
 * Adds a titled section of offer cards to the Home list — only when [offers] is
 * non-empty, so sections stay silent rather than showing empty placeholders.
 */
private fun androidx.compose.foundation.lazy.LazyListScope.offerSection(
    key: String,
    title: String,
    offers: List<OfferItem>,
    state: HomeUiState,
    onOfferSelect: (OfferItem) -> Unit,
    onFavouriteToggle: (OfferItem) -> Unit
) {
    if (offers.isEmpty()) return
    item(key = "header_$key") {
        Spacer(Modifier.height(8.dp))
        SectionHeader(title)
    }
    items(offers, key = { "${key}_${it.id}" }) { offer ->
        OfferCard(
            offer = offer,
            dailyState = dailyStateFor(offer, state.purchases, state.recipientNumber, state.nowMillis),
            isOffline = state.isOffline,
            onCardClick = { onOfferSelect(offer) },
            onFavouriteToggle = { onFavouriteToggle(offer) }
        )
    }
}

@Composable
private fun HomeSearchEntry(onClick: () -> Unit) {
    Surface(
        color = MaterialTheme.colorScheme.surface,
        shape = FieldButtonShape,
        border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
        modifier = Modifier
            .fillMaxWidth()
            .clip(FieldButtonShape)
            .clickable { onClick() }
            .testTag("home_search_entry")
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Outlined.Search,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(20.dp)
            )
            Spacer(Modifier.size(12.dp))
            Text(
                text = "Search data, SMS or minutes",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant
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
