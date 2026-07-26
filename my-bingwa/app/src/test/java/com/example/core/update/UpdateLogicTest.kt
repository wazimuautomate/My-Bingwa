package com.example.core.update

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.io.File

/**
 * Host-JVM coverage for the update-decision logic and the APK hash used to verify
 * a download. No Android framework calls here — [AppUpdateInstaller.sha256Hex] is
 * pure java.io/java.security, and [UpdateSource]/[UpdateResult.Available.isRequired]
 * are pure Kotlin.
 */
class UpdateLogicTest {

    private fun available(
        mandatory: Boolean = false,
        minSupported: Int = 0,
        source: UpdateSource = UpdateSource.GITHUB,
    ) = UpdateResult.Available(
        versionName = "1.0.2",
        versionCode = 3,
        apkUrl = "https://example.com/app.apk",
        apkSha256 = "",
        notes = "notes",
        mandatory = mandatory,
        minSupportedVersionCode = minSupported,
        source = source,
    )

    // --- UpdateSource.from --------------------------------------------------

    @Test
    fun `updateSource defaults to github and only play means play`() {
        assertEquals(UpdateSource.PLAY, UpdateSource.from("play"))
        assertEquals(UpdateSource.PLAY, UpdateSource.from("PLAY"))
        assertEquals(UpdateSource.PLAY, UpdateSource.from("  Play  "))
        assertEquals(UpdateSource.GITHUB, UpdateSource.from("github"))
        assertEquals(UpdateSource.GITHUB, UpdateSource.from(null))
        assertEquals(UpdateSource.GITHUB, UpdateSource.from(""))
        assertEquals(UpdateSource.GITHUB, UpdateSource.from("something-else"))
    }

    // --- isRequired ---------------------------------------------------------

    @Test
    fun `isRequired true when mandatory regardless of version`() {
        assertTrue(available(mandatory = true).isRequired(currentVersionCode = 999))
    }

    @Test
    fun `isRequired true when current build is below minSupported`() {
        assertTrue(available(minSupported = 5).isRequired(currentVersionCode = 4))
    }

    @Test
    fun `isRequired false for a normal optional update`() {
        assertFalse(available(mandatory = false, minSupported = 2).isRequired(currentVersionCode = 2))
        assertFalse(available(mandatory = false, minSupported = 2).isRequired(currentVersionCode = 10))
    }

    // --- sha256Hex ----------------------------------------------------------

    @Test
    fun `sha256Hex matches the known vector for abc`() {
        val file = File.createTempFile("sha", ".bin")
        try {
            file.writeBytes("abc".toByteArray(Charsets.US_ASCII))
            assertEquals(
                "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad",
                AppUpdateInstaller.sha256Hex(file),
            )
        } finally {
            file.delete()
        }
    }

    @Test
    fun `sha256Hex is 64 lower-case hex chars`() {
        val file = File.createTempFile("sha", ".bin")
        try {
            file.writeBytes(ByteArray(4096) { it.toByte() })
            val hex = AppUpdateInstaller.sha256Hex(file)
            assertEquals(64, hex.length)
            assertTrue(hex.all { it in "0123456789abcdef" })
        } finally {
            file.delete()
        }
    }
}
