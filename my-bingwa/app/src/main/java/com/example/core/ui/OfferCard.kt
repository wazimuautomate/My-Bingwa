package com.example.core.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.outlined.AccessTime
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.FavoriteBorder
import androidx.compose.material.icons.outlined.HourglassEmpty
import androidx.compose.material.icons.outlined.WifiOff
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.example.core.model.DailyStateKind
import com.example.core.model.OfferDailyState
import com.example.core.model.OfferItem
import com.example.core.model.PurchasePolicy
import com.example.ui.theme.CardShape
import com.example.ui.theme.FieldButtonShape
import com.example.ui.theme.TagShape
import com.example.ui.theme.categoryColors

/**
 * Compact offer card — a *selection* surface, not a miniature checkout
 * (design.md §13.7, Plan.md §5.3). The whole card is tappable and opens offer
 * details / the purchase sheet; there is deliberately no full-size Buy button.
 * The favourite control keeps its own target. Daily purchase state is shown as
 * a calm label, never strikethrough or error red for a legitimate limit.
 */
@Composable
fun OfferCard(
    offer: OfferItem,
    dailyState: OfferDailyState? = null,
    isOffline: Boolean = false,
    onCardClick: () -> Unit,
    onFavouriteToggle: () -> Unit,
    modifier: Modifier = Modifier
) {
    val offlineUnavailable =
        isOffline && offer.purchasePolicy == PurchasePolicy.ONCE_PER_RECIPIENT_PER_DAY &&
            (dailyState?.purchasable ?: true)
    val deEmphasise = dailyState != null && !dailyState.purchasable

    Card(
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 112.dp)
            .clip(CardShape)
            .clickable { onCardClick() }
            .testTag("offer_card_${offer.id}"),
        shape = CardShape,
        colors = CardDefaults.cardColors(
            containerColor = if (deEmphasise) MaterialTheme.colorScheme.surfaceVariant else MaterialTheme.colorScheme.surface
        ),
        border = BorderStroke(
            width = 1.dp,
            color = if (deEmphasise) MaterialTheme.colorScheme.primaryContainer
            else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            // Top: category tag + favourite
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                val catColors = categoryColors(offer.category)
                Surface(color = catColors.container, shape = TagShape) {
                    Text(
                        text = offer.category.label,
                        style = MaterialTheme.typography.labelSmall,
                        color = catColors.onContainer,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp)
                    )
                }

                IconButton(
                    onClick = onFavouriteToggle,
                    modifier = Modifier
                        .size(28.dp)
                        .testTag("favourite_button_${offer.id}")
                ) {
                    Icon(
                        imageVector = if (offer.isFavourite) Icons.Filled.Favorite else Icons.Outlined.FavoriteBorder,
                        contentDescription = if (offer.isFavourite) "Remove from favourites" else "Add to favourites",
                        tint = if (offer.isFavourite) MaterialTheme.colorScheme.tertiary else MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }

            Spacer(Modifier.height(6.dp))

            // Middle: allowance/name + validity (left), price (right)
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = offer.name,
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.onSurface,
                        fontWeight = FontWeight.Bold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = offer.validity,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }

                Text(
                    text = "KSh ${offer.priceKsh}",
                    style = MaterialTheme.typography.titleLarge,
                    color = MaterialTheme.colorScheme.primary,
                    fontWeight = FontWeight.ExtraBold,
                    modifier = Modifier.padding(start = 8.dp)
                )
            }

            Spacer(Modifier.height(10.dp))

            // Bottom: at most one commercial label (left) + daily/offline state (right)
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                if (offer.commercialLabel != null) {
                    CommercialLabelChip(offer.commercialLabel!!)
                } else {
                    Spacer(Modifier.size(0.dp))
                }

                when {
                    offlineUnavailable -> StateLabel(
                        icon = Icons.Outlined.WifiOff,
                        text = "Reconnect to buy",
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    dailyState != null -> DailyStateLabel(dailyState)
                    else -> Spacer(Modifier.size(0.dp))
                }
            }
        }
    }
}

@Composable
private fun CommercialLabelChip(label: String) {
    // Orange is reserved for genuine promotions/limited offers; "Popular"/"Best value"
    // stay tonal so orange never becomes a second navigation/CTA system.
    val promotional = label.contains("Limited", ignoreCase = true) || label.contains("offer", ignoreCase = true)
    val container = if (promotional) MaterialTheme.colorScheme.tertiaryContainer else MaterialTheme.colorScheme.surfaceVariant
    val content = if (promotional) MaterialTheme.colorScheme.onTertiaryContainer else MaterialTheme.colorScheme.onSurfaceVariant
    Surface(color = container, shape = TagShape) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = content,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp)
        )
    }
}

@Composable
private fun DailyStateLabel(state: OfferDailyState) {
    when (state.kind) {
        DailyStateKind.AVAILABLE -> Text(
            text = "Available today",
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            fontWeight = FontWeight.Medium
        )
        DailyStateKind.BOUGHT_TODAY, DailyStateKind.AVAILABLE_TOMORROW -> TonalStateChip(
            icon = if (state.kind == DailyStateKind.BOUGHT_TODAY) Icons.Outlined.CheckCircle else Icons.Outlined.AccessTime,
            text = if (state.kind == DailyStateKind.BOUGHT_TODAY) "Bought today" else "Available tomorrow",
            container = MaterialTheme.colorScheme.primaryContainer,
            content = MaterialTheme.colorScheme.primary
        )
        DailyStateKind.WAITING_VERIFY -> TonalStateChip(
            icon = Icons.Outlined.HourglassEmpty,
            text = "Waiting to verify",
            container = MaterialTheme.colorScheme.tertiaryContainer,
            content = MaterialTheme.colorScheme.onTertiaryContainer
        )
        DailyStateKind.PURCHASES_LEFT -> StateLabel(
            icon = Icons.Outlined.AccessTime,
            text = state.label,
            tint = MaterialTheme.colorScheme.secondary
        )
    }
}

@Composable
private fun TonalStateChip(icon: ImageVector, text: String, container: Color, content: Color) {
    Surface(color = container, shape = FieldButtonShape) {
        Row(
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(imageVector = icon, contentDescription = null, tint = content, modifier = Modifier.size(14.dp))
            Spacer(Modifier.size(4.dp))
            Text(text = text, style = MaterialTheme.typography.labelSmall, color = content, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun StateLabel(icon: ImageVector, text: String, tint: Color) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(imageVector = icon, contentDescription = null, tint = tint, modifier = Modifier.size(14.dp))
        Spacer(Modifier.size(4.dp))
        Text(text = text, style = MaterialTheme.typography.labelSmall, color = tint, fontWeight = FontWeight.Medium)
    }
}
