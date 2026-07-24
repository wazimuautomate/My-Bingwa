package com.example.data.fake

import com.example.core.model.AppThemeSetting
import com.example.core.model.NotificationItem
import com.example.core.model.OfferCategory
import com.example.core.model.OfferItem
import com.example.core.model.PaymentStatus
import com.example.core.model.PurchaseRecord
import com.example.core.model.UserProfile
import kotlinx.coroutines.flow.StateFlow

enum class DevStkOutcome {
    SUCCESS,
    CANCELLED,
    FAILED,
    DELAYED
}

enum class ValidityFilter(val label: String) {
    ALL("All"),
    HOURLY("Hourly"),
    DAILY("Daily"),
    WEEKLY("Weekly"),
    MONTHLY("Monthly")
}

enum class SortOption(val label: String) {
    POPULAR("Popular"),
    LOWEST_PRICE("Lowest price"),
    HIGHEST_VALUE("Highest value")
}

data class OfferFilterState(
    val selectedCategory: OfferCategory = OfferCategory.ALL,
    val selectedValidity: ValidityFilter = ValidityFilter.ALL,
    val searchQuery: String = "",
    val maxPriceKsh: Int = 200,
    val selectedSort: SortOption = SortOption.POPULAR
)

interface BingwaRepository {
    val userProfile: StateFlow<UserProfile>
    val appTheme: StateFlow<AppThemeSetting>
    val isOffline: StateFlow<Boolean>
    val offers: StateFlow<List<OfferItem>>
    val filterState: StateFlow<OfferFilterState>
    val purchases: StateFlow<List<PurchaseRecord>>
    val notifications: StateFlow<List<NotificationItem>>
    val recentRecipients: StateFlow<List<String>>
    val devStkOutcome: StateFlow<DevStkOutcome>

    fun updateProfile(name: String, primaryNumber: String)
    fun setOnboardingCompleted(completed: Boolean)
    fun setAppTheme(theme: AppThemeSetting)
    fun toggleOfflineMode()
    fun setOfflineMode(offline: Boolean)

    fun setSearchQuery(query: String)
    fun setCategoryFilter(category: OfferCategory)
    fun setFilterState(filter: OfferFilterState)
    fun clearFilters()

    fun toggleFavourite(offerId: String)
    fun setDevStkOutcome(outcome: DevStkOutcome)

    suspend fun executeMpesaStkPush(
        offer: OfferItem,
        recipientNumber: String,
        payerNumber: String
    ): PurchaseRecord

    suspend fun executeOfflinePayment(
        offer: OfferItem,
        recipientNumber: String,
        payerNumber: String,
        isTill: Boolean
    ): PurchaseRecord

    fun deletePurchaseRecord(recordId: String)
    fun deletePurchaseRecords(recordIds: List<String>)
    fun undoDeletePurchaseRecord(record: PurchaseRecord)

    fun markNotificationRead(id: String)
    fun markAllNotificationsRead()

    fun clearAllLocalData()
}
