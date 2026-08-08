package com.example.data.fake

import com.example.core.notifications.ConnectionState
import com.example.data.config.AppConfig
import com.example.data.config.RemoteConfigSource
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class RemoteConfigTest {

    private class FakeConfigSource(
        var cachedValue: AppConfig = AppConfig.DEFAULT,
        private val remote: AppConfig? = null
    ) : RemoteConfigSource {
        override fun cached(): AppConfig = cachedValue
        override suspend fun fetch(): AppConfig? = remote
    }

    @Test
    fun appConfig_seedsFromCache() {
        val cached = AppConfig("111", "222", "0700000000", "254700000000")
        val repo = FakeBingwaRepositoryImpl(configSource = FakeConfigSource(cachedValue = cached))
        assertEquals(cached, repo.appConfig.value)
    }

    @Test
    fun offlineConfig_usesSyncedTillAndPaybill() {
        val cached = AppConfig(tillNumber = "9999", paybillNumber = "8888", supportNumber = "0700", supportWhatsapp = "254700")
        val repo = FakeBingwaRepositoryImpl(configSource = FakeConfigSource(cachedValue = cached))
        val cfg = repo.offlineConfig()
        assertEquals("9999", cfg?.tillNumber)
        assertEquals("8888", cfg?.paybillNumber)
    }

    @Test
    fun syncRemoteConfig_updatesAppConfig() = runTest {
        val remote = AppConfig("R-TILL", "R-PAYBILL", "0711111111", "254711111111")
        val repo = FakeBingwaRepositoryImpl(configSource = FakeConfigSource(remote = remote))
        repo.syncRemoteConfig()
        assertEquals(remote, repo.appConfig.value)
        assertEquals("R-TILL", repo.offlineConfig()?.tillNumber)
    }

    /**
     * This test used to assert that the baked-in defaults were "always available
     * offline". That made sense while [AppConfig.DEFAULT] still carried real seller
     * numbers; once those were blanked (no Till/Paybill is hardcoded in the app any
     * more) the same assertion only proved that a BLANK config was handed to the
     * checkout, which rendered offline instructions with an empty number to copy.
     * The intended behaviour is the opposite: nothing synced yet means no offline
     * instructions, so the sheet asks the customer to connect and refresh
     * (CLAUDE.md §7 — ambiguous offline configuration disables payment).
     */
    @Test
    fun noConfigSource_yieldsBlankConfigAndNoOfflineInstructions() {
        val repo = FakeBingwaRepositoryImpl()
        assertEquals(AppConfig.DEFAULT, repo.appConfig.value)
        assertNull(repo.offlineConfig())
    }

    /** A single synced number is enough to offer instructions; the sheet then guards the route. */
    @Test
    fun offlineConfig_isOffered_whenOnlyOneNumberIsSynced() {
        val tillOnly = AppConfig(tillNumber = "9999", paybillNumber = "", supportNumber = "", supportWhatsapp = "")
        val repo = FakeBingwaRepositoryImpl(configSource = FakeConfigSource(cachedValue = tillOnly))
        val cfg = repo.offlineConfig()
        assertEquals("9999", cfg?.tillNumber)
        assertEquals("", cfg?.paybillNumber)
    }

    @Test
    fun connectivity_drivesOfflineFlag() {
        val repo = FakeBingwaRepositoryImpl()
        repo.setConnectionState(ConnectionState.NONE)
        assertTrue(repo.isOffline.value)
        repo.setConnectionState(ConnectionState.WIFI)
        assertFalse(repo.isOffline.value)
        repo.setConnectionState(ConnectionState.CELLULAR)
        assertFalse(repo.isOffline.value)
        repo.setConnectionState(ConnectionState.NONE)
        assertTrue(repo.isOffline.value)
    }
}
