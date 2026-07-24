package com.example

import android.Manifest
import android.content.Intent
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.Scaffold
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.example.core.model.AppThemeSetting
import com.example.core.model.OfferItem
import com.example.core.model.Promotion
import com.example.core.model.PromotionKind
import com.example.core.notifications.AppNotifier
import com.example.core.notifications.ConnectionState
import com.example.core.notifications.ConnectivityObserver
import com.example.core.notifications.NotificationChannels
import com.example.core.notifications.SmsSignal
import com.example.core.ui.MyBingwaBottomNav
import com.example.data.catalogue.AndroidRemoteCatalogueSource
import com.example.data.config.AndroidRemoteConfigSource
import com.example.data.fake.BingwaRepository
import com.example.data.fake.FakeBingwaRepositoryImpl
import com.example.data.payment.PaymentGatewayProvider
import com.example.feature.activity.ActivityScreen
import com.example.feature.help.HelpScreen
import com.example.feature.home.CatalogueViewModel
import com.example.feature.home.HomeScreen
import com.example.feature.notifications.NotificationsSheet
import com.example.feature.offers.OffersScreen
import com.example.feature.onboarding.OnboardingScreen
import com.example.feature.purchase.PurchaseBottomSheet
import com.example.feature.settings.SettingsScreen
import com.example.notifications.SmsSignalBus
import com.example.ui.theme.MyBingwaTheme
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {

    // Buy-for-myself uses the real backend proxy when a base URL is configured
    // (BuildConfig.PAYMENTS_BASE_URL, a non-secret value); otherwise a clearly
    // labelled local simulation. Daraja credentials never live in this app.
    // `by lazy` so applicationContext is attached before the RemoteConfigSource
    // (SharedPreferences) is built — property init runs before the base context.
    private val repository: BingwaRepository by lazy {
        val backendConfigured = PaymentGatewayProvider.isBackendConfigured(BuildConfig.PAYMENTS_BASE_URL)
        FakeBingwaRepositoryImpl(
            gateway = if (backendConfigured) {
                PaymentGatewayProvider.create(
                    baseUrl = BuildConfig.PAYMENTS_BASE_URL,
                    appKey = BuildConfig.PAYMENTS_APP_KEY,
                    debugLogging = BuildConfig.DEBUG
                )
            } else {
                null
            },
            // Seller Till/Paybill/support are synced from the server but always
            // cached for offline use. Null (no base URL) → baked-in defaults only.
            configSource = if (backendConfigured) {
                AndroidRemoteConfigSource(
                    context = applicationContext,
                    baseUrl = BuildConfig.PAYMENTS_BASE_URL,
                    appKey = BuildConfig.PAYMENTS_APP_KEY,
                    enableLogging = BuildConfig.DEBUG
                )
            } else {
                null
            },
            // Offers are synced from the server when online; the bundled catalogue
            // is the guaranteed offline base (server is only for syncing).
            catalogueSource = if (backendConfigured) {
                AndroidRemoteCatalogueSource(
                    baseUrl = BuildConfig.PAYMENTS_BASE_URL,
                    appKey = BuildConfig.PAYMENTS_APP_KEY,
                    enableLogging = BuildConfig.DEBUG
                )
            } else {
                null
            }
        )
    }

    // Pending notification-tap deep-link route (AppNotifier.EXTRA_DEEP_LINK_ROUTE).
    // Set from the launch intent and from onNewIntent (singleTop re-use); the
    // composable observes it, navigates, then clears it.
    private val deepLinkRoute = MutableStateFlow<String?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        // Install the system splash before super/setContent so the branded
        // launch mark shows on cold start, then hands off to the app theme.
        installSplashScreen()
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        // Register the notification channels early (guards API < 26 internally).
        NotificationChannels.createChannels(this)

        // A cold launch from a notification tap carries the route here.
        deepLinkRoute.value = intent?.getStringExtra(AppNotifier.EXTRA_DEEP_LINK_ROUTE)

        setContent {
            val appTheme by repository.appTheme.collectAsState()
            val isOffline by repository.isOffline.collectAsState()
            val userProfile by repository.userProfile.collectAsState()

            val darkTheme = when (appTheme) {
                AppThemeSetting.SYSTEM -> isSystemInDarkTheme()
                AppThemeSetting.LIGHT -> false
                AppThemeSetting.DARK -> true
            }

            MyBingwaTheme(darkTheme = darkTheme) {
                MyBingwaApp(
                    repository = repository,
                    startOnboarding = !userProfile.isOnboardingCompleted,
                    deepLinkRoute = deepLinkRoute.asStateFlow(),
                    onConsumeDeepLink = { deepLinkRoute.value = null }
                )
            }
        }
    }

    // singleTop re-uses this activity for a notification tap while it is running;
    // capture the fresh route so the composable can deep-link.
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        deepLinkRoute.value = intent.getStringExtra(AppNotifier.EXTRA_DEEP_LINK_ROUTE)
    }
}

