package com.example.data.sync

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.Data
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import com.example.core.notifications.AppNotifier
import com.example.core.notifications.ConnectionState
import com.example.core.notifications.ConnectivityObserver
import com.example.core.notifications.EngagementSchedule
import com.example.core.notifications.EngagementSlot
import java.util.concurrent.TimeUnit

/**
 * Posts the daily engagement notification for ONE slot, then schedules the next one.
 *
 * A self-rescheduling chain of one-shot jobs rather than a periodic job: the fire
 * time inside each window is different every day (see [EngagementSchedule]), which a
 * fixed-interval periodic job cannot express. Each run enqueues exactly one
 * successor under the same unique name, so the chain can never fork — a duplicate
 * enqueue REPLACEs rather than adds.
 *
 * Nothing here is loud. A run can decide to stay silent and that is a normal, quiet
 * success (CLAUDE.md §9, "prefer silence over a weak message"):
 *  - the live connection does not match what the slot is for;
 *  - this slot already posted today (survives process death via prefs);
 *  - notifications are not permitted, in which case [AppNotifier] no-ops anyway.
 */
class EngagementNotificationWorker(
    appContext: Context,
    params: WorkerParameters,
) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result {
        val now = System.currentTimeMillis()

        // Which slot were we scheduled for? An absent/unknown name means the request was
        // built by an older version of the app; just re-schedule from now and stop.
        val slot = inputData.getString(KEY_SLOT)
            ?.let { name -> EngagementSlot.entries.firstOrNull { it.name == name } }

        if (slot != null) {
            postIfDue(slot, now)
        }

        scheduleNext(applicationContext, now)
        return Result.success()
    }

    /** Post the slot's message when — and only when — every condition still holds. */
    private fun postIfDue(slot: EngagementSlot, now: Long) {
        val key = EngagementSchedule.slotKey(slot, now)
        val prefs = applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        // Already posted this slot today (a retried or duplicated run) → stay silent.
        if (prefs.getBoolean(key, false)) return

        // The slot is written for a specific connection state. WorkManager may run us
        // late or early, so the state is read HERE, not when the job was scheduled.
        val connected = ConnectivityObserver(applicationContext).current() != ConnectionState.NONE
        if (connected != slot.requiresConnection) return

        val message = EngagementSchedule.messageFor(slot, now)
        val posted = AppNotifier(applicationContext).postOfferSuggestion(
            title = message.title,
            body = message.body,
            stableId = key,
            deepLinkRoute = message.deepLinkRoute,
        )
        // Only mark it done once it actually reached the system. A refusal (permission
        // not granted yet) leaves the slot open, so it can still land on a later day.
        if (posted) prefs.edit().putBoolean(key, true).apply()
    }

    companion object {
        private const val PREFS = "mybingwa_engagement"
        private const val KEY_SLOT = "slot"

        /** Unique work name. One chain, replaced rather than duplicated on re-enqueue. */
        const val UNIQUE_WORK_NAME = "mybingwa_engagement_notifications"

        /**
         * Enqueue the next due slot as a one-shot job.
         *
         * Safe to call as often as you like — from [com.example.MyBingwaApplication] on
         * every cold start and from the worker itself. `REPLACE` means the newest
         * computation of "what is next" always wins and no chain is ever left running
         * beside another.
         *
         * No network constraint: the offline slots exist precisely to reach a customer
         * with no connection, and the copy is built on the device.
         */
        fun scheduleNext(context: Context, nowMillis: Long = System.currentTimeMillis()) {
            val (slot, at) = EngagementSchedule.nextOccurrence(nowMillis) ?: return
            val delay = (at - nowMillis).coerceAtLeast(0L)

            val request = OneTimeWorkRequestBuilder<EngagementNotificationWorker>()
                .setInitialDelay(delay, TimeUnit.MILLISECONDS)
                .setInputData(Data.Builder().putString(KEY_SLOT, slot.name).build())
                .build()

            WorkManager.getInstance(context).enqueueUniqueWork(
                UNIQUE_WORK_NAME,
                ExistingWorkPolicy.REPLACE,
                request,
            )
        }
    }
}
