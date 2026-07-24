package com.example.data.fake

import com.example.core.model.AppThemeSetting
import com.example.core.model.DailyRule
import com.example.core.model.NotificationItem
import com.example.core.model.OfferCategory
import com.example.core.model.OfferItem
import com.example.core.model.PaymentMethod
import com.example.core.model.PaymentStatus
import com.example.core.model.Promotion
import com.example.core.model.PromotionAccent
import com.example.core.model.PromotionKind
import com.example.core.model.PurchasePolicy
import com.example.core.model.PurchaseRecord
import com.example.core.model.UserProfile
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import java.util.UUID

class FakeBingwaRepositoryImpl : BingwaRepository {

    private val defaultProfile = UserProfile(
        name = "Bonke",
        primaryNumber = "0727 921 038",
        isOnboardingCompleted = true,
        notificationsEnabled = true
    )

    private val _userProfile = MutableStateFlow(defaultProfile)
    override val userProfile: StateFlow<UserProfile> = _userProfile.asStateFlow()

    private val _appTheme = MutableStateFlow(AppThemeSetting.SYSTEM)
    override val appTheme: StateFlow<AppThemeSetting> = _appTheme.asStateFlow()

    private val _isOffline = MutableStateFlow(false)
    override val isOffline: StateFlow<Boolean> = _isOffline.asStateFlow()

