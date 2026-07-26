package com.example

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
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
import com.example.core.update.UpdateChecker
import com.example.core.update.UpdatePromotion
import com.example.core.update.UpdateRequiredScreen
import com.example.core.update.UpdateResult
import com.example.core.update.UpdateSource
import com.example.core.update.openPlayStoreListing
import com.example.core.ui.MyBingwaBottomNav
import com.example.data.fake.BingwaRepository
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

    // The single process-wide repository, owned by MyBingwaApplication so the UI and
    // the background CatalogueSyncWorker share one instance (same StateFlows, same
    // on-device LocalStore). `by lazy` because `application` is attached before
    // onCreate runs, where this is first read.
    private val repository: BingwaRepository by lazy {
        (application as MyBingwaApplication).repository
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
                repository.syncBillboards()
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

    // Real OS permission state → profile, so the Settings toggles reflect the actual
    // grant (not an optimistic "on"). Checked on start and refreshed on every result.
    fun notificationsGranted(): Boolean =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            ContextCompat.checkSelfPermission(appContext, Manifest.permission.POST_NOTIFICATIONS) ==
                PackageManager.PERMISSION_GRANTED
        } else {
            NotificationManagerCompat.from(appContext).areNotificationsEnabled()
        }

    fun smsGranted(): Boolean =
        ContextCompat.checkSelfPermission(appContext, Manifest.permission.RECEIVE_SMS) ==
            PackageManager.PERMISSION_GRANTED

    LaunchedEffect(Unit) {
        repository.setNotificationsEnabled(notificationsGranted())
        repository.setSmsAlertsEnabled(smsGranted())
    }

    // Runtime permission launchers (Android 13+ POST_NOTIFICATIONS, RECEIVE_SMS).
    // Settings shows the in-app rationale first, then invokes these; the granted
    // result is written back so the toggle can never show "on" while the OS denied it.
    val notificationPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted -> repository.setNotificationsEnabled(granted) }

    val smsPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted -> repository.setSmsAlertsEnabled(granted) }

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

    // --- Direct-channel update awareness (Task 8) --------------------------------
    // One check at start drives all three surfaces: the force-update gate, the
    // system notification and the Home "Update available" billboard. Play users
    // update via the store; github users install in-app (AppUpdateInstaller).
    var pendingUpdate by remember { mutableStateOf<UpdateResult.Available?>(null) }
    LaunchedEffect(Unit) {
        val result = UpdateChecker.check()
        if (result is UpdateResult.Available) {
            pendingUpdate = result
            // Reuse the existing notification infrastructure (UPDATES channel). No-ops
            // silently when POST_NOTIFICATIONS is not granted; a tap deep-links to
            // Settings → update section (AppNotifier.postAppUpdate uses route "settings").
            appNotifier.postAppUpdate(result.versionName.ifBlank { "update" })
        }
    }
    // Non-dismissible gate required when mandatory OR this build is below the
    // manifest's minSupportedVersionCode. A normal update stays a gentle prompt.
    val updateRequired = pendingUpdate?.isRequired() == true

    val catalogueViewModel: CatalogueViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T =
                CatalogueViewModel(repository) as T
        }
    )

    val homeUiState by catalogueViewModel.homeUiState.collectAsState()
    val offersUiState by catalogueViewModel.offersUiState.collectAsState()

    // Surface an "Update available" billboard on Home while an update is pending
    // (Task 8.3). Prepended (not run through billboard selection) so it always
    // leads and is never filtered out — and it does not disturb synced billboards.
    val homeState = pendingUpdate?.let { update ->
        homeUiState.copy(promotions = listOf(UpdatePromotion.forUpdate(update)) + homeUiState.promotions)
    } ?: homeUiState

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

    // Hide navigation behind the non-dismissible "Update required" gate.
    val showBottomBar = !updateRequired &&
        currentRoute in listOf("home", "offers", "activity", "help", "settings")

    // Resolve details/purchase offers against the live catalogue so favourite
    // toggles inside a sheet stay reflected.
    fun liveOffer(offer: OfferItem): OfferItem = offers.find { it.id == offer.id } ?: offer

    val onUndoFavourite: (String) -> Unit = { id -> repository.setFavourite(id, true) }

    // Browse the Offers tab, optionally pre-filtered to a category. Shared by
    // announcement slides and by offer slides whose linked offer can't be resolved.
    val browseOffers: (com.example.core.model.OfferCategory?) -> Unit = { category ->
        category?.let { repository.setCategoryFilter(it) }
        navController.navigate("offers") {
            popUpTo("home") { saveState = true }
            launchSingleTop = true
            restoreState = true
        }
    }

    val onPromotionAction: (Promotion) -> Unit = { promo ->
        when {
            // The synthetic "Update available" slide (Task 8.3): its CTA opens the
            // Settings update section (github) or the Play listing — handled the same
            // way the Settings deep-link is, via the pending-update state below.
            promo.id == UpdatePromotion.ID -> {
                if (pendingUpdate?.source == UpdateSource.PLAY) {
                    openPlayStoreListing(context)
                } else {
                    navController.navigate("settings") {
                        popUpTo("home") { saveState = true }
                        launchSingleTop = true
                        restoreState = true
                    }
                }
            }
            promo.kind == PromotionKind.OFFER -> {
                val target = promo.linkedOfferId?.let { id -> offers.find { it.id == id } }
                if (target != null) {
                    activeOfferForPurchase = target
                } else {
                    // Synced billboard whose linked offer isn't in this catalogue: don't
                    // dead-end the CTA — browse offers (optionally by linked category).
                    browseOffers(promo.linkedCategory)
                }
            }
            promo.kind == PromotionKind.ANNOUNCEMENT -> browseOffers(promo.linkedCategory)
            else -> showNotifications = true // PromotionKind.UPDATE (informational)
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
                        state = homeState,
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
                        onEnableSmsDetection = requestSmsPermission,
                        knownUpdate = pendingUpdate
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

            // Blocking "Update required" gate (Task 8.1). Drawn last so it overlays
            // everything; the bottom bar is already hidden (showBottomBar). The user
            // cannot proceed until they update (github install or Play), and back is
            // swallowed inside the screen.
            pendingUpdate?.let { update ->
                if (updateRequired) {
                    UpdateRequiredScreen(update = update)
                }
            }
        }
    }
}
