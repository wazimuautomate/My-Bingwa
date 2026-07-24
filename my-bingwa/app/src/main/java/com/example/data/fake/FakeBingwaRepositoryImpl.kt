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
import com.example.core.payment.KenyanPhone
import com.example.core.payment.PaymentTxnState
import com.example.data.payment.ActiveOrder
import com.example.data.payment.CachedOfflineConfigProvider
import com.example.data.payment.OfflineConfigResult
import com.example.data.payment.OfflineEligibility
import com.example.data.payment.OfflineEligibilityChecker
import com.example.data.payment.OfflinePaymentConfig
import com.example.data.payment.OfflinePaymentConfigProvider
import com.example.data.payment.PaymentGateway
import com.example.data.payment.PaymentTransportException
import com.example.data.payment.SimulatedPaymentGateway
import com.example.data.payment.StkPushRequest
import com.example.data.payment.StkStatusQuery
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import java.util.UUID

/**
 * In-memory app-readable data source. [gateway] handles online M-Pesa (real backend
 * proxy in a configured build, a labelled simulation otherwise); [configProvider]
 * supplies the signed offline Till/Paybill config. When no gateway is injected, a
 * dev [SimulatedPaymentGateway] is wired to [devStkOutcome] so every result screen
 * can be exercised on a phone.
 */