    private val initialOffers = listOf(
        OfferItem(
            id = "off_1",
            name = "1 GB Hourly",
            allowance = "1 GB",
            priceKsh = 19,
            validity = "1 hour",
            category = OfferCategory.DATA,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            commercialLabel = "Popular",
            isPopular = true,
            description = "High-speed 1 GB internet bundle valid for 60 minutes from purchase."
        ),
        OfferItem(
            id = "off_2",
            name = "250 MB Daily",
            allowance = "250 MB",
            priceKsh = 20,
            validity = "24 hours",
            category = OfferCategory.DATA,
            dailyRule = DailyRule.ONCE_PER_DAY,
            description = "Stay connected all day with 250 MB data valid for 24 hours."
        ),
        OfferItem(
            id = "off_3",
            name = "1.5 GB 3-Hour",
            allowance = "1.5 GB",
            priceKsh = 50,
            validity = "3 hours",
            category = OfferCategory.DATA,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            description = "Stream and download with 1.5 GB fast data valid for 3 hours."
        ),
        OfferItem(
            id = "off_4",
            name = "1.25 GB Midnight",
            allowance = "1.25 GB",
            priceKsh = 55,
            validity = "Till midnight",
            category = OfferCategory.DATA,
            dailyRule = DailyRule.ONCE_PER_DAY,
            description = "1.25 GB data valid until 11:59 PM today."
        ),
        OfferItem(
            id = "off_5",
            name = "2 GB Daily Power",
            allowance = "2 GB",
            priceKsh = 110,
            validity = "24 hours",
            category = OfferCategory.DATA,
            dailyRule = DailyRule.ONCE_PER_DAY,
            commercialLabel = "Best value",
            isPopular = true,
            description = "2 GB superfast data bundle for 24 hours. Best value for heavy browsing."
        ),
        OfferItem(
            id = "off_6",
            name = "20 SMS Bundle",
            allowance = "20 SMS",
            priceKsh = 5,
            validity = "24 hours",
            category = OfferCategory.SMS,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            description = "Send up to 20 SMS messages across networks for 24 hours."
        ),
        OfferItem(
            id = "off_7",
            name = "200 SMS Daily",
            allowance = "200 SMS",
            priceKsh = 10,
            validity = "24 hours",
            category = OfferCategory.SMS,
            dailyRule = DailyRule.ONCE_PER_DAY,
            description = "200 local SMS valid for 24 hours."
        ),
        OfferItem(
            id = "off_8",
            name = "1,000 SMS Weekly",
            allowance = "1,000 SMS",
            priceKsh = 30,
            validity = "7 days",
            category = OfferCategory.SMS,
            dailyRule = DailyRule.ONCE_PER_DAY,
            description = "1,000 SMS for 7 days. Text freely all week."
        ),
        OfferItem(
            id = "off_9",
            name = "20 Mins Midnight",
            allowance = "20 minutes",
            priceKsh = 22,
            validity = "Till midnight",
            category = OfferCategory.MINUTES,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            description = "20 calling minutes to any Safaricom line until midnight."
        ),
        OfferItem(
            id = "off_10",
            name = "35 Mins 2-Hour",
            allowance = "35 minutes",
            priceKsh = 23,
            validity = "2 hours",
            category = OfferCategory.MINUTES,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            description = "35 call minutes valid for 2 hours."
        ),
        OfferItem(
            id = "off_11",
            name = "50 Mins Daily",
            allowance = "50 minutes",
            priceKsh = 51,
            validity = "Till midnight",
            category = OfferCategory.MINUTES,
            dailyRule = DailyRule.ONCE_PER_DAY,
            description = "50 talk time minutes valid until midnight."
        ),
        OfferItem(
            id = "off_12",
            name = "Weekend Mix",
            allowance = "2 GB + 50 SMS",
            priceKsh = 99,
            validity = "Weekend",
            category = OfferCategory.SPECIAL,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            commercialLabel = "Limited offer",
            description = "Weekend special bundle offering 2 GB data plus 50 SMS."
        ),
        OfferItem(
            id = "off_13",
            name = "Daily Connect",
            allowance = "500 MB + 20 mins",
            priceKsh = 45,
            validity = "24 hours",
            category = OfferCategory.SPECIAL,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            commercialLabel = "Best value",
            description = "Combo offer with 500 MB data and 20 voice minutes for 24 hours."
        ),
        OfferItem(
            id = "off_14",
            name = "3 GB Weekly",
            allowance = "3 GB",
            priceKsh = 250,
            validity = "7 days",
            category = OfferCategory.DATA,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            purchasePolicy = PurchasePolicy.MULTIPLE_PER_DAY,
            commercialLabel = "Popular",
            isPopular = true,
            description = "3 GB of fast data valid for a full 7 days. Great for a steady week online."
        ),
        OfferItem(
            id = "off_15",
            name = "8 GB Monthly",
            allowance = "8 GB",
            priceKsh = 1000,
            validity = "30 days",
            category = OfferCategory.DATA,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            purchasePolicy = PurchasePolicy.MULTIPLE_PER_DAY,
            commercialLabel = "Best value",
            isPopular = true,
            description = "8 GB of data valid for 30 days. The best value for heavy monthly browsing."
        ),
        OfferItem(
            id = "off_16",
            name = "Monthly Mega",
            allowance = "15 GB + 400 mins",
            priceKsh = 1500,
            validity = "30 days",
            category = OfferCategory.SPECIAL,
            dailyRule = DailyRule.BUY_AGAIN_TODAY,
            purchasePolicy = PurchasePolicy.MULTIPLE_PER_DAY,
            commercialLabel = "Limited offer",
            description = "15 GB data plus 400 voice minutes valid for 30 days. Everything for the month."
        )
    )

    private val _offers = MutableStateFlow(initialOffers)
    override val offers: StateFlow<List<OfferItem>> = _offers.asStateFlow()

    // The catalogue is cached, so the first load resolves immediately. The flag
    // still exists so Home/Offers can show skeletons on a cold, empty cache and
    // so Phase 6/7 can drive it from a real Room-then-network refresh.
    private val _catalogueLoading = MutableStateFlow(false)
    override val catalogueLoading: StateFlow<Boolean> = _catalogueLoading.asStateFlow()

