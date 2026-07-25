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
import com.example.core.notifications.ConnectionState
import com.example.core.payment.KenyanPhone
import com.example.core.payment.PaymentTxnState
import com.example.data.catalogue.RemoteCatalogueSource
import com.example.data.config.AppConfig
import com.example.data.config.RemoteConfigSource
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
import com.example.data.persistence.LocalStore
import com.example.data.persistence.PersistedState
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.util.UUID

/**
 * App-readable data source. State lives in [MutableStateFlow]s and — when a
 * [localStore] is injected (the running app always injects one) — is loaded from and
 * saved to on-device persistence, so name, profile, favourites, Activity,
 * notifications and any in-flight order survive process death. Unit tests inject no
 * store and run purely in memory.
 *
 * [gateway] handles online M-Pesa (real backend proxy in a configured build).
 * [fallbackGateway] is used when no backend is configured — a labelled simulation in
 * debug, or an honest-failing [UnavailablePaymentGateway] in release so a
 * misconfigured production build never fabricates a success. [configProvider] supplies
 * the offline Till/Paybill config.
 */
class FakeBingwaRepositoryImpl(
    gateway: PaymentGateway? = null,
    fallbackGateway: PaymentGateway? = null,
    private val configProvider: OfflinePaymentConfigProvider = CachedOfflineConfigProvider(),
    private val configSource: RemoteConfigSource? = null,
    private val catalogueSource: RemoteCatalogueSource? = null,
    private val localStore: LocalStore? = null
) : BingwaRepository {

    // Fallback used when no real backend is configured. MainActivity injects a
    // labelled simulation for debug builds and an UnavailablePaymentGateway for
    // release (which fails honestly rather than faking a success). With no injection
    // (unit tests) it defaults to a dev simulation wired to the DevStkOutcome switch.
    private val fallback: PaymentGateway = fallbackGateway ?: SimulatedPaymentGateway(
        terminalOutcome = { devOutcomeToState(_devStkOutcome.value) }
    )

    // Buy-for-myself uses the real backend gateway when one is configured.
    private val selfGateway: PaymentGateway = gateway ?: fallback

    // Buy-for-another now also goes through the real backend when configured: the
    // request carries forSelf=false, so the backend charges the payer (Paybill, with
    // the recipient's number as the account reference) and, on success, sends the
    // fulfilment operator a mocked M-Pesa SMS naming the RECIPIENT (server callback.php,
    // per docs/"Buy For Another Number - Implementation Spec.md"). Falls back to the
    // simulation only when no backend is configured.
    private val anotherNumberGateway: PaymentGateway = gateway ?: fallback

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

    // Real My Bingwa catalogue. name = allowance, validity = the duration shown
    // next to it on the card, validityBand = the Offers filter band, dailyRule =
    // the "Buy once a day" / "Buy many times" tag.
    private fun dataOffer(
        id: String, name: String, validity: String, band: String, price: Int,
        once: Boolean, category: OfferCategory = OfferCategory.DATA, favourite: Boolean = false
    ) = OfferItem(
        id = id,
        name = name,
        allowance = name,
        priceKsh = price,
        validity = validity,
        validityBand = band,
        category = category,
        dailyRule = if (once) DailyRule.ONCE_PER_DAY else DailyRule.BUY_AGAIN_TODAY,
        isFavourite = favourite,
        description = "$name of ${category.label.lowercase()} valid $validity."
    )

    private val initialOffers = listOf(
        // Data
        dataOffer("data_1", "1GB", "1 Hr", "Hourly", 19, once = true),
        dataOffer("data_2", "250MB", "24 Hrs", "Daily", 20, once = true),
        dataOffer("data_3", "1.5GB", "3 Hrs", "Hourly", 50, once = true),
        dataOffer("data_4", "1.25GB", "Midnight", "Daily", 55, once = true),
        dataOffer("data_5", "1GB", "24 Hrs", "Daily", 95, once = true),
        dataOffer("data_6", "2GB", "24 Hrs", "Daily", 110, once = false, favourite = true),
        dataOffer("data_7", "350MB", "7 days", "Weekly", 49, once = true),
        dataOffer("data_8", "2.5GB", "7 days", "Weekly", 300, once = true),
        dataOffer("data_9", "6GB", "7 days", "Weekly", 700, once = true),
        dataOffer("data_10", "1.2GB", "30 days", "Monthly", 250, once = true),
        dataOffer("data_11", "2.5GB", "30 days", "Monthly", 500, once = true),
        dataOffer("data_12", "10GB", "30 days", "Monthly", 1000, once = true),
        dataOffer("data_13", "8GB + 400 Min", "30 days", "Monthly", 1005, once = true),
        // SMS
        dataOffer("sms_1", "10 SMS", "24 Hrs", "Daily", 5, once = false, category = OfferCategory.SMS),
        dataOffer("sms_2", "200 SMS", "24 Hrs", "Daily", 10, once = false, category = OfferCategory.SMS, favourite = true),
        dataOffer("sms_3", "1,000 SMS", "7 days", "Weekly", 30, once = false, category = OfferCategory.SMS),
        dataOffer("sms_4", "1,500 SMS", "30 days", "Monthly", 101, once = false, category = OfferCategory.SMS),
        dataOffer("sms_5", "3,500 SMS", "30 days", "Monthly", 201, once = false, category = OfferCategory.SMS),
        // Minutes
        dataOffer("min_1", "20 Min", "Midnight", "Daily", 22, once = false, category = OfferCategory.MINUTES),
        dataOffer("min_2", "35 Min", "2 Hrs", "Hourly", 23, once = false, category = OfferCategory.MINUTES),
        dataOffer("min_3", "45 Min", "3 Hrs", "Hourly", 24, once = false, category = OfferCategory.MINUTES),
        dataOffer("min_4", "50 Min", "Midnight", "Daily", 48, once = false, category = OfferCategory.MINUTES, favourite = true),
        dataOffer("min_5", "250 Min", "7 days", "Weekly", 205, once = false, category = OfferCategory.MINUTES),
        dataOffer("min_6", "100 Min", "Midnight", "Daily", 105, once = false, category = OfferCategory.MINUTES),
        dataOffer("min_7", "300 Min", "30 days", "Monthly", 499, once = false, category = OfferCategory.MINUTES),
        dataOffer("min_8", "800 Min", "30 days", "Monthly", 950, once = false, category = OfferCategory.MINUTES),
        // Special
        dataOffer("spec_1", "1GB", "1 Hr", "Hourly", 21, once = true, category = OfferCategory.SPECIAL),
        dataOffer("spec_2", "1.5GB", "3 Hrs", "Hourly", 51, once = true, category = OfferCategory.SPECIAL),
        dataOffer("spec_3", "2GB", "24 Hrs", "Daily", 110, once = false, category = OfferCategory.SPECIAL)
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
            id = "promo_8gb_400min",
            kind = PromotionKind.OFFER,
            tag = "HOT DEAL",
            headline = "8GB + 400 Min",
            subhead = "The monthly mega bundle · 30 days for KSh 1,005",
            ctaLabel = "Buy now",
            accent = PromotionAccent.GREEN,
            linkedOfferId = "data_13",
            priorityWeight = 100
        ),
        Promotion(
            id = "promo_10gb_month",
            kind = PromotionKind.OFFER,
            tag = "BEST VALUE",
            headline = "10 GB for KSh 1,000",
            subhead = "Valid 30 days · stay online all month",
            ctaLabel = "Buy now",
            accent = PromotionAccent.NAVY,
            linkedOfferId = "data_12",
            priorityWeight = 90
        ),
        Promotion(
            id = "promo_6gb_week",
            kind = PromotionKind.OFFER,
            tag = "POPULAR",
            headline = "6 GB Weekly",
            subhead = "A full week of data for KSh 700",
            ctaLabel = "Buy now",
            accent = PromotionAccent.BLUE,
            linkedOfferId = "data_9",
            priorityWeight = 70
        ),
        Promotion(
            id = "promo_2gb_daily",
            kind = PromotionKind.OFFER,
            tag = "FAVOURITE",
            headline = "2GB for KSh 110",
            subhead = "24 hours of data — buy it as many times as you like",
            ctaLabel = "Buy now",
            accent = PromotionAccent.ORANGE,
            linkedOfferId = "data_6",
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
            offerId = "data_1",
            offerName = "1GB",
            allowance = "1GB",
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
            offerId = "sms_1",
            offerName = "10 SMS",
            allowance = "10 SMS",
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

    private val _connectionState = MutableStateFlow(ConnectionState.NONE)
    override val connectionState: StateFlow<ConnectionState> = _connectionState.asStateFlow()

    // Seller config: seeded from the last sync (or defaults) so it is available
    // offline immediately; refreshed by syncRemoteConfig() when online.
    private val _appConfig = MutableStateFlow(configSource?.cached() ?: AppConfig.DEFAULT)
    override val appConfig: StateFlow<AppConfig> = _appConfig.asStateFlow()

    // --- Real on-device persistence (installation-local; no cloud/account) ---------
    // When a LocalStore is injected, state is loaded from disk on start and every
    // mutation re-saves the whole (small) snapshot, so name, profile, favourites,
    // Activity, notifications and any in-flight order survive process death. Unit
    // tests inject no store and behave exactly as the old in-memory repository.
    private val ioScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val saveTick = MutableStateFlow(0L)

    init {
        val store = localStore
        if (store != null) {
            ioScope.launch {
                restoreFromDisk(store)
                // Persist on every subsequent mutation (StateFlow conflates bursts).
                saveTick.collect { store.save(currentSnapshot()) }
            }
        }
    }

    private suspend fun restoreFromDisk(store: LocalStore) {
        val s = store.load() ?: return
        // A never-saved device keeps the seeded demo content; only override once the
        // customer has real saved state (also lets Clear local data persist as empty).
        if (!s.initialized) return
        s.profile?.let { _userProfile.value = it }
        s.theme?.let { name ->
            runCatching { AppThemeSetting.valueOf(name) }.getOrNull()?.let { _appTheme.value = it }
        }
        _purchases.value = s.purchases
        _notifications.value = s.notifications
        _recentRecipients.value = s.recentRecipients
        val favs = s.favouriteIds.toSet()
        val bought = s.boughtTodayIds.toSet()
        _offers.update { list ->
            list.map { it.copy(isFavourite = favs.contains(it.id), isBoughtToday = bought.contains(it.id)) }
        }
        // Safe process-death payment restore: an order still in-flight when the app
        // died is settled to an honest "Waiting to verify" — never silently lost and
        // never re-charged — so the customer can follow it up in Activity.
        s.activeOrder?.let { restoreUnfinishedOrder(it) }
    }

    private fun restoreUnfinishedOrder(order: ActiveOrder) {
        _activeOrder.value = null
        // Idempotent: if it already produced a record, do nothing.
        if (_purchases.value.any { it.clientRequestId == order.clientRequestId }) return
        val record = PurchaseRecord(
            id = "pur_" + UUID.randomUUID().toString().take(8),
            offerId = order.offerId,
            offerName = order.offerName,
            allowance = order.offerName,
            priceKsh = order.priceKsh,
            recipientNumber = order.recipientNumber,
            payerNumber = order.payerNumber,
            mpesaCode = "-",
            timestampMillis = System.currentTimeMillis(),
            status = PaymentStatus.WAITING_VERIFY,
            paymentMethod = PaymentMethod.STK_PUSH,
            clientRequestId = order.clientRequestId,
            orderReference = order.orderReference ?: ""
        )
        _purchases.update { listOf(record) + it }
    }

    private fun currentSnapshot(): PersistedState = PersistedState(
        profile = _userProfile.value,
        theme = _appTheme.value.name,
        favouriteIds = _offers.value.filter { it.isFavourite }.map { it.id },
        boughtTodayIds = _offers.value.filter { it.isBoughtToday }.map { it.id },
        purchases = _purchases.value,
        notifications = _notifications.value,
        recentRecipients = _recentRecipients.value,
        activeOrder = _activeOrder.value,
        initialized = true
    )

    /** Signal a save of the current snapshot (no-op when persistence is disabled). */
    private fun persist() {
        if (localStore != null) saveTick.value = saveTick.value + 1
    }

    override fun updateProfile(name: String, primaryNumber: String) {
        _userProfile.update { it.copy(name = name, primaryNumber = primaryNumber) }
        persist()
    }

    override fun setNotificationsEnabled(enabled: Boolean) {
        _userProfile.update { it.copy(notificationsEnabled = enabled) }
        persist()
    }

    override fun setSmsAlertsEnabled(enabled: Boolean) {
        _userProfile.update { it.copy(smsAlertsEnabled = enabled) }
        persist()
    }

    override fun setOnboardingCompleted(completed: Boolean) {
        _userProfile.update { it.copy(isOnboardingCompleted = completed) }
        persist()
    }

    override fun setAppTheme(theme: AppThemeSetting) {
        _appTheme.value = theme
        persist()
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
        persist()
    }

    override fun setFavourite(offerId: String, isFavourite: Boolean) {
        _offers.update { list ->
            list.map { offer ->
                if (offer.id == offerId) offer.copy(isFavourite = isFavourite) else offer
            }
        }
        persist()
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
            isForSelf = isForSelf,
            state = PaymentTxnState.DRAFT
        )
        // Persist the in-flight order so a process death mid-payment can be restored.
        persist()

        val request = StkPushRequest(
            offerId = offer.id,
            amountKsh = offer.priceKsh,
            payerMsisdn = payerMsisdn,
            recipientMsisdn = recipientMsisdn,
            clientRequestId = clientRequestId,
            forSelf = isForSelf
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

        // Poll until a terminal result or the polling window is exhausted. Wait
        // between polls so the real backend/Daraja query isn't hammered while the
        // customer is entering their PIN (~POLL_INTERVAL_MILLIS x MAX_STATUS_POLLS
        // total). Under test the delay is virtual (runTest advances it instantly).
        var attempts = 0
        while (!result.state.isTerminal && attempts < MAX_STATUS_POLLS) {
            attempts++
            delay(POLL_INTERVAL_MILLIS)
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
        persist()
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

    override fun offlineConfig(): OfflinePaymentConfig? {
        // Eligibility (expiry/ambiguity) still comes from the signed provider, but the
        // displayed Till/Paybill are the server-synced values (always available offline).
        if (configProvider.load(System.currentTimeMillis()) !is OfflineConfigResult.Valid) return null
        val cfg = _appConfig.value
        return OfflinePaymentConfig(
            tillNumber = cfg.tillNumber,
            paybillNumber = cfg.paybillNumber,
            issuedAtMillis = 0L,
            expiresAtMillis = Long.MAX_VALUE,
            signatureValid = true
        )
    }

    override fun clearActiveOrder() {
        _activeOrder.value = null
        persist()
    }

    override fun deletePurchaseRecord(recordId: String) {
        _purchases.update { list -> list.filterNot { it.id == recordId } }
        persist()
    }

    override fun deletePurchaseRecords(recordIds: List<String>) {
        _purchases.update { list -> list.filterNot { recordIds.contains(it.id) } }
        persist()
    }

    override fun undoDeletePurchaseRecord(record: PurchaseRecord) {
        _purchases.update { listOf(record) + it }
        persist()
    }

    override fun setConnectionState(state: ConnectionState) {
        _connectionState.value = state
        // Real connectivity is now the source of truth for the offline flag: no
        // transport at all = offline. Any Wi-Fi/cellular link = online (Phase 6).
        _isOffline.value = (state == ConnectionState.NONE)
    }

    override suspend fun syncRemoteConfig() {
        configSource?.fetch()?.let { fresh -> _appConfig.value = fresh }
    }

    override suspend fun syncCatalogue() {
        val fresh = catalogueSource?.fetch() ?: return
        // Preserve the customer's local favourite/bought-today state across a sync.
        _offers.value = fresh.map { server ->
            val current = _offers.value.find { it.id == server.id }
            if (current != null) {
                server.copy(isFavourite = current.isFavourite, isBoughtToday = current.isBoughtToday)
            } else {
                server
            }
        }
        persist()
    }

    override fun onBundleDeliveryDetected(category: OfferCategory) {
        // Reconcile against the newest RECEIVED purchase of this category that is
        // not already carrier-confirmed. If none matches (e.g. a gift the user
        // never paid for here) we simply do not fabricate a record.
        val target = _purchases.value.firstOrNull { record ->
            record.status == PaymentStatus.RECEIVED &&
                !record.isDeliveryConfirmed &&
                recordCategory(record) == category
        } ?: return

        _purchases.update { list ->
            list.map { if (it.id == target.id) it.copy(isDeliveryConfirmed = true) else it }
        }

        // Quiet in-app note only — attributed to Safaricom, no "we delivered"
        // claim (CLAUDE.md §7). No loud system notification here; delivery is
        // never shouted every time.
        val what = categoryNoun(category)
        val newNotif = NotificationItem(
            id = "notif_" + UUID.randomUUID().toString().take(6),
            title = "Safaricom confirms your bundle",
            body = "Safaricom has sent your $what. Dial *144# or *544# to check your balance.",
            timestampMillis = System.currentTimeMillis(),
            isRead = false,
            deepLinkRoute = "activity"
        )
        _notifications.update { listOf(newNotif) + it }
        persist()
    }

    override fun onLowBalanceDetected(category: OfferCategory) {
        // §8-allowed language only: surface more offers to buy — never claim the
        // customer is "running out" or "needs" more data.
        val what = categoryNoun(category)
        val newNotif = NotificationItem(
            id = "notif_" + UUID.randomUUID().toString().take(6),
            title = "More $what offers for you",
            body = "Top up your $what with these deals whenever you are ready.",
            timestampMillis = System.currentTimeMillis(),
            isRead = false,
            deepLinkRoute = "offers"
        )
        _notifications.update { listOf(newNotif) + it }
        persist()
    }

    /**
     * Resolve a purchase to its offer category for SMS reconciliation. Prefers
     * the live catalogue (by offerId); falls back to a text heuristic on the
     * offer name/allowance for records whose offer is no longer catalogued.
     */
    private fun recordCategory(record: PurchaseRecord): OfferCategory {
        _offers.value.firstOrNull { it.id == record.offerId }?.let { return it.category }
        val text = (record.offerName + " " + record.allowance).lowercase()
        return when {
            "sms" in text -> OfferCategory.SMS
            "min" in text || "voice" in text || "call" in text -> OfferCategory.MINUTES
            "gb" in text || "mb" in text || "data" in text -> OfferCategory.DATA
            else -> OfferCategory.DATA
        }
    }

    /** Human noun for a category, matching AppNotifier's wording. */
    private fun categoryNoun(category: OfferCategory): String = when (category) {
        OfferCategory.DATA -> "data"
        OfferCategory.SMS -> "SMS"
        OfferCategory.MINUTES -> "minutes"
        OfferCategory.SPECIAL -> "bundle"
        OfferCategory.ALL, OfferCategory.FAVOURITES -> "bundle"
    }

    override fun markNotificationRead(id: String) {
        _notifications.update { list ->
            list.map { if (it.id == id) it.copy(isRead = true) else it }
        }
        persist()
    }

    override fun markAllNotificationsRead() {
        _notifications.update { list -> list.map { it.copy(isRead = true) } }
        persist()
    }

    override fun deleteNotification(id: String) {
        _notifications.update { list -> list.filterNot { it.id == id } }
        persist()
    }

    override fun clearAllNotifications() {
        _notifications.value = emptyList()
        persist()
    }

    override fun clearAllLocalData() {
        _userProfile.value = UserProfile()
        _purchases.value = emptyList()
        _offers.update { list -> list.map { it.copy(isFavourite = false, isBoughtToday = false) } }
        _notifications.value = emptyList()
        _recentRecipients.value = emptyList()
        _activeOrder.value = null
        persist()
    }

    private companion object {
        /** Max status polls before falling back to an honest "still checking" result. */
        const val MAX_STATUS_POLLS = 10

        /** Wait between STK status polls (real backend + Daraja query take seconds). */
        const val POLL_INTERVAL_MILLIS = 3000L
    }
}