@Composable
fun MyBingwaApp(
    repository: BingwaRepository,
    startOnboarding: Boolean,
    deepLinkRoute: StateFlow<String?> = MutableStateFlow(null),
    onConsumeDeepLink: () -> Unit = {}
) {
    val navController = rememberNavController()
    val scope = rememberCoroutineScope()
    val context = LocalContext.current
    val appContext = context.applicationContext

    // Notification + connectivity framework (constructed manually — no Hilt).
    val appNotifier = remember { AppNotifier(appContext) }
    val connectivityObserver = remember { ConnectivityObserver(appContext) }

    // Feed observed connectivity into the repository (sets the offline flag) and,
    // whenever we're online, sync the seller config (Till/Paybill/support) from the
    // server so those details stay fresh — while remaining cached for offline use.
    LaunchedEffect(connectivityObserver) {
        connectivityObserver.observe().collect { state ->
            repository.setConnectionState(state)
            if (state != ConnectionState.NONE) {
                repository.syncRemoteConfig()
                repository.syncCatalogue()
            }
        }
    }

    // React to Safaricom SMS signals surfaced by SmsDeliveryReceiver via the bus.
    // Delivery is reconciled quietly (in-app + Activity, no loud system post);
    // low-balance adds an in-app offers suggestion and a quiet system nudge.
    LaunchedEffect(Unit) {
        SmsSignalBus.signals.collect { signal ->
            when (signal) {
                is SmsSignal.DeliveryDetected ->
                    repository.onBundleDeliveryDetected(signal.category)
                is SmsSignal.LowBalanceDetected -> {
                    repository.onLowBalanceDetected(signal.category)
                    appNotifier.postLowBalanceSuggestion(signal.category)
                }
            }
        }
    }

    // Runtime permission launchers (Android 13+ POST_NOTIFICATIONS, RECEIVE_SMS).
    // Settings shows the in-app rationale first, then invokes these.
    val notificationPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { /* result reflected implicitly: AppNotifier no-ops without the grant. */ }

    val smsPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { /* granted → SmsDeliveryReceiver starts receiving; denied → stays silent. */ }

    val requestNotificationPermission: () -> Unit = {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
        // Below API 33 the permission is granted at install time; nothing to ask.
    }
    val requestSmsPermission: () -> Unit = {
        smsPermissionLauncher.launch(Manifest.permission.RECEIVE_SMS)
    }

    // Reduced-motion proxy: honour the device animator-duration-scale = 0 setting
    // (design.md §11 "Respect Android reduced-motion settings").
    val reducedMotion = remember {
        Settings.Global.getFloat(context.contentResolver, Settings.Global.ANIMATOR_DURATION_SCALE, 1f) == 0f
    }

    val catalogueViewModel: CatalogueViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T =
                CatalogueViewModel(repository) as T
        }
    )

    val homeUiState by catalogueViewModel.homeUiState.collectAsState()
    val offersUiState by catalogueViewModel.offersUiState.collectAsState()

    val userProfile by repository.userProfile.collectAsState()
    val appTheme by repository.appTheme.collectAsState()
    val isOffline by repository.isOffline.collectAsState()
    val offers by repository.offers.collectAsState()
    val purchases by repository.purchases.collectAsState()
    val notifications by repository.notifications.collectAsState()
    val recentRecipients by repository.recentRecipients.collectAsState()
    val appConfig by repository.appConfig.collectAsState()

    // Hoisted so list position and filters survive tab switches (design.md §14.4).
    val homeListState = rememberLazyListState()
    val offersListState = rememberLazyListState()

    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route ?: if (startOnboarding) "onboarding" else "home"

    var activeOfferForPurchase by remember { mutableStateOf<OfferItem?>(null) }
    var prefilledReportRef by remember { mutableStateOf<String?>(null) }
    // Notification centre is an in-app slide-up overlay, not a standalone route,
    // so the bottom navigation stays visible behind it.
    var showNotifications by remember { mutableStateOf(false) }

    // Notification-tap deep-link: navigate to a real tab, open the notification
    // centre for "notifications", ignore anything unknown, then consume it so it
    // does not re-fire on recomposition.
    val pendingDeepLink by deepLinkRoute.collectAsState()
    LaunchedEffect(pendingDeepLink) {
        val route = pendingDeepLink ?: return@LaunchedEffect
        when (route) {
            "home", "offers", "activity", "help", "settings" -> {
                navController.navigate(route) {
                    popUpTo("home") { saveState = true }
                    launchSingleTop = true
                    restoreState = true
                }
            }
            "notifications" -> showNotifications = true
            else -> { /* unknown route — ignore */ }
        }
        onConsumeDeepLink()
    }

    val showBottomBar = currentRoute in listOf("home", "offers", "activity", "help", "settings")

    // Resolve details/purchase offers against the live catalogue so favourite
    // toggles inside a sheet stay reflected.
    fun liveOffer(offer: OfferItem): OfferItem = offers.find { it.id == offer.id } ?: offer

    val onUndoFavourite: (String) -> Unit = { id -> repository.setFavourite(id, true) }

    val onPromotionAction: (Promotion) -> Unit = { promo ->
        when (promo.kind) {
            PromotionKind.OFFER -> {
                val target = promo.linkedOfferId?.let { id -> offers.find { it.id == id } }
                if (target != null) activeOfferForPurchase = target
            }
            PromotionKind.ANNOUNCEMENT -> {
                promo.linkedCategory?.let { repository.setCategoryFilter(it) }
                navController.navigate("offers") {
                    popUpTo("home") { saveState = true }
                    launchSingleTop = true
                    restoreState = true
                }
            }
            PromotionKind.UPDATE -> showNotifications = true
        }
    }

    Scaffold(
        bottomBar = {
            if (showBottomBar && activeOfferForPurchase == null) {
                MyBingwaBottomNav(
                    currentRoute = currentRoute,
                    onNavigate = { dest ->
                        if (currentRoute != dest.route) {
                            navController.navigate(dest.route) {
                                // Pop back to the real Home root (not graph.startDestination,
                                // which may still be "onboarding" this session and would
                                // silently break Home navigation). Save/restore each tab's
                                // state so filters and scroll are preserved.
                                popUpTo("home") { saveState = true }
                                launchSingleTop = true
                                restoreState = true
                            }
                        } else {
                            // Reselecting the active tab returns it to the top (design.md §12.2).
                            scope.launch {
                                when (dest.route) {
                                    "home" -> homeListState.animateScrollToItem(0)
                                    "offers" -> offersListState.animateScrollToItem(0)
                                }
                            }
                        }
                    }
                )
            }
        },
        containerColor = androidx.compose.material3.MaterialTheme.colorScheme.background
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            NavHost(
                navController = navController,
                startDestination = if (startOnboarding) "onboarding" else "home"
            ) {
                composable("onboarding") {
                    OnboardingScreen(
                        onCompleteOnboarding = { name, phone ->
                            repository.updateProfile(name, phone)
                            repository.setOnboardingCompleted(true)
                            navController.navigate("home") {
                                popUpTo("onboarding") { inclusive = true }
                            }
                        }
                    )
                }

                composable("home") {
                    HomeScreen(
                        state = homeUiState,
                        unreadNotifCount = notifications.count { !it.isRead },
                        reducedMotion = reducedMotion,
                        listState = homeListState,
                        onCategoryClick = { cat ->
                            repository.setCategoryFilter(cat)
                            navController.navigate("offers") {
                                popUpTo("home") { saveState = true }
                                launchSingleTop = true
                                restoreState = true
                            }
                        },
                        onOfferSelect = { offer -> activeOfferForPurchase = offer },
                        onOfferBuy = { offer -> activeOfferForPurchase = offer },
                        onFavouriteToggle = { offer -> repository.setFavourite(offer.id, !offer.isFavourite) },
                        onUndoFavourite = onUndoFavourite,
                        onPromotionAction = onPromotionAction,
                        onNotifClick = { showNotifications = true },
                        onOfflineClick = {
                            navController.navigate("offers") {
                                popUpTo("home") { saveState = true }
                                launchSingleTop = true
                                restoreState = true
                            }
                        }
                    )
                }

                composable("offers") {
                    OffersScreen(
                        state = offersUiState,
                        listState = offersListState,
                        onSearchQueryChange = { repository.setSearchQuery(it) },
                        onCategorySelect = { repository.setCategoryFilter(it) },
                        onFilterStateChange = { repository.setFilterState(it) },
                        onClearFilters = { repository.clearFilters() },
                        onOfferSelect = { offer -> activeOfferForPurchase = offer },
                        onOfferBuy = { offer -> activeOfferForPurchase = offer },
                        onFavouriteToggle = { offer -> repository.setFavourite(offer.id, !offer.isFavourite) },
                        onUndoFavourite = onUndoFavourite
                    )
                }

                composable("activity") {
                    ActivityScreen(
                        purchases = purchases,
                        userPrimaryNumber = userProfile.primaryNumber,
                        onDeleteRecord = { id -> repository.deletePurchaseRecord(id) },
                        onDeleteRecords = { ids -> repository.deletePurchaseRecords(ids) },
                        onUndoDelete = { rec -> repository.undoDeletePurchaseRecord(rec) },
                        onBrowseOffers = {
                            navController.navigate("offers") {
                                popUpTo("home") { saveState = true }
                                launchSingleTop = true
                                restoreState = true
                            }
                        },
                        onReportProblem = { rec ->
                            prefilledReportRef = rec.mpesaCode
                            navController.navigate("help")
                        }
                    )
                }

                composable("help") {
                    HelpScreen(
                        prefilledRef = prefilledReportRef,
                        appConfig = appConfig,
                        onOpenSettings = { navController.navigate("settings") }
                    )
                }

                composable("settings") {
                    SettingsScreen(
                        profile = userProfile,
                        currentTheme = appTheme,
                        onUpdateProfile = { name, phone -> repository.updateProfile(name, phone) },
                        onThemeSelect = { theme -> repository.setAppTheme(theme) },
                        onClearLocalData = {
                            repository.clearAllLocalData()
                            navController.navigate("onboarding") {
                                popUpTo(0) { inclusive = true }
                            }
                        },
                        onEnablePushNotifications = requestNotificationPermission,
                        onEnableSmsDetection = requestSmsPermission
                    )
                }
            }

            // Purchase flow bottom sheet overlay (Phase 4 owns the state machine).
            activeOfferForPurchase?.let { snapshot ->
                val offer = liveOffer(snapshot)
                PurchaseBottomSheet(
                    offer = offer,
                    userPrimaryNumber = userProfile.primaryNumber,
                    recentRecipients = recentRecipients,
                    isOffline = isOffline,
                    onExecuteStkPush = { off, rec, pay, crid, self -> repository.executeMpesaStkPush(off, rec, pay, crid, self) },
                    onExecuteOfflinePayment = { off, rec, pay, till, receipt -> repository.executeOfflinePayment(off, rec, pay, till, receipt) },
                    offlineEligibility = { off, self -> repository.offlineEligibility(off, self) },
                    offlineConfig = { repository.offlineConfig() },
                    onDismiss = {
                        repository.clearActiveOrder()
                        activeOfferForPurchase = null
                    },
                    onViewActivity = {
                        activeOfferForPurchase = null
                        navController.navigate("activity") {
                            popUpTo("home") { saveState = true }
                            launchSingleTop = true
                            restoreState = true
                        }
                    }
                )
            }

            // Notification-centre overlay (slide-up modal). Rendered above the
            // scaffold body so the bottom navigation remains visible/accessible.
            if (showNotifications) {
                NotificationsSheet(
                    notifications = notifications,
                    notificationsEnabled = userProfile.notificationsEnabled,
                    onMarkRead = { id -> repository.markNotificationRead(id) },
                    onMarkAllRead = { repository.markAllNotificationsRead() },
                    onDeleteNotification = { id -> repository.deleteNotification(id) },
                    onClearAll = { repository.clearAllNotifications() },
                    onEnableNotifications = { repository.updateProfile(userProfile.name, userProfile.primaryNumber) },
                    onDeepLink = { route ->
                        navController.navigate(route) {
                            popUpTo("home") { saveState = true }
                            launchSingleTop = true
                            restoreState = true
                        }
                    },
                    onDismiss = { showNotifications = false }
                )
            }
        }
    }
}