    // Billboard promotions pool. In version 1 this is seeded here; Phase 6/7 syncs
    // it from the backend into Room (Plan.md §5.13). Slides lead with the seller's
    // biggest offers (weekly/monthly/high-value) plus announcements and app news.
    // No gradients: each slide paints a single brand colour (see PromotionAccent).
    private val initialPromotions = listOf(
        Promotion(
            id = "promo_monthly_mega",
            kind = PromotionKind.OFFER,
            tag = "HOT DEAL",
            headline = "15 GB + 400 mins",
            subhead = "Monthly Mega · everything you need for KSh 1,500",
            ctaLabel = "Buy now",
            accent = PromotionAccent.GREEN,
            linkedOfferId = "off_16",
            priorityWeight = 100
        ),
        Promotion(
            id = "promo_8gb_month",
            kind = PromotionKind.OFFER,
            tag = "BEST VALUE",
            headline = "8 GB for KSh 1,000",
            subhead = "Valid 30 days · the calmest way to stay online all month",
            ctaLabel = "Buy now",
            accent = PromotionAccent.NAVY,
            linkedOfferId = "off_15",
            priorityWeight = 90
        ),
        Promotion(
            id = "promo_3gb_week",
            kind = PromotionKind.OFFER,
            tag = "POPULAR",
            headline = "3 GB Weekly",
            subhead = "A full week of data for KSh 250",
            ctaLabel = "Buy now",
            accent = PromotionAccent.BLUE,
            linkedOfferId = "off_14",
            priorityWeight = 70
        ),
        Promotion(
            id = "promo_weekend_mix",
            kind = PromotionKind.OFFER,
            tag = "LIMITED",
            headline = "Weekend Mix",
            subhead = "2 GB + 50 SMS for KSh 99 — this weekend only",
            ctaLabel = "Buy now",
            accent = PromotionAccent.ORANGE,
            linkedOfferId = "off_12",
            priorityWeight = 60
        ),
        Promotion(
            id = "promo_data_browse",
            kind = PromotionKind.ANNOUNCEMENT,
            tag = "BROWSE",
            headline = "All data bundles",
            subhead = "From hourly to monthly — find the one that fits today",
            ctaLabel = "See offers",
            accent = PromotionAccent.BLUE,
            linkedCategory = OfferCategory.DATA,
            priorityWeight = 30
        ),
        Promotion(
            id = "promo_app_update",
            kind = PromotionKind.UPDATE,
            tag = "WHAT'S NEW",
            headline = "Smoother My Bingwa",
            subhead = "Faster Home, clearer offers and a fresh promotions board",
            ctaLabel = "Got it",
            accent = PromotionAccent.NAVY,
            priorityWeight = 20
        )
    )

    private val _promotions = MutableStateFlow(initialPromotions)
    override val promotions: StateFlow<List<Promotion>> = _promotions.asStateFlow()

    private val _filterState = MutableStateFlow(OfferFilterState())
    override val filterState: StateFlow<OfferFilterState> = _filterState.asStateFlow()

    private val initialPurchases = listOf(
        PurchaseRecord(
            id = "pur_101",
            offerId = "off_1",
            offerName = "1 GB Hourly",
            allowance = "1 GB",
            priceKsh = 19,
            recipientNumber = "0727 921 038",
            payerNumber = "0727 921 038",
            mpesaCode = "RHK82910AZ",
            timestampMillis = System.currentTimeMillis() - (1000 * 60 * 45), // 45 mins ago
            status = PaymentStatus.RECEIVED,
            paymentMethod = PaymentMethod.STK_PUSH
        ),
        PurchaseRecord(
            id = "pur_102",
            offerId = "off_6",
            offerName = "20 SMS Bundle",
            allowance = "20 SMS",
            priceKsh = 5,
            recipientNumber = "0712 345 678",
            payerNumber = "0727 921 038",
            mpesaCode = "RHJ91823BX",
            timestampMillis = System.currentTimeMillis() - (1000 * 60 * 60 * 26), // Yesterday
            status = PaymentStatus.RECEIVED,
            paymentMethod = PaymentMethod.STK_PUSH
        )
    )

    private val _purchases = MutableStateFlow(initialPurchases)
    override val purchases: StateFlow<List<PurchaseRecord>> = _purchases.asStateFlow()

