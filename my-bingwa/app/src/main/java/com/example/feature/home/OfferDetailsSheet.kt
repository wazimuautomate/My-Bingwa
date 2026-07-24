package com.example.feature.home

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.outlined.AccessTime
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.FavoriteBorder
import androidx.compose.material.icons.outlined.HourglassEmpty
import androidx.compose.material.icons.outlined.WifiOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.example.core.model.OfferItem
import com.example.core.model.PurchasePolicy
import com.example.ui.theme.BottomSheetTopShape
import com.example.ui.theme.FieldButtonShape
import com.example.ui.theme.TagShape
import com.example.ui.theme.TypographyOfferPrice
import com.example.ui.theme.categoryColors

/**
 * Short offer-details bottom sheet (design.md §14.5): allowance/name, price,
 * validity, eligibility/daily state, favourite control and the primary
 * **Buy bundle** action. The sheet is presentation only — it never claims a
 * bundle was delivered and hands the actual purchase to the checkout phase.
 *
 * When an offer cannot be bought right now (already bought today, awaiting
 * verification, or a hard once-per-day offer while offline) the primary action
 * is disabled with a plain explanation instead of an error state.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OfferDetailsSheet(
    offer: OfferItem,
    dailyState: OfferDailyState,
    isOffline: Boolean,
    onBuy: () -> Unit,
    onToggleFavourite: () -> Unit,
    onDismiss: () -> Unit
) {
    val offlineBlocksHardLimit =
        isOffline && offer.purchasePolicy == PurchasePolicy.ONCE_PER_RECIPIENT_PER_DAY
    val canBuy = dailyState.purchasable && !offlineBlocksHardLimit

    val blockedReason: String? = when {
        offlineBlocksHardLimit ->
            "This once-per-day offer can't be paid for offline. Reconnect to buy it safely."
        dailyState.kind == DailyStateKind.AVAILABLE_TOMORROW ->
            "You've already bought this today. It's available again tomorrow."
        dailyState.kind == DailyStateKind.WAITING_VERIFY ->
            "A payment for this offer is still being verified. Please wait before buying again."
        else -> null
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        shape = BottomSheetTopShape,
        containerColor = MaterialTheme.colorScheme.surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 24.dp)
                .padding(bottom = 24.dp)
                .testTag("offer_details_sheet")
        ) {
            // Category + favourite
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
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 3.dp)
                    )
                }
                IconButton(
                    onClick = onToggleFavourite,
                    modifier = Modifier.testTag("offer_details_favourite")
                ) {
                    Icon(
                        imageVector = if (offer.isFavourite) Icons.Filled.Favorite else Icons.Outlined.FavoriteBorder,
                        contentDescription = if (offer.isFavourite) "Remove from favourites" else "Add to favourites",
                        tint = if (offer.isFavourite) MaterialTheme.colorScheme.tertiary else MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }

            Spacer(Modifier.height(8.dp))

            // Allowance / name — strongest hierarchy alongside price
            Text(
                text = offer.allowance,
                style = MaterialTheme.typography.headlineSmall,
                color = MaterialTheme.colorScheme.onSurface,
                fontWeight = FontWeight.Bold
            )
            Text(
                text = offer.name,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )

            Spacer(Modifier.height(12.dp))

            // Price + validity
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    text = "KSh ${offer.priceKsh}",
                    style = TypographyOfferPrice,
                    color = MaterialTheme.colorScheme.primary,
                    fontWeight = FontWeight.ExtraBold
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    text = "· ${offer.validity}",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(bottom = 4.dp)
                )
            }

            if (offer.description.isNotBlank()) {
                Spacer(Modifier.height(12.dp))
                Text(
                    text = offer.description,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            Spacer(Modifier.height(14.dp))

            // Daily / eligibility state
            DailyStateRow(dailyState = dailyState)

            if (isOffline) {
                Spacer(Modifier.height(8.dp))
                InfoRow(
                    icon = Icons.Outlined.WifiOff,
                    text = "You're offline. Payment uses saved M-Pesa instructions."
                )
            }

            if (blockedReason != null) {
                Spacer(Modifier.height(6.dp))
                Text(
                    text = blockedReason,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            Spacer(Modifier.height(20.dp))

            Button(
                onClick = onBuy,
                enabled = canBuy,
                shape = FieldButtonShape,
                colors = ButtonDefaults.buttonColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    contentColor = MaterialTheme.colorScheme.onPrimary
                ),
                modifier = Modifier
                    .fillMaxWidth()
                    .height(52.dp)
                    .testTag("offer_details_buy")
            ) {
                Text(
                    text = if (canBuy) "Buy bundle" else "Not available right now",
                    style = MaterialTheme.typography.labelLarge,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}

@Composable
private fun DailyStateRow(dailyState: OfferDailyState) {
    val (icon, tint) = when (dailyState.kind) {
        DailyStateKind.BOUGHT_TODAY, DailyStateKind.AVAILABLE_TOMORROW ->
            Icons.Outlined.CheckCircle to MaterialTheme.colorScheme.primary
        DailyStateKind.WAITING_VERIFY ->
            Icons.Outlined.HourglassEmpty to MaterialTheme.colorScheme.tertiary
        DailyStateKind.PURCHASES_LEFT ->
            Icons.Outlined.AccessTime to MaterialTheme.colorScheme.secondary
        DailyStateKind.AVAILABLE ->
            Icons.Outlined.CheckCircle to MaterialTheme.colorScheme.secondary
    }
    InfoRow(icon = icon, text = dailyState.label, tint = tint)
}

@Composable
private fun InfoRow(
    icon: ImageVector,
    text: String,
    tint: androidx.compose.ui.graphics.Color = MaterialTheme.colorScheme.onSurfaceVariant
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = tint,
            modifier = Modifier.size(18.dp)
        )
        Spacer(Modifier.width(8.dp))
        Text(
            text = text,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurface,
            fontWeight = FontWeight.Medium
        )
    }
}
