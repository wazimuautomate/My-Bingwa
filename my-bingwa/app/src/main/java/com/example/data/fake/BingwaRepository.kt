package com.example.data.fake

import com.example.core.model.AppThemeSetting
import com.example.core.model.NotificationItem
import com.example.core.model.OfferCategory
import com.example.core.model.OfferItem
import com.example.core.model.PaymentStatus
import com.example.core.model.Promotion
import com.example.core.model.PurchaseRecord
import com.example.core.model.UserProfile
import com.example.core.notifications.ConnectionState
import com.example.data.config.AppConfig
import com.example.data.payment.ActiveOrder
import com.example.data.payment.OfflineEligibility
import com.example.data.payment.OfflinePaymentConfig
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
    HIGHEST_VALUE("Highest value"),
    SHORTEST_VALIDITY("Shortest validity"),
    LONGEST_VALIDITY("Longest validity")
}

/** Highest offer price in the catalogue; the price filter defaults to this so nothing is hidden. */
const val MAX_OFFER_PRICE_KSH = 1005

data class OfferFilterState(
    val selectedCategory: OfferCategory = OfferCategory.ALL,
    val selectedValidity: ValidityFilter = ValidityFilter.ALL,
    val searchQuery: String = "",
    val maxPriceKsh: Int = MAX_OFFER_PRICE_KSH,
    val selectedSort: SortOption = SortOption.POPULAR
)

interface BingwaRepository {
    val userProfile: StateFlow<UserProfile>
    val appTheme: StateFlow<AppThemeSetting>
    val isOffline: StateFlow<Boolean>
    /** True until the first cached catalogue load resolves; drives the Home/Offers skeletons. */
    val catalogueLoading: StateFlow<Boolean>
    val offers: StateFlow<List<OfferItem>>
    /** Active promotions/announcements for the Home billboard (Plan.md §5.13). */
    val promotions: StateFlow<List<Promotion>>
    val filterState: StateFlow<OfferFilterState>
    val purchases: StateFlow<List<PurchaseRecord>>
    val notifications: StateFlow<List<NotificationItem>>
    val recentRecipients: StateFlow<List<String>>
    val devStkOutcome: StateFlow<DevStkOutcome>

    /**
     * Seller details (Till, Paybill, support) synced from the server but always
     * available offline from cache/defaults. Server is only for syncing (Phase 6).
     */
    val appConfig: StateFlow<AppConfig>

    /**
     * The device's current internet-transport state, pushed in from the
     * [ConnectivityObserver] in MainActivity. Feeds offer suggestion logic; the
     * canonical offline flag stays [isOffline].
     */
    val connectionState: StateFlow<ConnectionState>

    /**
     * The in-flight checkout, or null when idle. Exposed for process-death
     * restoration (Plan.md §3.4); persisted in Phase 6.
     */
    val activeOrder: StateFlow<ActiveOrder?>

    fun updateProfile(name: String, primaryNumber: String)
    fun setOnboardingCompleted(completed: Boolean)
    /** Reflect the real OS notification-permission state into the profile (persisted). */
    fun setNotificationsEnabled(enabled: Boolean)
    /** Reflect the real OS RECEIVE_SMS grant into the profile (persisted). */
    fun setSmsAlertsEnabled(enabled: Boolean)
    fun setAppTheme(theme: AppThemeSetting)
    fun toggleOfflineMode()
    fun setOfflineMode(offline: Boolean)

    fun setSearchQuery(query: String)
    fun setCategoryFilter(category: OfferCategory)
    fun setFilterState(filter: OfferFilterState)
    fun clearFilters()

    fun toggleFavourite(offerId: String)
    /** Deterministic favourite set, so a "Removed from favourites" Undo restores the exact prior state. */
    fun setFavourite(offerId: String, isFavourite: Boolean)
    /** Re-load the cached catalogue (retry after an error / manual refresh). */
    suspend fun refreshCatalogue()
    fun setDevStkOutcome(outcome: DevStkOutcome)

    /**
     * Online M-Pesa STK Push. [clientRequestId] is the idempotency key: repeating it
     * must never create a second charge (double-tap, retry). Runs the payment state
     * machine behind the payment gateway and returns the settled/honest record.
     *
     * [isForSelf] selects the route: buy-for-myself uses the configured gateway (real
     * backend/Daraja when a base URL is set — the Till lands the money on the seller's
     * own-number identity); buy-for-another remains a simulation in this phase.
     */
    suspend fun executeMpesaStkPush(
        offer: OfferItem,
        recipientNumber: String,
        payerNumber: String,
        clientRequestId: String,
        isForSelf: Boolean
    ): PurchaseRecord

    /**
     * Records a customer-marked offline payment. With an M-Pesa [receipt] the
     * attempt becomes **Waiting to verify**; without one it becomes **Payment not
     * confirmed** (Plan.md §5.8). Never returns success.
     */
    suspend fun executeOfflinePayment(
        offer: OfferItem,
        recipientNumber: String,
        payerNumber: String,
        isTill: Boolean,
        receipt: String?
    ): PurchaseRecord

    /** Whether [offer] can be bought offline via the chosen route, and why not otherwise. */
    fun offlineEligibility(offer: OfferItem, isForSelf: Boolean): OfflineEligibility

    /** The current verified offline config (Till/Paybill), or null when unavailable/expired. */
    fun offlineConfig(): OfflinePaymentConfig?

    /** Clear the restored active order (checkout dismissed or reached a terminal state). */
    fun clearActiveOrder()

    /** Fetch fresh seller config from the server and update [appConfig]; safe to call when online. */
    suspend fun syncRemoteConfig()

    /**
     * Fetch the catalogue from the server and replace the offers when a non-empty
     * list is returned (preserving local favourite/bought-today state). On failure
     * the local catalogue is kept, so the app always has offers offline (Phase 7).
     */
    suspend fun syncCatalogue()

    fun deletePurchaseRecord(recordId: String)
    fun deletePurchaseRecords(recordIds: List<String>)
    fun undoDeletePurchaseRecord(record: PurchaseRecord)

    /** Record the latest observed connectivity state (from [ConnectivityObserver]). */
    fun setConnectionState(state: ConnectionState)

    /**
     * Reconcile a Safaricom bundle-delivery SMS against the most recent RECEIVED
     * purchase of [category] not yet flagged confirmed: flip its
     * [PurchaseRecord.isDeliveryConfirmed] and add a carrier-attributed in-app
     * notification. Honest — this never claims My Bingwa delivered anything, and
     * it does not post a loud system notification (delivery is not shouted).
     */
    fun onBundleDeliveryDetected(category: OfferCategory)

    /**
     * Handle a Safaricom low-balance SMS for [category] by adding an in-app
     * offers suggestion using only §8-allowed language (never "you are running
     * out" / "you need more data").
     */
    fun onLowBalanceDetected(category: OfferCategory)

    fun markNotificationRead(id: String)
    fun markAllNotificationsRead()
    /** Remove a single notification from the local centre (customer "clear"). */
    fun deleteNotification(id: String)
    /** Remove every notification from the local centre (customer "clear all"). */
    fun clearAllNotifications()

    fun clearAllLocalData()
}