    private val initialNotifications = listOf(
        NotificationItem(
            id = "notif_1",
            title = "Your payment was received",
            body = "The 1 GB offer for 0727 921 038 was recorded.",
            timestampMillis = System.currentTimeMillis() - (1000 * 60 * 45),
            isRead = false
        ),
        NotificationItem(
            id = "notif_2",
            title = "You can buy this offer again",
            body = "Your 1 GB hourly offer is available whenever you need it.",
            timestampMillis = System.currentTimeMillis() - (1000 * 60 * 60 * 5),
            isRead = true
        ),
        NotificationItem(
            id = "notif_3",
            title = "New offer available",
            body = "Get 2 GB for KSh 110, valid for 24 hours.",
            timestampMillis = System.currentTimeMillis() - (1000 * 60 * 60 * 28),
            isRead = true
        ),
        NotificationItem(
            id = "notif_4",
            title = "My Bingwa update available",
            body = "Update for a faster, smoother experience.",
            timestampMillis = System.currentTimeMillis() - (1000 * 60 * 60 * 48),
            isRead = true
        )
    )

    private val _notifications = MutableStateFlow(initialNotifications)
    override val notifications: StateFlow<List<NotificationItem>> = _notifications.asStateFlow()

    private val _recentRecipients = MutableStateFlow(listOf("0727 921 038", "0712 345 678", "0722 000 111"))
    override val recentRecipients: StateFlow<List<String>> = _recentRecipients.asStateFlow()

    private val _devStkOutcome = MutableStateFlow(DevStkOutcome.SUCCESS)
    override val devStkOutcome: StateFlow<DevStkOutcome> = _devStkOutcome.asStateFlow()

    override fun updateProfile(name: String, primaryNumber: String) {
        _userProfile.update { it.copy(name = name, primaryNumber = primaryNumber) }
    }

    override fun setOnboardingCompleted(completed: Boolean) {
        _userProfile.update { it.copy(isOnboardingCompleted = completed) }
    }

    override fun setAppTheme(theme: AppThemeSetting) {
        _appTheme.value = theme
    }

    override fun toggleOfflineMode() {
        _isOffline.value = !_isOffline.value
    }

    override fun setOfflineMode(offline: Boolean) {
        _isOffline.value = offline
    }

    override fun setSearchQuery(query: String) {
        _filterState.update { it.copy(searchQuery = query) }
    }

    override fun setCategoryFilter(category: OfferCategory) {
        _filterState.update { it.copy(selectedCategory = category) }
    }

    override fun setFilterState(filter: OfferFilterState) {
        _filterState.value = filter
    }

    override fun clearFilters() {
        _filterState.value = OfferFilterState()
    }

    override fun toggleFavourite(offerId: String) {
        _offers.update { list ->
            list.map { offer ->
                if (offer.id == offerId) offer.copy(isFavourite = !offer.isFavourite) else offer
            }
        }
    }

    override fun setFavourite(offerId: String, isFavourite: Boolean) {
        _offers.update { list ->
            list.map { offer ->
                if (offer.id == offerId) offer.copy(isFavourite = isFavourite) else offer
            }
        }
    }

    override suspend fun refreshCatalogue() {
        _catalogueLoading.value = true
        delay(400) // Simulate a cached-first read; the cache is already warm.
        _offers.value = initialOffers.map { fresh ->
            // Preserve the customer's local favourite/bought-today state across a refresh.
            val current = _offers.value.find { it.id == fresh.id }
            if (current != null) fresh.copy(
                isFavourite = current.isFavourite,
                isBoughtToday = current.isBoughtToday
            ) else fresh
        }
        _catalogueLoading.value = false
    }

    override fun setDevStkOutcome(outcome: DevStkOutcome) {
        _devStkOutcome.value = outcome
    }

