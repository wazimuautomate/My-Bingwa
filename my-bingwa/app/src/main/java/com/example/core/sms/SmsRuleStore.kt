package com.example.core.sms

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.squareup.moshi.Moshi
import com.squareup.moshi.kotlin.reflect.KotlinJsonAdapterFactory
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.flow.first

/**
 * The cache of server-taught SMS rules, kept in its OWN Preferences DataStore
 * ("mybingwa_sms_rules") so it is completely independent of the customer's
 * installation snapshot in `data/persistence/LocalStore.kt`. Corrupting or
 * clearing one can never affect the other, and no personal data ever lands here —
 * only rules (CLAUDE.md §10).
 *
 * Modelled on `LocalStore`: one JSON document under one key, Moshi **reflection**
 * (no codegen in this module).
 */
private val Context.smsRuleDataStore: DataStore<Preferences> by preferencesDataStore(
    name = "mybingwa_sms_rules"
)

/**
 * Read/write seam for the cached rule set. The app injects
 * [DataStoreSmsRuleStore]; unit tests inject a tiny in-memory implementation so
 * the "never lose the rules" behaviour can be verified without Android.
 */
interface SmsRuleStore {
    /** The cached set, or null when nothing is stored / it is unreadable. */
    suspend fun load(): SmsRuleSet?

    /** Overwrites the cached set. */
    suspend fun save(set: SmsRuleSet)
}

/** DataStore-backed [SmsRuleStore]. Construct manually with any [Context] — no DI. */
class DataStoreSmsRuleStore(context: Context) : SmsRuleStore {

    private val appContext: Context = context.applicationContext

    private val moshi: Moshi = Moshi.Builder().add(KotlinJsonAdapterFactory()).build()
    private val adapter = moshi.adapter(SmsRuleSet::class.java)

    /**
     * A read failure returns null on purpose: the caller then falls back to the
     * in-APK seed, so the app is NEVER left with no rules at all.
     */
    override suspend fun load(): SmsRuleSet? = try {
        val prefs = appContext.smsRuleDataStore.data.first()
        val json = prefs[RULES_KEY]
        if (json.isNullOrBlank()) null else adapter.fromJson(json)
    } catch (e: CancellationException) {
        // Never swallow cancellation — the BroadcastReceiver relies on its timeout.
        throw e
    } catch (e: Exception) {
        null
    }

    override suspend fun save(set: SmsRuleSet) {
        val json = adapter.toJson(set)
        appContext.smsRuleDataStore.edit { it[RULES_KEY] = json }
    }

    private companion object {
        val RULES_KEY = stringPreferencesKey("sms_rules_json")
    }
}