class FakeBingwaRepositoryImpl(
    gateway: PaymentGateway? = null,
    private val configProvider: OfflinePaymentConfigProvider = CachedOfflineConfigProvider()
) : BingwaRepository {

    // Buy-for-myself gateway: the real backend proxy when configured, otherwise a
    // dev simulation wired to the DevStkOutcome switch.
    private val selfGateway: PaymentGateway = gateway ?: SimulatedPaymentGateway(
        terminalOutcome = { devOutcomeToState(_devStkOutcome.value) }
    )

    // Buy-for-another stays mocked in this phase (product decision), so it always
    // uses a simulation even once the backend base URL is set for self-purchases.
    private val anotherNumberGateway: PaymentGateway = SimulatedPaymentGateway(
        terminalOutcome = { devOutcomeToState(_devStkOutcome.value) }
    )

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

    private val _activeOrder = MutableStateFlow<ActiveOrder?>(null)
    override val activeOrder: StateFlow<ActiveOrder?> = _activeOrder.asStateFlow()

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

    private fun devOutcomeToState(outcome: DevStkOutcome): PaymentTxnState = when (outcome) {
        DevStkOutcome.SUCCESS -> PaymentTxnState.PAYMENT_CONFIRMED
        DevStkOutcome.CANCELLED -> PaymentTxnState.CANCELLED
        DevStkOutcome.FAILED -> PaymentTxnState.PAYMENT_FAILED
        // A delayed prompt never reaches a terminal result within the polling
        // window; the honest outcome is "still checking" (Waiting to verify).
        DevStkOutcome.DELAYED -> PaymentTxnState.AWAITING_APPROVAL
    }

    override suspend fun executeMpesaStkPush(
        offer: OfferItem,
        recipientNumber: String,
        payerNumber: String,
        clientRequestId: String,
        isForSelf: Boolean
    ): PurchaseRecord {
        // Idempotency: a repeated clientRequestId (double-tap, retry) returns the
        // existing record instead of charging again (Plan.md §API idempotency).
        _purchases.value.firstOrNull { it.clientRequestId == clientRequestId }?.let { return it }

        val gatewayForRoute = if (isForSelf) selfGateway else anotherNumberGateway

        val payerMsisdn = KenyanPhone.toMsisdn(payerNumber)
        val recipientMsisdn = KenyanPhone.toMsisdn(recipientNumber)
        if (payerMsisdn == null || recipientMsisdn == null) {
            return settle(
                offer, recipientNumber, payerNumber, clientRequestId,
                PaymentStatus.FAILED, PaymentMethod.STK_PUSH, mpesaCode = "-", orderReference = ""
            )
        }

        _activeOrder.value = ActiveOrder(
            clientRequestId = clientRequestId,
            offerId = offer.id,
            offerName = offer.name,
            priceKsh = offer.priceKsh,
            recipientNumber = recipientNumber,
            payerNumber = payerNumber,
            isForSelf = recipientNumber == payerNumber,
            state = PaymentTxnState.DRAFT
        )

        val request = StkPushRequest(
            offerId = offer.id,
            amountKsh = offer.priceKsh,
            payerMsisdn = payerMsisdn,
            recipientMsisdn = recipientMsisdn,
            clientRequestId = clientRequestId
        )

        var result = try {
            gatewayForRoute.initiateStkPush(request)
        } catch (e: PaymentTransportException) {
            _activeOrder.value = null
            return settle(
                offer, recipientNumber, payerNumber, clientRequestId,
                PaymentStatus.FAILED, PaymentMethod.STK_PUSH, mpesaCode = "-", orderReference = ""
            )
        }
        _activeOrder.update { it?.copy(state = result.state, orderReference = result.orderReference) }

        // Poll until a terminal result or the polling window is exhausted.
        var attempts = 0
        while (!result.state.isTerminal && attempts < MAX_STATUS_POLLS) {
            attempts++
            result = try {
                gatewayForRoute.queryStatus(StkStatusQuery(clientRequestId, result.orderReference))
            } catch (e: PaymentTransportException) {
                break
            }
            _activeOrder.update { it?.copy(state = result.state, orderReference = result.orderReference) }
        }

        val status = if (result.state.isTerminal) {
            result.state.toRecordStatus() ?: PaymentStatus.WAITING_VERIFY
        } else {
            // Non-terminal after polling → honest "still checking" (Waiting to verify).
            PaymentStatus.WAITING_VERIFY
        }
        _activeOrder.value = null

        return settle(
            offer, recipientNumber, payerNumber, clientRequestId,
            status, PaymentMethod.STK_PUSH,
            mpesaCode = result.mpesaReceipt?.takeIf { status == PaymentStatus.RECEIVED } ?: "-",
            orderReference = result.orderReference ?: ""
        )
    }

    override suspend fun executeOfflinePayment(
        offer: OfferItem,
        recipientNumber: String,
        payerNumber: String,
        isTill: Boolean,
        receipt: String?
    ): PurchaseRecord {
        delay(400)
        val trimmed = receipt?.trim().orEmpty()
        // With a receipt → Waiting to verify; without one → Payment not confirmed.
        val status = if (trimmed.isNotEmpty()) PaymentStatus.WAITING_VERIFY else PaymentStatus.NOT_CONFIRMED
        return settle(
            offer, recipientNumber, payerNumber,
            clientRequestId = "off_" + UUID.randomUUID().toString().take(8),
            status = status,
            method = if (isTill) PaymentMethod.TILL else PaymentMethod.PAYBILL,
            mpesaCode = trimmed.ifEmpty { "OFFLINE_PENDING" },
            orderReference = ""
        )
    }

    /** Builds, persists and cross-updates a settled record (bought-today, notifications, recents). */
    private fun settle(
        offer: OfferItem,
        recipientNumber: String,
        payerNumber: String,
        clientRequestId: String,
        status: PaymentStatus,
        method: PaymentMethod,
        mpesaCode: String,
        orderReference: String
    ): PurchaseRecord {
        val record = PurchaseRecord(
            id = "pur_" + UUID.randomUUID().toString().take(8),
            offerId = offer.id,
            offerName = offer.name,
            allowance = offer.allowance,
            priceKsh = offer.priceKsh,
            recipientNumber = recipientNumber,
            payerNumber = payerNumber,
            mpesaCode = mpesaCode,
            timestampMillis = System.currentTimeMillis(),
            status = status,
            paymentMethod = method,
            clientRequestId = clientRequestId,
            orderReference = orderReference
        )
        _purchases.update { listOf(record) + it }

        if (status == PaymentStatus.RECEIVED && offer.dailyRule == DailyRule.ONCE_PER_DAY) {
            _offers.update { list ->
                list.map { item -> if (item.id == offer.id) item.copy(isBoughtToday = true) else item }
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

        if (recipientNumber.isNotBlank() && !_recentRecipients.value.contains(recipientNumber)) {
            _recentRecipients.update { listOf(recipientNumber) + it }
        }
        return record
    }

    override fun offlineEligibility(offer: OfferItem, isForSelf: Boolean): OfflineEligibility =
        OfflineEligibilityChecker.check(
            offer = offer,
            isForSelf = isForSelf,
            catalogue = _offers.value,
            config = configProvider.load(System.currentTimeMillis()),
            nowMillis = System.currentTimeMillis()
        )

    override fun offlineConfig(): OfflinePaymentConfig? =
        (configProvider.load(System.currentTimeMillis()) as? OfflineConfigResult.Valid)?.config

    override fun clearActiveOrder() {
        _activeOrder.value = null
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
        _activeOrder.value = null
    }

    private companion object {
        /** Max status polls before falling back to an honest "still checking" result. */
        const val MAX_STATUS_POLLS = 6
    }
}
