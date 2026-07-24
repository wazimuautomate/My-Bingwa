package com.example

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.compose.animation.Crossfade
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Scaffold
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.example.core.model.AppThemeSetting
import com.example.core.model.OfferCategory
import com.example.core.model.OfferItem
import com.example.core.model.PurchaseRecord
import com.example.core.ui.BottomNavDestination
import com.example.core.ui.MyBingwaBottomNav
import com.example.data.fake.FakeBingwaRepositoryImpl
import com.example.feature.activity.ActivityScreen
import com.example.feature.help.HelpScreen
import com.example.feature.home.HomeScreen
import com.example.feature.notifications.NotificationsScreen
import com.example.feature.offers.OffersScreen
import com.example.feature.onboarding.OnboardingScreen
import com.example.feature.purchase.PurchaseBottomSheet
import com.example.feature.settings.SettingsScreen
import com.example.ui.theme.MyBingwaTheme
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {

    private val repository = FakeBingwaRepositoryImpl()

    override fun onCreate(savedInstanceState: Bundle?) {
        // Install the system splash before super/setContent so the branded
        // launch mark shows on cold start, then hands off to the app theme.
        installSplashScreen()
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        setContent {
            val userProfile by repository.userProfile.collectAsState()
            val appTheme by repository.appTheme.collectAsState()
            val isOffline by repository.isOffline.collectAsState()

            val darkTheme = when (appTheme) {
                AppThemeSetting.SYSTEM -> isSystemInDarkTheme()
                AppThemeSetting.LIGHT -> false
                AppThemeSetting.DARK -> true
            }

            MyBingwaTheme(darkTheme = darkTheme) {
                MyBingwaApp(
                    repository = repository,
                    startOnboarding = !userProfile.isOnboardingCompleted
                )
            }
        }
    }
}

@Composable
fun MyBingwaApp(
    repository: FakeBingwaRepositoryImpl,
    startOnboarding: Boolean
) {
    val navController = rememberNavController()
    val coroutineScope = rememberCoroutineScope()

    val userProfile by repository.userProfile.collectAsState()
    val appTheme by repository.appTheme.collectAsState()
    val isOffline by repository.isOffline.collectAsState()
    val offers by repository.offers.collectAsState()
    val filterState by repository.filterState.collectAsState()
    val purchases by repository.purchases.collectAsState()
    val notifications by repository.notifications.collectAsState()
    val recentRecipients by repository.recentRecipients.collectAsState()

    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route ?: if (startOnboarding) "onboarding" else "home"

    var activeOfferForPurchase by remember { mutableStateOf<OfferItem?>(null) }
    var prefilledReportRef by remember { mutableStateOf<String?>(null) }

    // Routes where bottom nav should be visible
    val showBottomBar = currentRoute in listOf("home", "offers", "activity", "help", "settings")

    Scaffold(
        bottomBar = {
            if (showBottomBar && activeOfferForPurchase == null) {
                MyBingwaBottomNav(
                    currentRoute = currentRoute,
                    onNavigate = { dest ->
                        navController.navigate(dest.route) {
                            popUpTo(navController.graph.startDestinationId) { saveState = true }
                            launchSingleTop = true
                            restoreState = true
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
                        profile = userProfile,
                        isOffline = isOffline,
                        unreadNotifCount = notifications.count { !it.isRead },
                        offers = offers,
                        recentPurchases = purchases,
                        onCategoryClick = { cat ->
                            repository.setCategoryFilter(cat)
                            navController.navigate("offers")
                        },
                        onOfferSelect = { offer -> activeOfferForPurchase = offer },
                        onOfferBuy = { offer -> activeOfferForPurchase = offer },
                        onFavouriteToggle = { offer -> repository.toggleFavourite(offer.id) },
                        onNotifClick = { navController.navigate("notifications") },
                        onProfileClick = { navController.navigate("settings") },
                        onSearchClick = { navController.navigate("offers") }
                    )
                }

                composable("offers") {
                    OffersScreen(
                        offers = offers,
                        filterState = filterState,
                        isOffline = isOffline,
                        onSearchQueryChange = { repository.setSearchQuery(it) },
                        onCategorySelect = { repository.setCategoryFilter(it) },
                        onFilterStateChange = { repository.setFilterState(it) },
                        onClearFilters = { repository.clearFilters() },
                        onOfferSelect = { offer -> activeOfferForPurchase = offer },
                        onOfferBuy = { offer -> activeOfferForPurchase = offer },
                        onFavouriteToggle = { offer -> repository.toggleFavourite(offer.id) }
                    )
                }

                composable("activity") {
                    ActivityScreen(
                        purchases = purchases,
                        userPrimaryNumber = userProfile.primaryNumber,
                        onDeleteRecord = { id -> repository.deletePurchaseRecord(id) },
                        onDeleteRecords = { ids -> repository.deletePurchaseRecords(ids) },
                        onUndoDelete = { rec -> repository.undoDeletePurchaseRecord(rec) },
                        onBrowseOffers = { navController.navigate("offers") },
                        onReportProblem = { rec ->
                            prefilledReportRef = rec.mpesaCode
                            navController.navigate("help")
                        }
                    )
                }

                composable("help") {
                    HelpScreen(
                        prefilledRef = prefilledReportRef,
                        onOpenSettings = { navController.navigate("settings") }
                    )
                }

                composable("notifications") {
                    NotificationsScreen(
                        notifications = notifications,
                        notificationsEnabled = userProfile.notificationsEnabled,
                        onMarkRead = { id -> repository.markNotificationRead(id) },
                        onMarkAllRead = { repository.markAllNotificationsRead() },
                        onEnableNotifications = { repository.updateProfile(userProfile.name, userProfile.primaryNumber) },
                        onBack = { navController.popBackStack() }
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
                        }
                    )
                }
            }

            // Fast Purchase Flow Bottom Sheet Overlay
            activeOfferForPurchase?.let { offer ->
                PurchaseBottomSheet(
                    offer = offer,
                    userPrimaryNumber = userProfile.primaryNumber,
                    recentRecipients = recentRecipients,
                    isOffline = isOffline,
                    onExecuteStkPush = { off, rec, pay ->
                        repository.executeMpesaStkPush(off, rec, pay)
                    },
                    onExecuteOfflinePayment = { off, rec, pay, till ->
                        repository.executeOfflinePayment(off, rec, pay, till)
                    },
                    onDismiss = { activeOfferForPurchase = null },
                    onViewActivity = {
                        activeOfferForPurchase = null
                        navController.navigate("activity")
                    }
                )
            }
        }
    }
}
