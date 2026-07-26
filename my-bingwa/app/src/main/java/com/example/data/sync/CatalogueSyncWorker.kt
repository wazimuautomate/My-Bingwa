package com.example.data.sync

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.example.MyBingwaApplication

/**
 * Periodic background sync of the seller config, offer catalogue and Home billboards.
 *
 * It refreshes the SAME process-wide repository the UI observes (obtained from
 * [MyBingwaApplication]), so a background refresh updates the live StateFlows and the
 * on-device [com.example.data.persistence.LocalStore] — never a second, divergent
 * repository. The repository's own sync methods are no-data-loss by contract: a
 * failed/empty/incomplete response keeps the existing local offers, so this worker can
 * never wipe local data.
 *
 * On any unexpected error the worker returns [Result.retry] so WorkManager reschedules
 * with the configured exponential backoff.
 */
class CatalogueSyncWorker(
    appContext: Context,
    params: WorkerParameters
) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result {
        val repository = (applicationContext as? MyBingwaApplication)?.repository
        // No app-scoped repository (should not happen in the real process): nothing to
        // sync, and retrying will not help — succeed quietly rather than loop.
        ?: return Result.success()

        return try {
            repository.syncRemoteConfig()
            repository.syncCatalogue()
            repository.syncBillboards()
            Result.success()
        } catch (t: Throwable) {
            Result.retry()
        }
    }
}