    override suspend fun executeMpesaStkPush(
        offer: OfferItem,
        recipientNumber: String,
        payerNumber: String
    ): PurchaseRecord {
        delay(1800) // Simulate M-Pesa STK push network processing
        val outcome = _devStkOutcome.value
        val recordId = "pur_" + UUID.randomUUID().toString().take(8)
        val mpesaCode = "R" + (100000..999999).random() + "MB"

        val status = when (outcome) {
            DevStkOutcome.SUCCESS -> PaymentStatus.RECEIVED
            DevStkOutcome.CANCELLED -> PaymentStatus.CANCELLED
            DevStkOutcome.FAILED -> PaymentStatus.FAILED
            DevStkOutcome.DELAYED -> PaymentStatus.WAITING_VERIFY
        }

        val record = PurchaseRecord(
            id = recordId,
            offerId = offer.id,
            offerName = offer.name,
            allowance = offer.allowance,
            priceKsh = offer.priceKsh,
            recipientNumber = recipientNumber,
            payerNumber = payerNumber,
            mpesaCode = if (status == PaymentStatus.RECEIVED) mpesaCode else "-",
            timestampMillis = System.currentTimeMillis(),
            status = status,
            paymentMethod = PaymentMethod.STK_PUSH
        )

        _purchases.update { listOf(record) + it }

        if (status == PaymentStatus.RECEIVED && offer.dailyRule == DailyRule.ONCE_PER_DAY) {
            _offers.update { list ->
                list.map { item ->
                    if (item.id == offer.id) item.copy(isBoughtToday = true) else item
                }
            }
        }

        if (status == PaymentStatus.RECEIVED) {
            val newNotif = NotificationItem(
                id = "notif_" + UUID.randomUUID().toString().take(6),
                title = "Your payment was received",
                body = "The ${offer.allowance} offer for $recipientNumber was recorded.",
                timestampMillis = System.currentTimeMillis(),
                isRead = false
            )
            _notifications.update { listOf(newNotif) + it }
        }

        // Add recipient to recent recipients if unique
        if (recipientNumber.isNotBlank() && !_recentRecipients.value.contains(recipientNumber)) {
            _recentRecipients.update { listOf(recipientNumber) + it }
        }

        return record
    }

    override suspend fun executeOfflinePayment(
        offer: OfferItem,
        recipientNumber: String,
        payerNumber: String,
        isTill: Boolean
    ): PurchaseRecord {
        delay(600)
        val recordId = "pur_" + UUID.randomUUID().toString().take(8)
        val record = PurchaseRecord(
            id = recordId,
            offerId = offer.id,
            offerName = offer.name,
            allowance = offer.allowance,
            priceKsh = offer.priceKsh,
            recipientNumber = recipientNumber,
            payerNumber = payerNumber,
            mpesaCode = "OFFLINE_PENDING",
            timestampMillis = System.currentTimeMillis(),
            status = PaymentStatus.WAITING_VERIFY,
            paymentMethod = if (isTill) PaymentMethod.TILL else PaymentMethod.PAYBILL
        )
        _purchases.update { listOf(record) + it }
        return record
    }

    override fun deletePurchaseRecord(recordId: String) {
        _purchases.update { list -> list.filterNot { it.id == recordId } }
    }

    override fun deletePurchaseRecords(recordIds: List<String>) {
        _purchases.update { list -> list.filterNot { recordIds.contains(it.id) } }
    }

    override fun undoDeletePurchaseRecord(record: PurchaseRecord) {
        _purchases.update { listOf(record) + it }
    }

    override fun markNotificationRead(id: String) {
        _notifications.update { list ->
            list.map { if (it.id == id) it.copy(isRead = true) else it }
        }
    }

    override fun markAllNotificationsRead() {
        _notifications.update { list -> list.map { it.copy(isRead = true) } }
    }

    override fun clearAllLocalData() {
        _userProfile.value = UserProfile()
        _purchases.value = emptyList()
        _offers.update { list -> list.map { it.copy(isFavourite = false, isBoughtToday = false) } }
        _notifications.value = emptyList()
        _recentRecipients.value = emptyList()
    }
}
