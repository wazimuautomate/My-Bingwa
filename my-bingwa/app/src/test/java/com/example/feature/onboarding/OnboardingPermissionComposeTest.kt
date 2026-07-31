package com.example.feature.onboarding

import android.provider.Settings
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.performClick
import com.example.ui.theme.MyBingwaTheme
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.annotation.Config

/**
 * Compose behaviour for the onboarding permission steps (Feature 8).
 *
 * Two deliberate testing choices:
 *
 * - `@Config(sdk = [30])`: onboarding's owner-approved glass look uses
 *   `Modifier.blur`, which only creates a `RenderEffect` on API 31+. Running the
 *   screen at API 30 makes the blur a documented no-op and keeps the test off
 *   Robolectric's render-effect path; nothing else on these steps is API-gated.
 * - `mainClock.autoAdvance = false` plus a zero animator duration scale: the
 *   welcome screens run ambient infinite animations, which would otherwise mean
 *   the tree is never "idle". Time is advanced explicitly after each tap so the
 *   step transition settles.
 */
@RunWith(RobolectricTestRunner::class)
@Config(sdk = [30])
class OnboardingPermissionComposeTest {

    @get:Rule
    val composeRule = createComposeRule()

    @Before
    fun disableSystemAnimations() {
        // Best-effort: makes the screen take its reduced-motion path. The manual
        // clock below is what actually guarantees the test never waits on an
        // infinite animation, so a shadow that rejects this write is harmless.
        runCatching {
            Settings.Global.putFloat(
                RuntimeEnvironment.getApplication().contentResolver,
                Settings.Global.ANIMATOR_DURATION_SCALE,
                0f
            )
        }
        composeRule.mainClock.autoAdvance = false
    }

    private fun settle() {
        composeRule.mainClock.advanceTimeBy(1_000L)
    }

    private fun tapCta() {
        composeRule.onNodeWithTag("onboarding_primary_cta").performClick()
        settle()
    }

    @Test
    fun `permission steps sit between the value screens and the personal setup`() {
        composeRule.setContent {
            MyBingwaTheme {
                OnboardingScreen(onCompleteOnboarding = { _, _ -> })
            }
        }
        // Promise → Gains → Notifications.
        tapCta()
        tapCta()
        composeRule.onNodeWithTag("onboarding_step_notifications", useUnmergedTree = true)
            .assertExists()
    }

    @Test
    fun `the notification cta asks once and the step still continues`() {
        var notificationRequests = 0
        composeRule.setContent {
            MyBingwaTheme {
                OnboardingScreen(
                    onCompleteOnboarding = { _, _ -> },
                    onRequestNotificationPermission = { notificationRequests++ }
                )
            }
        }
        tapCta()
        tapCta()
        composeRule.onNodeWithTag("onboarding_step_notifications", useUnmergedTree = true)
            .assertExists()

        // First tap on the permission step asks the OS...
        tapCta()
        assertEquals(1, notificationRequests)

        // ...and the following tap moves on regardless of the OS answer, so a
        // customer whose dialog no longer appears can never be trapped.
        tapCta()
        assertEquals(1, notificationRequests)
        composeRule.onNodeWithTag("onboarding_step_sms", useUnmergedTree = true).assertExists()
    }

    @Test
    fun `declining with Not now continues the flow without asking`() {
        var notificationRequests = 0
        var smsRequests = 0
        composeRule.setContent {
            MyBingwaTheme {
                OnboardingScreen(
                    onCompleteOnboarding = { _, _ -> },
                    onRequestNotificationPermission = { notificationRequests++ },
                    onRequestSmsPermission = { smsRequests++ }
                )
            }
        }
        tapCta()
        tapCta()

        // Skip notifications, then skip SMS: onboarding continues to the setup step.
        composeRule.onNodeWithTag("onboarding_permission_skip").performClick()
        settle()
        composeRule.onNodeWithTag("onboarding_step_sms", useUnmergedTree = true).assertExists()

        composeRule.onNodeWithTag("onboarding_permission_skip").performClick()
        settle()
        composeRule.onNodeWithTag("onboarding_step_setup", useUnmergedTree = true).assertExists()

        assertEquals(0, notificationRequests)
        assertEquals(0, smsRequests)
    }

    @Test
    fun `the sms cta invokes the sms request callback`() {
        var smsRequests = 0
        composeRule.setContent {
            MyBingwaTheme {
                OnboardingScreen(
                    onCompleteOnboarding = { _, _ -> },
                    onRequestSmsPermission = { smsRequests++ }
                )
            }
        }
        tapCta() // gains
        tapCta() // notifications
        composeRule.onNodeWithTag("onboarding_permission_skip").performClick()
        settle()
        composeRule.onNodeWithTag("onboarding_step_sms", useUnmergedTree = true).assertExists()

        tapCta()
        assertTrue(smsRequests == 1)
    }

    @Test
    fun `the sms step is hidden entirely when sms is not supported`() {
        composeRule.setContent {
            MyBingwaTheme {
                OnboardingScreen(
                    onCompleteOnboarding = { _, _ -> },
                    smsSupported = false
                )
            }
        }
        tapCta()
        tapCta()
        composeRule.onNodeWithTag("onboarding_step_notifications", useUnmergedTree = true)
            .assertExists()

        composeRule.onNodeWithTag("onboarding_permission_skip").performClick()
        settle()
        composeRule.onNodeWithTag("onboarding_step_setup", useUnmergedTree = true).assertExists()
        composeRule.onNodeWithTag("onboarding_step_sms", useUnmergedTree = true)
            .assertDoesNotExist()
    }

    @Test
    fun `the top skip jumps straight to the personal setup`() {
        composeRule.setContent {
            MyBingwaTheme {
                OnboardingScreen(onCompleteOnboarding = { _, _ -> })
            }
        }
        composeRule.onNodeWithTag("onboarding_skip_to_setup").performClick()
        settle()
        composeRule.onNodeWithTag("onboarding_step_setup", useUnmergedTree = true).assertExists()
    }
}
