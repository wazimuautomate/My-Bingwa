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
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AutoAwesome
import androidx.compose.material.icons.outlined.Call
import androidx.compose.material.icons.outlined.ChatBubbleOutline
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.SignalCellularAlt
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.core.model.OfferCategory
import com.example.core.model.OfferItem
import com.example.core.model.PurchaseRecord
import com.example.core.model.UserProfile
import com.example.core.ui.MyBingwaTopAppBar
import com.example.core.ui.OfferCard
import com.example.core.ui.OfflineStatusStrip
import com.example.ui.theme.CardShape
import com.example.ui.theme.FieldButtonShape
import com.example.ui.theme.PromotionStatusShape
import com.example.ui.theme.TagShape
import java.util.Calendar

@Composable
fun HomeScreen(
    profile: UserProfile,
    isOffline: Boolean,
    unreadNotifCount: Int,
    offers: List<OfferItem>,
    recentPurchases: List<PurchaseRecord>,
    onCategoryClick: (OfferCategory) -> Unit,
    onOfferSelect: (OfferItem) -> Unit,
    onOfferBuy: (OfferItem) -> Unit,
    onFavouriteToggle: (OfferItem) -> Unit,
    onNotifClick: () -> Unit,
    onProfileClick: () -> Unit,
    onSearchClick: () -> Unit
) {
    val scrollState = rememberScrollState()

    // Time-based greeting
    val greeting = remember {
        val hour = Calendar.getInstance().get(Calendar.HOUR_OF_DAY)
        when {
            hour < 12 -> "Good morning"
            hour < 17 -> "Good afternoon"
            else -> "Good evening"
        }
    }

    val favouriteOffers = offers.filter { it.isFavourite }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Top Bar
        MyBingwaTopAppBar(
            userName = profile.name,
            unreadNotifCount = unreadNotifCount,
            isOffline = isOffline,
            onNotifClick = onNotifClick,
            onProfileClick = onProfileClick,
            onOfflineClick = onSearchClick
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(scrollState)
                .padding(horizontal = 16.dp, vertical = 8.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Welcome Section
            Text(
                text = "$greeting, ${profile.name.ifEmpty { "Customer" }}",
                style = MaterialTheme.typography.headlineMedium,
                color = MaterialTheme.colorScheme.onBackground,
                fontWeight = FontWeight.Bold,
                modifier = Modifier
                    .padding(vertical = 12.dp)
                    .testTag("home_greeting_text")
            )

            // Category Section
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                CategoryShortcutTile(
                    label = "Data",
                    icon = Icons.Outlined.SignalCellularAlt,
                    category = OfferCategory.DATA,
                    onClick = { onCategoryClick(OfferCategory.DATA) }
                )
                CategoryShortcutTile(
                    label = "SMS",
                    icon = Icons.Outlined.ChatBubbleOutline,
                    category = OfferCategory.SMS,
                    onClick = { onCategoryClick(OfferCategory.SMS) }
                )
                CategoryShortcutTile(
                    label = "Minutes",
                    icon = Icons.Outlined.Call,
                    category = OfferCategory.MINUTES,
                    onClick = { onCategoryClick(OfferCategory.MINUTES) }
                )
                CategoryShortcutTile(
                    label = "Special",
                    icon = Icons.Outlined.AutoAwesome,
                    category = OfferCategory.SPECIAL,
                    onClick = { onCategoryClick(OfferCategory.SPECIAL) }
                )
            }

            Spacer(modifier = Modifier.height(20.dp))

            // Connectivity Strip if offline
            if (isOffline) {
                OfflineStatusStrip()
                Spacer(modifier = Modifier.height(16.dp))
            }

            // Announcement Section (Large Card with Rectangular Banner Graphic)
            LargeAnnouncementCard(
                label = "HOT DEAL",
                headline = "2 GB for KSh 110",
                validity = "Valid for 24 Hours",
                buttonText = "Buy Now",
                onAction = {
                    offers.find { it.name.contains("2 GB") }?.let { onOfferBuy(it) } ?: onSearchClick()
                }
            )

            Spacer(modifier = Modifier.height(24.dp))

            // Favourites Section
            SectionHeader(title = "Favourites")
            Spacer(modifier = Modifier.height(8.dp))
            
            if (favouriteOffers.isNotEmpty()) {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    favouriteOffers.forEach { offer ->
                        OfferCard(
                            offer = offer,
                            isOffline = isOffline,
                            onCardClick = { onOfferSelect(offer) },
                            onBuyClick = { onOfferBuy(offer) },
                            onFavouriteToggle = { onFavouriteToggle(offer) }
                        )
                    }
                }
            } else {
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

            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

@Composable
private fun CategoryShortcutTile(
    label: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
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
        Box(
            modifier = Modifier
                .size(56.dp)
                .clip(CircleShape)
                .background(category.containerColor),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = category.accentColor,
                modifier = Modifier.size(26.dp)
            )
        }
        Spacer(modifier = Modifier.height(6.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )
    }
}

@Composable
private fun LargeAnnouncementCard(
    label: String,
    headline: String,
    validity: String,
    buttonText: String,
    onAction: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clip(PromotionStatusShape),
        shape = PromotionStatusShape,
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer
        )
    ) {
        Column(
            modifier = Modifier.fillMaxWidth()
        ) {
            // Rectangular Top Banner Graphic Container
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(130.dp)
                    .background(
                        brush = androidx.compose.ui.graphics.Brush.linearGradient(
                            colors = listOf(
                                MaterialTheme.colorScheme.primary,
                                MaterialTheme.colorScheme.tertiary
                            )
                        )
                    ),
                contentAlignment = Alignment.Center
            ) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Surface(
                        color = MaterialTheme.colorScheme.surface.copy(alpha = 0.25f),
                        shape = TagShape
                    ) {
                        Text(
                            text = label,
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onPrimary,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp)
                        )
                    }
                    Spacer(modifier = Modifier.height(6.dp))
                    Text(
                        text = headline,
                        style = MaterialTheme.typography.headlineMedium,
                        color = MaterialTheme.colorScheme.onPrimary,
                        fontWeight = FontWeight.ExtraBold
                    )
                }
            }

            // Bottom Info & Action Row
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Special Offer",
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        text = validity,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.8f)
                    )
                }

                Button(
                    onClick = onAction,
                    shape = FieldButtonShape,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = MaterialTheme.colorScheme.primary,
                        contentColor = MaterialTheme.colorScheme.onPrimary
                    ),
                    modifier = Modifier.testTag("announcement_card_button")
                ) {
                    Text(
                        text = buttonText,
                        style = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }
    }
}

@Composable
private fun SectionHeader(title: String) {
    Text(
        text = title,
        style = MaterialTheme.typography.titleLarge,
        color = MaterialTheme.colorScheme.onBackground,
        fontWeight = FontWeight.Bold,
        modifier = Modifier.fillMaxWidth()
    )
}
