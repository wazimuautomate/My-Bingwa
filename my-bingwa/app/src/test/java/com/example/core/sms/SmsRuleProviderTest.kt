package com.example.core.sms

import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The selection and sync policy: the app must always have usable rules, must
 * prefer a genuinely newer server set, and must NEVER be blinded by a failed or
 * empty fetch.
 */
class SmsRuleProviderTest {

    /** In-memory [SmsRuleStore]; [failOnLoad] simulates a corrupt/unreadable cache. */
    private class FakeStore(
        var stored: SmsRuleSet? = null,
        var failOnLoad: Boolean = false
    ) : SmsRuleStore {
        var saveCount: Int = 0

        override suspend fun load(): SmsRuleSet? {
            if (failOnLoad) throw IllegalStateException("corrupt cache")
            return stored
        }

        override suspend fun save(set: SmsRuleSet) {
            stored = set
            saveCount++
        }
    }

    private class FixedSource(private val result: SmsRuleSet?) : RemoteSmsRuleSource {
        override suspend fun fetch(): SmsRuleSet? = result
    }

    private class ThrowingSource : RemoteSmsRuleSource {
        override suspend fun fetch(): SmsRuleSet = throw IllegalStateException("offline")
    }

    private fun serverSet(version: Int) = SmsRuleSet(
        version = version,
        updatedAt = 1_700_000_000_000L,
        rules = listOf(
            SmsRule(
                id = "srv_rule",
                name = "Server rule",
                senderId = "SAF_NEW",
                pattern = "umepokea",
                matchType = SmsMatchType.REGEX,
                eventTypes = listOf(SmsEventType.DATA_RECEIVED.name),
                priority = 5
            )
        )
    )

    @Test
    fun emptyStore_fallsBackToSeed() = runTest {
        val provider = SmsRuleProvider(FakeStore())
        assertEquals(DefaultSmsRules.SEED, provider.current())
    }

    @Test
    fun storedSetWithHigherVersion_isPreferred() = runTest {
        val newer = serverSet(DefaultSmsRules.SEED_VERSION + 1)
        val provider = SmsRuleProvider(FakeStore(stored = newer))
        assertEquals(newer, provider.current())
    }

    @Test
    fun storedSetWithSameOrLowerVersion_losesToSeed() = runTest {
        val same = serverSet(DefaultSmsRules.SEED_VERSION)
        assertEquals(DefaultSmsRules.SEED, SmsRuleProvider(FakeStore(stored = same)).current())

        val older = serverSet(DefaultSmsRules.SEED_VERSION - 1)
        assertEquals(DefaultSmsRules.SEED, SmsRuleProvider(FakeStore(stored = older)).current())
    }

    @Test
    fun storedSetWithNoRules_losesToSeed() = runTest {
        val empty = SmsRuleSet(version = 99, rules = emptyList())
        assertEquals(DefaultSmsRules.SEED, SmsRuleProvider(FakeStore(stored = empty)).current())
    }

    @Test
    fun unreadableStore_fallsBackToSeed_ratherThanNoRulesAtAll() = runTest {
        val provider = SmsRuleProvider(FakeStore(failOnLoad = true))
        assertEquals(DefaultSmsRules.SEED, provider.current())
    }

    @Test
    fun cachedOrSeed_isSeedBeforeAnyLoad_thenTheSelectedSet() = runTest {
        val newer = serverSet(DefaultSmsRules.SEED_VERSION + 1)
        val provider = SmsRuleProvider(FakeStore(stored = newer))
        assertEquals(DefaultSmsRules.SEED, provider.cachedOrSeed())
        provider.current()
        assertEquals(newer, provider.cachedOrSeed())
    }

    @Test
    fun syncFrom_storesAndCachesANewerSet() = runTest {
        val store = FakeStore()
        val provider = SmsRuleProvider(store)
        val newer = serverSet(DefaultSmsRules.SEED_VERSION + 1)

        assertTrue(provider.syncFrom(FixedSource(newer)))
        assertEquals(1, store.saveCount)
        assertEquals(newer, store.stored)
        assertEquals(newer, provider.cachedOrSeed())
    }

    @Test
    fun syncFrom_nullFetch_keepsCachedRules_andDoesNotWipe() = runTest {
        val newer = serverSet(DefaultSmsRules.SEED_VERSION + 1)
        val store = FakeStore(stored = newer)
        val provider = SmsRuleProvider(store)
        provider.current()

        assertFalse(provider.syncFrom(FixedSource(null)))
        assertEquals(newer, store.stored)
        assertEquals(newer, provider.cachedOrSeed())
        assertEquals(0, store.saveCount)
    }

    @Test
    fun syncFrom_emptyFetch_keepsCachedRules_andDoesNotWipe() = runTest {
        val newer = serverSet(DefaultSmsRules.SEED_VERSION + 1)
        val store = FakeStore(stored = newer)
        val provider = SmsRuleProvider(store)
        provider.current()

        assertFalse(provider.syncFrom(FixedSource(SmsRuleSet(version = 500, rules = emptyList()))))
        assertEquals(newer, store.stored)
        assertEquals(newer, provider.cachedOrSeed())
    }

    @Test
    fun syncFrom_throwingSource_isNotFatal_andKeepsCachedRules() = runTest {
        val newer = serverSet(DefaultSmsRules.SEED_VERSION + 1)
        val store = FakeStore(stored = newer)
        val provider = SmsRuleProvider(store)
        provider.current()

        assertFalse(provider.syncFrom(ThrowingSource()))
        assertEquals(newer, provider.cachedOrSeed())
    }

    @Test
    fun serverTaughtRuleIsUsableEndToEnd_afterSync() = runTest {
        val provider = SmsRuleProvider(FakeStore())
        val newer = serverSet(DefaultSmsRules.SEED_VERSION + 1)
        assertTrue(provider.syncFrom(FixedSource(newer)))

        val match = DynamicSmsParser.parse(
            sender = "SAF_NEW",
            body = "Umepokea 1GB ya leo.",
            ruleSet = provider.cachedOrSeed()
        )
        assertNotNull(match)
        assertEquals("srv_rule", match!!.ruleId)
    }
}
