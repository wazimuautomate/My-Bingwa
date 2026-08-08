package com.example.core.sms

import android.content.Context
import kotlinx.coroutines.CancellationException

/**
 * SERVER SEAM. The one interface the network layer implements so the backend can
 * teach the app new Safaricom message shapes with ZERO app release.
 *
 * Returning null means "no newer rules right now / offline / call failed" — it is
 * NOT an instruction to forget what we already know (see [SmsRuleProvider.syncFrom]).
 */
interface RemoteSmsRuleSource {
    /** Fetch the latest rule set, or null when unavailable. Must not throw for a normal failure. */
    suspend fun fetch(): SmsRuleSet?
}

/**
 * Decides WHICH rule set is active right now.
 *
 * Selection policy (deliberately conservative):
 * - a stored set wins only when it is non-empty AND its [SmsRuleSet.version] is
 *   strictly higher than [DefaultSmsRules.SEED_VERSION];
 * - anything else falls back to the in-APK seed.
 *
 * The app is therefore never left without rules: a corrupt cache, a first launch,
 * a failed sync and a server outage all degrade to the seed rather than to
 * "detect nothing".
 *
 * [cachedOrSeed] exists for the [android.content.BroadcastReceiver], which cannot
 * block on a suspending DataStore read on the main thread.
 */
class SmsRuleProvider(private val store: SmsRuleStore) {

    @Volatile
    private var cached: SmsRuleSet? = null

    /**
     * The active rule set, reading the cache from disk. Also warms the in-memory
     * cache that [cachedOrSeed] serves.
     */
    suspend fun current(): SmsRuleSet {
        val stored = try {
            store.load()
        } catch (e: CancellationException) {
            throw e
        } catch (e: Exception) {
            null
        }
        val selected = select(stored)
        cached = selected
        return selected
    }

    /**
     * Pull the latest rules from [source] and cache them.
     *
     * @return true only when a usable set was fetched AND stored.
     *
     * A null or EMPTY fetch KEEPS the rules already cached — the server must
     * never be able to blind the app by accident (an outage, an empty response,
     * a truncated payload). Wiping detection is not an outcome any failure path
     * is allowed to produce.
     */
    suspend fun syncFrom(source: RemoteSmsRuleSource): Boolean {
        val fetched = try {
            source.fetch()
        } catch (e: CancellationException) {
            throw e
        } catch (e: Exception) {
            null
        }
        if (fetched == null || fetched.rules.isEmpty()) return false

        return try {
            store.save(fetched)
            cached = select(fetched)
            true
        } catch (e: CancellationException) {
            throw e
        } catch (e: Exception) {
            // Could not persist (disk full, corrupt store). The in-memory cache is
            // still updated so this session benefits; the next sync retries the write.
            cached = select(fetched)
            false
        }
    }

    /**
     * NON-SUSPENDING active rule set, from the in-memory cache warmed by
     * [current]/[syncFrom]. Falls back to the seed when the cache is cold, so a
     * receiver can classify the very first SMS after a cold start without waiting
     * on disk.
     */
    fun cachedOrSeed(): SmsRuleSet = cached ?: DefaultSmsRules.SEED

    private fun select(stored: SmsRuleSet?): SmsRuleSet {
        val seed = DefaultSmsRules.SEED
        if (stored == null) return seed
        if (stored.rules.isEmpty()) return seed
        return if (stored.version > seed.version) stored else seed
    }

    companion object {
        @Volatile
        private var instance: SmsRuleProvider? = null

        /**
         * The PROCESS-WIDE provider.
         *
         * A manifest-registered BroadcastReceiver is constructed fresh by the
         * system for every SMS, so a per-receiver instance would always start with
         * a cold cache and always pay a disk read. One shared instance keeps the
         * warm cache across broadcasts. No DI framework (CLAUDE.md: no Hilt).
         */
        fun shared(context: Context): SmsRuleProvider {
            val existing = instance
            if (existing != null) return existing
            return synchronized(this) {
                val current = instance
                if (current != null) {
                    current
                } else {
                    val created = SmsRuleProvider(DataStoreSmsRuleStore(context.applicationContext))
                    instance = created
                    created
                }
            }
        }
    }
}
