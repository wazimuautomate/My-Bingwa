package com.example.feature.offers

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
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Clear
import androidx.compose.material.icons.outlined.FilterList
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.SearchOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Slider
import androidx.compose.material3.SliderDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.core.model.OfferCategory
import com.example.core.model.OfferItem
import com.example.core.ui.EmptyStateView
import com.example.core.ui.OfferCard
import com.example.data.fake.OfferFilterState
import com.example.data.fake.SortOption
import com.example.data.fake.ValidityFilter
import com.example.ui.theme.BottomSheetTopShape
import com.example.ui.theme.FieldButtonShape
import com.example.ui.theme.TagShape
import com.example.ui.theme.TypographyPageHeading

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OffersScreen(
    offers: List<OfferItem>,
    filterState: OfferFilterState,
    isOffline: Boolean,
    onSearchQueryChange: (String) -> Unit,
    onCategorySelect: (OfferCategory) -> Unit,
    onFilterStateChange: (OfferFilterState) -> Unit,
    onClearFilters: () -> Unit,
    onOfferSelect: (OfferItem) -> Unit,
    onOfferBuy: (OfferItem) -> Unit,
    onFavouriteToggle: (OfferItem) -> Unit
) {
    var showFilterSheet by remember { mutableStateOf(false) }

    // Apply Filter Logic
    val filteredOffers = remember(offers, filterState) {
        offers.filter { offer ->
            // Category check
            val matchesCategory = when (filterState.selectedCategory) {
                OfferCategory.ALL -> true
                OfferCategory.FAVOURITES -> offer.isFavourite
                else -> offer.category == filterState.selectedCategory
            }

            // Search query check
            val query = filterState.searchQuery.trim().lowercase()
            val matchesSearch = if (query.isEmpty()) true else {
                offer.name.lowercase().contains(query) ||
                        offer.allowance.lowercase().contains(query) ||
                        offer.category.label.lowercase().contains(query) ||
                        offer.priceKsh.toString().contains(query)
            }

            // Price check
            val matchesPrice = offer.priceKsh <= filterState.maxPriceKsh

            // Validity check
            val matchesValidity = when (filterState.selectedValidity) {
                ValidityFilter.ALL -> true
                ValidityFilter.HOURLY -> offer.validity.lowercase().contains("hour")
                ValidityFilter.DAILY -> offer.validity.lowercase().contains("day") || offer.validity.lowercase().contains("24") || offer.validity.lowercase().contains("midnight")
                ValidityFilter.WEEKLY -> offer.validity.lowercase().contains("week") || offer.validity.lowercase().contains("7")
                ValidityFilter.MONTHLY -> offer.validity.lowercase().contains("month") || offer.validity.lowercase().contains("30")
            }

            matchesCategory && matchesSearch && matchesPrice && matchesValidity
        }.let { list ->
            when (filterState.selectedSort) {
                SortOption.POPULAR -> list.sortedByDescending { it.isPopular }
                SortOption.LOWEST_PRICE -> list.sortedBy { it.priceKsh }
                SortOption.HIGHEST_VALUE -> list.sortedByDescending { it.priceKsh }
            }
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Top Heading & Search Bar
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp)
        ) {
            Text(
                text = "Offers",
                style = TypographyPageHeading.copy(fontSize = 24.sp),
                color = MaterialTheme.colorScheme.onBackground,
                fontWeight = FontWeight.Bold
            )

            Spacer(modifier = Modifier.height(10.dp))

            OutlinedTextField(
                value = filterState.searchQuery,
                onValueChange = onSearchQueryChange,
                placeholder = { Text("Search data, SMS or minutes") },
                leadingIcon = {
                    Icon(
                        imageVector = Icons.Outlined.Search,
                        contentDescription = "Search",
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                },
                trailingIcon = {
                    if (filterState.searchQuery.isNotEmpty()) {
                        IconButton(onClick = { onSearchQueryChange("") }) {
                            Icon(
                                imageVector = Icons.Outlined.Clear,
                                contentDescription = "Clear search",
                                tint = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                },
                singleLine = true,
                shape = FieldButtonShape,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = MaterialTheme.colorScheme.primary,
                    unfocusedBorderColor = MaterialTheme.colorScheme.outline,
                    focusedContainerColor = MaterialTheme.colorScheme.surface,
                    unfocusedContainerColor = MaterialTheme.colorScheme.surface
                ),
                modifier = Modifier
                    .fillMaxWidth()
                    .testTag("offers_search_field")
            )

            Spacer(modifier = Modifier.height(12.dp))

            // Horizontally Scrollable Category Filters
            LazyRow(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                contentPadding = PaddingValues(horizontal = 2.dp)
            ) {
                items(OfferCategory.entries.toTypedArray()) { category ->
                    val selected = filterState.selectedCategory == category
                    FilterChip(
                        selected = selected,
                        onClick = { onCategorySelect(category) },
                        label = {
                            Text(
                                text = category.label,
                                style = MaterialTheme.typography.labelLarge,
                                fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium
                            )
                        },
                        shape = TagShape,
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = MaterialTheme.colorScheme.primary,
                            selectedLabelColor = MaterialTheme.colorScheme.onPrimary,
                            containerColor = MaterialTheme.colorScheme.surfaceVariant,
                            labelColor = MaterialTheme.colorScheme.onSurfaceVariant
                        ),
                        modifier = Modifier.testTag("category_chip_${category.name.lowercase()}")
                    )
                }
            }

            Spacer(modifier = Modifier.height(10.dp))

            // Result Count & Filter Control
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "${filteredOffers.size} offers available",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    fontWeight = FontWeight.Medium
                )

                Surface(
                    shape = FieldButtonShape,
                    color = MaterialTheme.colorScheme.surfaceVariant,
                    modifier = Modifier
                        .clip(FieldButtonShape)
                        .clickable { showFilterSheet = true }
                        .testTag("filter_control_button")
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.FilterList,
                            contentDescription = "Filter",
                            tint = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = "Filter & Sort",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(8.dp))

        // Offer List
        if (filteredOffers.isEmpty()) {
            EmptyStateView(
                icon = Icons.Outlined.SearchOff,
                title = "No offers found",
                description = "Try adjusting your search query or clearing filter choices.",
                actionText = "Clear filters",
                onActionClick = onClearFilters
            )
        } else {
            LazyColumn(
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.fillMaxSize()
            ) {
                items(filteredOffers, key = { it.id }) { offer ->
                    OfferCard(
                        offer = offer,
                        isOffline = isOffline,
                        onCardClick = { onOfferSelect(offer) },
                        onBuyClick = { onOfferBuy(offer) },
                        onFavouriteToggle = { onFavouriteToggle(offer) }
                    )
                }
            }
        }
    }

    // Filter Bottom Sheet
    if (showFilterSheet) {
        ModalBottomSheet(
            onDismissRequest = { showFilterSheet = false },
            sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
            shape = BottomSheetTopShape,
            containerColor = MaterialTheme.colorScheme.surface
        ) {
            FilterBottomSheetContent(
                currentFilter = filterState,
                offerCount = filteredOffers.size,
                onApplyFilter = { newFilter ->
                    onFilterStateChange(newFilter)
                    showFilterSheet = false
                },
                onClear = {
                    onClearFilters()
                    showFilterSheet = false
                }
            )
        }
    }
}

@Composable
private fun FilterBottomSheetContent(
    currentFilter: OfferFilterState,
    offerCount: Int,
    onApplyFilter: (OfferFilterState) -> Unit,
    onClear: () -> Unit
) {
    var selectedValidity by remember(currentFilter) { mutableStateOf(currentFilter.selectedValidity) }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(24.dp)
    ) {
        Text(
            text = "Filter Offers",
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )

        Spacer(modifier = Modifier.height(16.dp))

        Text(
            text = "Select Validity",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurface
        )

        Spacer(modifier = Modifier.height(10.dp))

        ValidityFilter.entries.forEach { option ->
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(FieldButtonShape)
                    .clickable { selectedValidity = option }
                    .padding(vertical = 8.dp, horizontal = 4.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                RadioButton(
                    selected = (selectedValidity == option),
                    onClick = { selectedValidity = option },
                    colors = RadioButtonDefaults.colors(
                        selectedColor = MaterialTheme.colorScheme.primary
                    )
                )
                Spacer(modifier = Modifier.width(10.dp))
                Text(
                    text = option.label,
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = if (selectedValidity == option) FontWeight.Bold else FontWeight.Normal,
                    color = MaterialTheme.colorScheme.onSurface
                )
            }
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Action buttons
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            OutlinedButton(
                onClick = onClear,
                shape = FieldButtonShape,
                modifier = Modifier
                    .weight(1f)
                    .height(50.dp)
            ) {
                Text("Clear filters", style = MaterialTheme.typography.labelLarge)
            }

            Button(
                onClick = {
                    onApplyFilter(
                        currentFilter.copy(
                            selectedValidity = selectedValidity
                        )
                    )
                },
                shape = FieldButtonShape,
                colors = ButtonDefaults.buttonColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    contentColor = MaterialTheme.colorScheme.onPrimary
                ),
                modifier = Modifier
                    .weight(1.5f)
                    .height(50.dp)
                    .testTag("apply_filter_button")
            ) {
                Text("Show $offerCount offers", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold)
            }
        }
    }
}
