package com.example

import android.app.Application
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkRequest
import com.example.data.catalogue.AndroidRemoteCatalogueSource
import com.example.data.config.AndroidRemoteConfigSource
import com.example.data.fake.BingwaRepository
import com.example.data.fake.FakeBingwaRepositoryImpl
import com.example.data.payment.PaymentGatewayProvider
import com.example.data.payment.UnavailablePaymentGateway
import com.example.data.persistence.LocalStore
import com.example.data.sync.CatalogueSyncWorker
import java.util.concurrent.TimeUnit

/**
 * Process-wide entry point. It owns the single [BingwaRepository] instance so the UI
 * ([MainActivity]) and the background [CatalogueSyncWorker] share exactly one
 * repository — the same StateFlows and the same on-device [LocalStore] — instead of
 * two divergent copies. It also schedules the periodic catalogue/config sync.
 */
class MyBingwaApplication : Application() {

    /**
     * The app's one repository. Built lazily on first access (UI start or the sync
     * worker), so construction never blocks [onCreate]/cold start. Buy-for-myself uses
     * the real backend proxy when a non-secret base URL + app-key are configured;
     * otherwise a labelled debug simulation or an honest-failing release gateway.
     * Daraja credentials never live in the app.
     */
    val repository: BingwaRepository by lazy { buildRepository() }

    override fun onCreate() {
        super.onCreate()
        scheduleCatalogueSync()
    }

    private fun buildRepository(): BingwaRepository {
        val baseUrl = BuildConfig.PAYMENTS_BASE_URL
        val appKey = BuildConfig.PAYMENTS_APP_KEY
        // Real STK needs BOTH a usable https base URL AND the app-key (the backend
        // rejects calls without it). Config/catalogue sync only needs a base URL.
        val paymentConfigured = PaymentGatewayProvider.isBackendConfigured(baseUrl, appKey)
        val hasBaseUrl = baseUrl.isNotBlank() && baseUrl.startsWith("https://")

        return FakeBingwaRepositoryImpl(
            gateway = if (paymentConfigured) {
                PaymentGatewayProvider.create(
                    baseUrl = baseUrl,
                    appKey = appKey,
                    debugLogging = BuildConfig.DEBUG
                )
            } else {
                null
            },
            // No real backend: a debug build uses a labelled on-device simulation so
            // screens stay testable; a release build must NEVER fake a success, so it
            // uses UnavailablePaymentGateway (payments fail honestly until configured).
            fallbackGateway = if (!paymentConfigured && !BuildConfig.DEBUG) {
                UnavailablePaymentGateway()
            } else {
                null
            },
            // Seller Till/Paybill/support are synced from the server but always cached
            // for offline use. No base URL → baked-in defaults only.
            configSource = if (hasBaseUrl) {
                AndroidRemoteConfigSource(
                    context = applicationContext,
                    baseUrl = baseUrl,
                    appKey = appKey,
                    enableLogging = BuildConfig.DEBUG
                )
            } else {
                null
            },
            // Offers are synced from the server when online; the bundled catalogue is
            // the guaranteed offline base (server is only for syncing).
            catalogueSource = if (hasBaseUrl) {
                AndroidRemoteCatalogueSource(
                    baseUrl = baseUrl,
                    appKey = appKey,
                    enableLogging = BuildConfig.DEBUG
                )
            } else {
                null
            },
            // Real on-device persistence: name, profile, favourites, Activity, synced
            // offers, notifications and any in-flight order survive process death.
            localStore = LocalStore(applicationContext)
        )
    }

    /**
     * Enqueue the periodic background sync as a unique job. [ExistingPeriodicWorkPolicy.KEEP]
     * leaves an already-scheduled job untouched across restarts. It runs only when the
     * device is CONNECTED, retries failures with exponential backoff, and never blocks
     * startup (enqueue is cheap and offloaded to WorkManager).
     */
    private fun scheduleCatalogueSync() {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()

        val request = PeriodicWorkRequestBuilder<CatalogueSyncWorker>(SYNC_INTERVAL_HOURS, TimeUnit.HOURS)
            .setConstraints(constraints)
            .setBackoffCriteria(
                BackoffPolicy.EXPONENTIAL,
                WorkRequest.MIN_BACKOFF_MILLIS,
                TimeUnit.MILLISECONDS
            )
            .build()

        WorkManager.getInstance(this).enqueueUniquePeriodicWork(
            UNIQUE_SYNC_WORK_NAME,
            ExistingPeriodicWorkPolicy.KEEP,
            request
        )
    }

    private companion object {
        const val UNIQUE_SYNC_WORK_NAME = "mybingwa_catalogue_sync"

        // Six hours: fresh enough for offer/config changes without draining battery or
        // hammering the backend. Comfortably above WorkManager's 15-minute floor.
        const val SYNC_INTERVAL_HOURS = 6L
    }
}
