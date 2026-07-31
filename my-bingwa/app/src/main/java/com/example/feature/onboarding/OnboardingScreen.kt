package com.example.feature.onboarding

import android.provider.Settings
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.systemBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.ArrowForward
import androidx.compose.material.icons.rounded.AutoAwesome
import androidx.compose.material.icons.rounded.Call
import androidx.compose.material.icons.rounded.CardGiftcard
import androidx.compose.material.icons.rounded.CheckCircle
import androidx.compose.material.icons.rounded.Info
import androidx.compose.material.icons.rounded.Lock
import androidx.compose.material.icons.rounded.NotificationsActive
import androidx.compose.material.icons.rounded.Payments
import androidx.compose.material.icons.rounded.Person
import androidx.compose.material.icons.rounded.Phone
import androidx.compose.material.icons.rounded.Sms
import androidx.compose.material.icons.rounded.Wifi
import androidx.compose.material.icons.rounded.WifiOff
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.BlurredEdgeTreatment
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.example.R
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlin.random.Random

// ---------------------------------------------------------------------------
// Onboarding (Phase 2) — premium, animated, glassmorphic first-run experience.
//
// NOTE ON DESIGN DIRECTION: design.md's "Calm Momentum" system forbids
// glassmorphism, ambient gradients, glow and confetti. The product owner has
// explicitly and repeatedly overridden that FOR THE ONBOARDING SCREENS ONLY —
// they want a rich, animated, glass welcome. The rest of the app stays calm.
// This decision is recorded in memory.md.
//
// This file is intentionally self-contained: it depends only on Compose +
// MaterialTheme + the bundled brand logo, so it does not couple to the shared
// design-system module that the Phase 1 session is still shaping.
// ---------------------------------------------------------------------------

private val BrandDeepGreen = Color(0xFF006B27)
private val BrandBrightGreen = Color(0xFF18C964)
private val AccentData = Color(0xFF3BA9FF)
private val AccentSms = Color(0xFF7C6CF2)
private val AccentMinutes = Color(0xFF18C964)
private val AccentSpecial = Color(0xFFFF8A00)

/**
 * The ordered first-run steps. Permission asks sit AFTER the value is clear
 * (promise + gains) and BEFORE the personal setup, so the customer already knows
 * what My Bingwa does when it explains why it wants a permission. [SMS] is
 * dropped entirely on the Play flavour, where the SMS receiver is stripped from
 * the manifest and the permission does not exist.
 */
private enum class OnboardingStep { PROMISE, GAINS, NOTIFICATIONS, SMS, SETUP }

/**
 * First-run onboarding.
 *
 * Runtime permissions are requested HERE rather than from Settings: adoption
 * from a buried settings row was poor. Each ask is preceded by a short, plain
 * explanation of what the permission buys the customer, shown before the system
 * dialog appears (CLAUDE.md §9 — never ask cold).
 *
 * Declining is a first-class outcome: the step can always be skipped, the flow
 * always continues, only that one feature is disabled, and the customer is never
 * scolded or asked twice in the same session.
 *
 * The Activity owns the `rememberLauncherForActivityResult` launchers; this
 * screen only calls back and renders the granted state it is given. Every new
 * parameter is defaulted so existing callers and tests keep compiling.
 *
 * @param onRequestNotificationPermission asks the OS for POST_NOTIFICATIONS.
 * @param onRequestSmsPermission asks the OS for RECEIVE_SMS/READ_SMS.
 * @param notificationsGranted live grant state, for the confirmation UI.
 * @param smsGranted live grant state, for the confirmation UI.
 * @param smsSupported false on the Play flavour — hides the SMS step entirely.
 */
@Composable
fun OnboardingScreen(
    onCompleteOnboarding: (String, String) -> Unit,
    onRequestNotificationPermission: () -> Unit = {},
    onRequestSmsPermission: () -> Unit = {},
    notificationsGranted: Boolean = false,
    smsGranted: Boolean = false,
    smsSupported: Boolean = true
) {
    val reducedMotion = rememberReducedMotion()
    val scope = rememberCoroutineScope()

    val steps = remember(smsSupported) {
        buildList {
            add(OnboardingStep.PROMISE)
            add(OnboardingStep.GAINS)
            add(OnboardingStep.NOTIFICATIONS)
            if (smsSupported) add(OnboardingStep.SMS)
            add(OnboardingStep.SETUP)
        }
    }
    var stepIndex by remember { mutableIntStateOf(0) }
    // Clamp defensively: smsSupported could flip while onboarding is open.
    val safeIndex = stepIndex.coerceIn(0, steps.lastIndex)
    val currentStep = steps[safeIndex]

    // "Asked once" flags. After one ask the CTA becomes a plain Continue, so a
    // customer whose OS no longer shows the dialog can never be trapped on the
    // step by tapping a button that appears to do nothing.
    var askedNotifications by remember { mutableStateOf(false) }
    var askedSms by remember { mutableStateOf(false) }

    var nameInput by remember { mutableStateOf("") }
    var phoneInput by remember { mutableStateOf("") }
    var nameError by remember { mutableStateOf<String?>(null) }
    var phoneError by remember { mutableStateOf<String?>(null) }
    var launching by remember { mutableStateOf(false) }

    fun advance() {
        if (safeIndex < steps.lastIndex) stepIndex = safeIndex + 1
    }

    fun finish() {
        val trimmedName = nameInput.trim()
        val normalized = normalizeKenyanPhone(phoneInput)
        nameError = if (trimmedName.length < 2) "Enter your name" else null
        phoneError = if (normalized == null) "Enter a valid Safaricom number" else null
        if (nameError != null || phoneError != null) return

        if (reducedMotion) {
            onCompleteOnboarding(trimmedName, normalized!!)
        } else {
            launching = true
            scope.launch {
                delay(1150)
                onCompleteOnboarding(trimmedName, normalized!!)
            }
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        AmbientBackdrop(reducedMotion = reducedMotion)

        Column(
            modifier = Modifier
                .fillMaxSize()
                .systemBarsPadding()
                .imePadding()
                .padding(horizontal = 24.dp, vertical = 12.dp)
        ) {
            // Top: filling progress track + skip to setup.
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                StepProgress(step = safeIndex + 1, total = steps.size)
                if (safeIndex < steps.lastIndex) {
                    TextButton(
                        onClick = { stepIndex = steps.lastIndex },
                        modifier = Modifier.testTag("onboarding_skip_to_setup")
                    ) {
                        Text(
                            text = "Skip",
                            style = MaterialTheme.typography.labelLarge,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                } else {
                    Spacer(Modifier.height(40.dp))
                }
            }

            AnimatedContent(
                targetState = safeIndex,
                transitionSpec = {
                    (slideInHorizontally(tween(420)) { w -> w / 2 } + fadeIn(tween(420))) togetherWith
                        (slideOutHorizontally(tween(420)) { w -> -w / 2 } + fadeOut(tween(220)))
                },
                label = "onboarding_step",
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
            ) { index ->
                when (steps.getOrNull(index) ?: OnboardingStep.SETUP) {
                    OnboardingStep.PROMISE -> StepPromise(reducedMotion = reducedMotion)
                    OnboardingStep.GAINS -> StepGains(reducedMotion = reducedMotion)
                    OnboardingStep.NOTIFICATIONS -> StepNotifications(
                        granted = notificationsGranted,
                        asked = askedNotifications,
                        reducedMotion = reducedMotion
                    )
                    OnboardingStep.SMS -> StepSms(
                        granted = smsGranted,
                        asked = askedSms,
                        reducedMotion = reducedMotion
                    )
                    OnboardingStep.SETUP -> StepSetup(
                        name = nameInput,
                        phone = phoneInput,
                        nameError = nameError,
                        phoneError = phoneError,
                        reducedMotion = reducedMotion,
                        onNameChange = { nameInput = it; nameError = null },
                        onPhoneChange = { phoneInput = it; phoneError = null }
                    )
                }
            }

            Spacer(Modifier.height(12.dp))

            // One dominant CTA per step. On a permission step the first tap opens
            // the system dialog; afterwards — granted or denied — it simply moves
            // on, so the customer is never stuck behind an OS decision.
            PrimaryCtaButton(
                text = when (currentStep) {
                    OnboardingStep.PROMISE -> "Get started"
                    OnboardingStep.GAINS -> "I love it! Continue."
                    OnboardingStep.NOTIFICATIONS ->
                        if (notificationsGranted || askedNotifications) "Continue" else "Turn on updates"
                    OnboardingStep.SMS ->
                        if (smsGranted || askedSms) "Continue" else "Allow bundle messages"
                    OnboardingStep.SETUP -> "Start using My Bingwa"
                },
                enabled = !launching,
                modifier = Modifier.testTag("onboarding_primary_cta"),
                onClick = {
                    when (currentStep) {
                        OnboardingStep.NOTIFICATIONS ->
                            if (notificationsGranted || askedNotifications) {
                                advance()
                            } else {
                                askedNotifications = true
                                onRequestNotificationPermission()
                            }
                        OnboardingStep.SMS ->
                            if (smsGranted || askedSms) {
                                advance()
                            } else {
                                askedSms = true
                                onRequestSmsPermission()
                            }
                        OnboardingStep.SETUP -> finish()
                        else -> advance()
                    }
                }
            )

            // Always-available escape hatch on a permission step. No nagging, no
            // second ask, no scolding — the feature is simply left off.
            if (currentStep == OnboardingStep.NOTIFICATIONS || currentStep == OnboardingStep.SMS) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.Center
                ) {
                    TextButton(
                        onClick = { advance() },
                        modifier = Modifier.testTag("onboarding_permission_skip")
                    ) {
                        Text(
                            text = "Not now",
                            style = MaterialTheme.typography.labelLarge,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }

            Spacer(Modifier.height(8.dp))
        }

        if (launching) {
            ConfettiOverlay()
        }
    }
}

// ---------------------------------------------------------------------------
// Ambient animated backdrop — soft, slowly drifting blurred colour orbs that
// give the glass surfaces something to frost over.
// ---------------------------------------------------------------------------

@Composable
private fun AmbientBackdrop(reducedMotion: Boolean) {
    val transition = rememberInfiniteTransition(label = "ambient")
    val drift by if (reducedMotion) {
        remember { mutableStateOf(0f) }
    } else {
        transition.animateFloatAlt(
            initial = 0f,
            target = 1f,
            durationMillis = 9000,
            label = "drift"
        )
    }

    Box(modifier = Modifier.fillMaxSize()) {
        Box(
            modifier = Modifier
                .size(320.dp)
                .graphicsLayer {
                    translationX = -120f + drift * 90f
                    translationY = -60f + drift * 70f
                }
                .blur(90.dp, BlurredEdgeTreatment.Unbounded)
                .background(
                    Brush.radialGradient(
                        colors = listOf(BrandBrightGreen.copy(alpha = 0.40f), Color.Transparent)
                    ),
                    shape = CircleShape
                )
        )
        Box(
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .size(340.dp)
                .graphicsLayer {
                    translationX = 120f - drift * 80f
                    translationY = 80f - drift * 90f
                }
                .blur(100.dp, BlurredEdgeTreatment.Unbounded)
                .background(
                    Brush.radialGradient(
                        colors = listOf(AccentData.copy(alpha = 0.34f), Color.Transparent)
                    ),
                    shape = CircleShape
                )
        )
        Box(
            modifier = Modifier
                .align(Alignment.CenterEnd)
                .size(240.dp)
                .graphicsLayer {
                    translationX = 80f - drift * 60f
                    translationY = -40f + drift * 60f
                }
                .blur(90.dp, BlurredEdgeTreatment.Unbounded)
                .background(
                    Brush.radialGradient(
                        colors = listOf(AccentSpecial.copy(alpha = 0.22f), Color.Transparent)
                    ),
                    shape = CircleShape
                )
        )
    }
}

// ---------------------------------------------------------------------------
// Screen 1 — Main Promise: animated hero logo, staggered title/subtitle.
// ---------------------------------------------------------------------------

@Composable
private fun StepPromise(reducedMotion: Boolean) {
    val intro = remember { Animatable(if (reducedMotion) 1f else 0f) }
    LaunchedEffect(Unit) {
        if (!reducedMotion) intro.animateTo(1f, tween(900, easing = FastOutSlowInEasing))
    }
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Spacer(Modifier.height(24.dp))
        LogoHero(reducedMotion = reducedMotion)
        Spacer(Modifier.height(40.dp))

        StaggeredItem(intro.value, 0.15f) {
            Text(
                text = "Welcome to My Bingwa",
                style = MaterialTheme.typography.displaySmall,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onBackground,
                textAlign = TextAlign.Center
            )
        }
        Spacer(Modifier.height(14.dp))
        StaggeredItem(intro.value, 0.32f) {
            Text(
                text = "Buy data, SMS and minutes — even with unpaid Okoa Jahazi.",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(horizontal = 12.dp)
            )
        }
        Spacer(Modifier.height(24.dp))
    }
}

@Composable
private fun LogoHero(reducedMotion: Boolean) {
    // Entrance: overshoot scale-in + upward settle.
    val enter = remember { Animatable(if (reducedMotion) 1f else 0f) }
    LaunchedEffect(Unit) {
        if (!reducedMotion) {
            enter.animateTo(
                1f,
                spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow)
            )
        }
    }
    // Ambient: gentle float + a slowly rotating conic glow ring behind the mark.
    val transition = rememberInfiniteTransition(label = "logo")
    val float by if (reducedMotion) remember { mutableStateOf(0f) } else
        transition.animateFloatAlt(0f, 1f, 3200, "float")
    val spin by if (reducedMotion) remember { mutableStateOf(0f) } else
        transition.animateFloatLinear(0f, 360f, 14000, "spin")

    Box(contentAlignment = Alignment.Center) {
        // Rotating glow ring.
        Box(
            modifier = Modifier
                .size(250.dp)
                .graphicsLayer {
                    rotationZ = spin
                    alpha = 0.9f * enter.value
                }
                .blur(18.dp, BlurredEdgeTreatment.Unbounded)
                .background(
                    Brush.sweepGradient(
                        colors = listOf(
                            BrandBrightGreen.copy(alpha = 0.0f),
                            BrandBrightGreen.copy(alpha = 0.55f),
                            AccentData.copy(alpha = 0.45f),
                            AccentSpecial.copy(alpha = 0.40f),
                            BrandBrightGreen.copy(alpha = 0.0f)
                        )
                    ),
                    shape = CircleShape
                )
        )
        // Soft halo.
        Box(
            modifier = Modifier
                .size(200.dp)
                .graphicsLayer { alpha = enter.value }
                .blur(30.dp, BlurredEdgeTreatment.Unbounded)
                .background(
                    Brush.radialGradient(
                        colors = listOf(BrandBrightGreen.copy(alpha = 0.30f), Color.Transparent)
                    ),
                    shape = CircleShape
                )
        )
        // The real brand mark.
        Image(
            painter = painterResource(id = R.drawable.img_onboarding_logo),
            contentDescription = "My Bingwa",
            modifier = Modifier
                .size(184.dp)
                .graphicsLayer {
                    val s = 0.6f + 0.4f * enter.value
                    scaleX = s
                    scaleY = s
                    alpha = enter.value
                    translationY = (1f - enter.value) * 40f + (float - 0.5f) * 16f
                }
        )
    }
}

// ---------------------------------------------------------------------------
// Screen 2 — What You Gain: category glyphs with glow rings + glass benefits.
// ---------------------------------------------------------------------------

@Composable
private fun StepGains(reducedMotion: Boolean) {
    val intro = remember { Animatable(if (reducedMotion) 1f else 0f) }
    LaunchedEffect(Unit) {
        if (!reducedMotion) intro.animateTo(1f, tween(1000, easing = FastOutSlowInEasing))
    }
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(8.dp))
        StaggeredItem(intro.value, 0.05f) {
            Text(
                text = "Buy what you need, anytime, anywhere.",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onBackground,
                textAlign = TextAlign.Center
            )
        }
        Spacer(Modifier.height(28.dp))

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceEvenly
        ) {
            CategoryGlyph(Icons.Rounded.Wifi, "Data", AccentData, intro.value, 0.10f, reducedMotion)
            CategoryGlyph(Icons.Rounded.Sms, "SMS", AccentSms, intro.value, 0.18f, reducedMotion)
            CategoryGlyph(Icons.Rounded.Call, "Minutes", AccentMinutes, intro.value, 0.26f, reducedMotion)
            CategoryGlyph(Icons.Rounded.AutoAwesome, "Special", AccentSpecial, intro.value, 0.34f, reducedMotion)
        }

        Spacer(Modifier.height(30.dp))

        BenefitGlassCard(
            icon = Icons.Rounded.Payments,
            accent = BrandDeepGreen,
            title = "Pay easily with M-Pesa",
            body = "Approve the payment directly from your phone.",
            progress = intro.value,
            delay = 0.42f
        )
        Spacer(Modifier.height(14.dp))
        BenefitGlassCard(
            icon = Icons.Rounded.CardGiftcard,
            accent = AccentSpecial,
            title = "Gift others",
            body = "You can buy for another number with ease.",
            progress = intro.value,
            delay = 0.54f
        )
        Spacer(Modifier.height(14.dp))
        BenefitGlassCard(
            icon = Icons.Rounded.WifiOff,
            accent = AccentData,
            title = "Buy even when offline",
            body = "Use Till number and Paybill to buy anytime when offline.",
            progress = intro.value,
            delay = 0.66f
        )
        Spacer(Modifier.height(16.dp))
    }
}

@Composable
private fun CategoryGlyph(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    accent: Color,
    progress: Float,
    delay: Float,
    reducedMotion: Boolean
) {
    // Drop-in with a soft settle.
    val appear = slice(progress, delay, delay + 0.4f)
    val transition = rememberInfiniteTransition(label = "glyph_$label")
    val spin by if (reducedMotion) remember { mutableStateOf(0f) } else
        transition.animateFloatLinear(0f, 360f, 9000, "spin_$label")

    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Box(contentAlignment = Alignment.Center) {
            // Rotating glow ring.
            Box(
                modifier = Modifier
                    .size(64.dp)
                    .graphicsLayer { rotationZ = spin; alpha = appear }
                    .blur(10.dp, BlurredEdgeTreatment.Unbounded)
                    .background(
                        Brush.sweepGradient(
                            listOf(
                                accent.copy(alpha = 0f),
                                accent.copy(alpha = 0.6f),
                                accent.copy(alpha = 0f)
                            )
                        ),
                        shape = CircleShape
                    )
            )
            Box(
                modifier = Modifier
                    .size(56.dp)
                    .graphicsLayer {
                        alpha = appear
                        val s = 0.6f + 0.4f * appear
                        scaleX = s
                        scaleY = s
                        translationY = (1f - appear) * -28f
                    }
                    .clip(CircleShape)
                    .background(accent.copy(alpha = 0.16f))
                    .border(1.dp, accent.copy(alpha = 0.35f), CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, contentDescription = label, tint = accent, modifier = Modifier.size(26.dp))
            }
        }
        Spacer(Modifier.height(8.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.graphicsLayer { alpha = appear }
        )
    }
}

@Composable
private fun BenefitGlassCard(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    accent: Color,
    title: String,
    body: String,
    progress: Float,
    delay: Float
) {
    val appear = slice(progress, delay, delay + 0.4f)
    GlassSurface(
        modifier = Modifier
            .fillMaxWidth()
            .graphicsLayer {
                alpha = appear
                translationX = (1f - appear) * 60f
            }
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(44.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .background(accent.copy(alpha = 0.18f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, contentDescription = null, tint = accent, modifier = Modifier.size(24.dp))
            }
            Spacer(Modifier.width(14.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = body,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Permission steps — explain first, ask second, never trap.
//
// Both steps follow the same shape: a centred glyph and title (centre-anchored
// composition), then START-aligned explanation copy inside the existing glass
// card (CLAUDE.md §6 — paragraphs are never centred). Flat fills only: no new
// gradient, no glow, no emoji. The single dominant CTA lives in the shared
// footer above, with a quiet "Not now" beneath it.
// ---------------------------------------------------------------------------

@Composable
private fun StepNotifications(granted: Boolean, asked: Boolean, reducedMotion: Boolean) {
    PermissionStep(
        icon = Icons.Rounded.NotificationsActive,
        accent = AccentData,
        stepTag = "onboarding_step_notifications",
        title = "Know the moment it lands",
        lead = "Allow notifications and My Bingwa will tell you when your M-Pesa " +
            "payment is received and when a bundle you buy often is back in stock.",
        bullets = listOf(
            "Payment updates for the purchases you make.",
            "Quiet hours are respected — nothing wakes you at night.",
            "Offers are a separate, optional channel you control in Settings."
        ),
        privacyNote = "You can change this any time in Settings.",
        granted = granted,
        grantedText = "Notifications are on. We will keep them useful, not noisy.",
        deniedText = "No problem. My Bingwa still works — you can turn notifications " +
            "on later in Settings.",
        asked = asked,
        reducedMotion = reducedMotion
    )
}

@Composable
private fun StepSms(granted: Boolean, asked: Boolean, reducedMotion: Boolean) {
    PermissionStep(
        icon = Icons.Rounded.Sms,
        accent = AccentSms,
        stepTag = "onboarding_step_sms",
        title = "Keep track of your bundles",
        lead = "My Bingwa reads only Safaricom bundle messages so it can notify you " +
            "when your bundles are running low and automatically update your bundle " +
            "status. We never upload or store your SMS. Everything stays on your phone.",
        bullets = listOf(
            "Only Safaricom bundle and balance messages are read.",
            "Personal messages are ignored and never opened.",
            "Nothing is uploaded — no server ever sees your SMS."
        ),
        privacyNote = "You can change this any time in Settings.",
        granted = granted,
        grantedText = "Bundle tracking is on. Everything stays on this phone.",
        deniedText = "No problem. My Bingwa still works — bundle tracking simply " +
            "stays off until you turn it on in Settings.",
        asked = asked,
        reducedMotion = reducedMotion
    )
}

@Composable
private fun PermissionStep(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    accent: Color,
    stepTag: String,
    title: String,
    lead: String,
    bullets: List<String>,
    privacyNote: String,
    granted: Boolean,
    grantedText: String,
    deniedText: String,
    asked: Boolean,
    reducedMotion: Boolean
) {
    val intro = remember { Animatable(if (reducedMotion) 1f else 0f) }
    LaunchedEffect(Unit) {
        if (!reducedMotion) intro.animateTo(1f, tween(700, easing = FastOutSlowInEasing))
    }
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .testTag(stepTag),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(16.dp))

        StaggeredItem(intro.value, 0.05f) {
            Box(
                modifier = Modifier
                    .size(72.dp)
                    .clip(CircleShape)
                    .background(accent.copy(alpha = 0.16f))
                    .border(1.dp, accent.copy(alpha = 0.35f), CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, contentDescription = null, tint = accent, modifier = Modifier.size(34.dp))
            }
        }

        Spacer(Modifier.height(20.dp))

        StaggeredItem(intro.value, 0.14f) {
            Text(
                text = title,
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onBackground,
                textAlign = TextAlign.Center
            )
        }

        Spacer(Modifier.height(18.dp))

        StaggeredItem(intro.value, 0.24f) {
            GlassSurface(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(18.dp)) {
                    // Explanations are START-aligned paragraphs (CLAUDE.md §6).
                    Text(
                        text = lead,
                        style = MaterialTheme.typography.bodyLarge,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Spacer(Modifier.height(14.dp))
                    bullets.forEach { line ->
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(bottom = 8.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .padding(top = 7.dp)
                                    .size(6.dp)
                                    .clip(CircleShape)
                                    .background(accent)
                            )
                            Spacer(Modifier.width(10.dp))
                            Text(
                                text = line,
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                    Spacer(Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            Icons.Rounded.Lock,
                            contentDescription = null,
                            tint = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(Modifier.width(8.dp))
                        Text(
                            text = privacyNote,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }

        // Outcome line. Granted is confirmed; a decline is acknowledged calmly and
        // is never repeated or judged.
        if (granted || asked) {
            Spacer(Modifier.height(14.dp))
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .testTag("${stepTag}_status"),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = if (granted) Icons.Rounded.CheckCircle else Icons.Rounded.Info,
                    contentDescription = null,
                    tint = if (granted) BrandDeepGreen else MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    text = if (granted) grantedText else deniedText,
                    style = MaterialTheme.typography.bodyMedium,
                    color = if (granted) BrandDeepGreen else MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }

        Spacer(Modifier.height(20.dp))
    }
}

// ---------------------------------------------------------------------------
// Final step — Personal Setup: name slides from left, phone from right.
// ---------------------------------------------------------------------------

@Composable
private fun StepSetup(
    name: String,
    phone: String,
    nameError: String?,
    phoneError: String?,
    reducedMotion: Boolean,
    onNameChange: (String) -> Unit,
    onPhoneChange: (String) -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .testTag("onboarding_step_setup"),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(12.dp))
        Text(
            text = "Make My Bingwa yours.",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground,
            textAlign = TextAlign.Center
        )
        Spacer(Modifier.height(10.dp))
        Text(
            text = "Just your name and phone number. No account needed.",
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(horizontal = 12.dp)
        )
        Spacer(Modifier.height(28.dp))

        SlideIn(fromLeft = true, reducedMotion = reducedMotion) {
            GlassSurface(modifier = Modifier.fillMaxWidth()) {
                OutlinedTextField(
                    value = name,
                    onValueChange = onNameChange,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(4.dp),
                    label = { Text("Your name") },
                    leadingIcon = { Icon(Icons.Rounded.Person, contentDescription = null) },
                    singleLine = true,
                    isError = nameError != null,
                    shape = RoundedCornerShape(16.dp),
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Color.Transparent, unfocusedBorderColor = Color.Transparent, errorBorderColor = Color.Transparent, disabledBorderColor = Color.Transparent)
                )
            }
        }
        if (nameError != null) FieldError(nameError)

        Spacer(Modifier.height(16.dp))

        SlideIn(fromLeft = false, reducedMotion = reducedMotion) {
            GlassSurface(modifier = Modifier.fillMaxWidth()) {
                OutlinedTextField(
                    value = phone,
                    onValueChange = onPhoneChange,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(4.dp),
                    label = { Text("Safaricom number") },
                    placeholder = { Text("07XX XXX XXX") },
                    leadingIcon = { Icon(Icons.Rounded.Phone, contentDescription = null) },
                    singleLine = true,
                    isError = phoneError != null,
                    keyboardOptions = KeyboardOptions(keyboardType = androidx.compose.ui.text.input.KeyboardType.Phone),
                    shape = RoundedCornerShape(16.dp),
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Color.Transparent, unfocusedBorderColor = Color.Transparent, errorBorderColor = Color.Transparent, disabledBorderColor = Color.Transparent)
                )
            }
        }
        if (phoneError != null) FieldError(phoneError)

        Spacer(Modifier.height(20.dp))
    }
}

@Composable
private fun FieldError(message: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(start = 8.dp, top = 6.dp)
    ) {
        Text(
            text = message,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.error
        )
    }
}

// ---------------------------------------------------------------------------
// Shared pieces
// ---------------------------------------------------------------------------

@Composable
private fun GlassSurface(
    modifier: Modifier = Modifier,
    content: @Composable () -> Unit
) {
    val dark = MaterialTheme.colorScheme.background.luminanceIsDark()
    val fill = if (dark) Color.White.copy(alpha = 0.07f) else Color.White.copy(alpha = 0.55f)
    val stroke = if (dark) Color.White.copy(alpha = 0.16f) else Color.White.copy(alpha = 0.70f)
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(22.dp))
            .background(fill)
            .border(1.dp, stroke, RoundedCornerShape(22.dp))
    ) {
        content()
    }
}

@Composable
private fun StaggeredItem(progress: Float, delay: Float, content: @Composable () -> Unit) {
    val appear = slice(progress, delay, delay + 0.5f)
    Box(
        modifier = Modifier.graphicsLayer {
            alpha = appear
            translationY = (1f - appear) * 28f
        }
    ) { content() }
}

@Composable
private fun SlideIn(fromLeft: Boolean, reducedMotion: Boolean, content: @Composable () -> Unit) {
    val slidePx = with(LocalDensity.current) { 140.dp.toPx() }
    val anim = remember { Animatable(if (reducedMotion) 1f else 0f) }
    LaunchedEffect(Unit) {
        if (!reducedMotion) {
            anim.animateTo(1f, spring(dampingRatio = 0.72f, stiffness = 260f))
        }
    }
    Box(
        modifier = Modifier.graphicsLayer {
            alpha = anim.value
            translationX = (1f - anim.value) * if (fromLeft) -slidePx else slidePx
        }
    ) { content() }
}

@Composable
private fun StepProgress(step: Int, total: Int) {
    val target = step.toFloat() / total.coerceAtLeast(1)
    val fill by animateFloatAsState(target, tween(500, easing = FastOutSlowInEasing), label = "progress")
    Box(
        modifier = Modifier
            .width(120.dp)
            .height(6.dp)
            .clip(CircleShape)
            .background(MaterialTheme.colorScheme.onSurface.copy(alpha = 0.12f))
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth(fill)
                .height(6.dp)
                .clip(CircleShape)
                .background(
                    Brush.horizontalGradient(listOf(BrandDeepGreen, BrandBrightGreen))
                )
        )
    }
}

@Composable
private fun PrimaryCtaButton(
    text: String,
    enabled: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(56.dp)
            .graphicsLayer { alpha = if (enabled) 1f else 0.6f }
            .clip(RoundedCornerShape(18.dp))
            .background(Brush.horizontalGradient(listOf(BrandDeepGreen, Color(0xFF00913A))))
            .clickable(enabled = enabled) { onClick() },
        contentAlignment = Alignment.Center
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                text = text,
                color = Color.White,
                style = MaterialTheme.typography.labelLarge,
                fontWeight = FontWeight.SemiBold
            )
            Spacer(Modifier.width(8.dp))
            Icon(
                Icons.Rounded.ArrowForward,
                contentDescription = null,
                tint = Color.White,
                modifier = Modifier.size(20.dp)
            )
        }
    }
}

// ---------------------------------------------------------------------------
// Confetti burst (Screen 3 finish)
// ---------------------------------------------------------------------------

private data class Confetti(
    val xFrac: Float,
    val delay: Float,
    val colorIndex: Int,
    val spin: Float,
    val drift: Float,
    val size: Float,
    val launch: Float,
    val fall: Float
)

@Composable
private fun ConfettiOverlay() {
    val pieces = remember {
        List(110) {
            Confetti(
                xFrac = 0.5f + (Random.nextFloat() - 0.5f) * 0.5f,
                delay = Random.nextFloat() * 0.18f,
                colorIndex = Random.nextInt(4),
                spin = (Random.nextFloat() - 0.5f) * 10f,
                drift = (Random.nextFloat() - 0.5f) * 0.7f,
                size = 10f + Random.nextFloat() * 12f,
                launch = 0.55f + Random.nextFloat() * 0.5f,
                fall = 0.9f + Random.nextFloat() * 0.6f
            )
        }
    }
    val colors = listOf(BrandDeepGreen, BrandBrightGreen, AccentData, AccentSpecial)
    val progress = remember { Animatable(0f) }
    LaunchedEffect(Unit) { progress.animateTo(1f, tween(1300, easing = LinearEasing)) }

    Canvas(modifier = Modifier.fillMaxSize()) {
        val p = progress.value
        val originY = size.height * 0.82f
        pieces.forEach { c ->
            val local = ((p - c.delay) / (1f - c.delay)).coerceIn(0f, 1f)
            if (local <= 0f) return@forEach
            val x = c.xFrac * size.width + c.drift * local * size.width
            // Up then down: parabolic burst from the button area.
            val y = originY - c.launch * local * size.height + c.fall * local * local * size.height
            val alpha = (1f - local * local).coerceIn(0f, 1f)
            val deg = local * 360f * c.spin
            val col = colors[c.colorIndex].copy(alpha = alpha)
            rotate(degrees = deg, pivot = Offset(x, y)) {
                drawRect(
                    color = col,
                    topLeft = Offset(x - c.size / 2f, y - c.size / 2f),
                    size = Size(c.size, c.size * 0.55f)
                )
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Maps a global 0..1 progress onto a windowed 0..1 slice for staggering. */
private fun slice(progress: Float, start: Float, end: Float): Float =
    ((progress - start) / (end - start)).coerceIn(0f, 1f)

/** Reads the system "remove animations" accessibility setting. */
@Composable
private fun rememberReducedMotion(): Boolean {
    val context = LocalContext.current
    return remember {
        runCatching {
            Settings.Global.getFloat(
                context.contentResolver,
                Settings.Global.ANIMATOR_DURATION_SCALE,
                1f
            ) == 0f
        }.getOrDefault(false)
    }
}

/**
 * Normalises a Kenyan Safaricom number to canonical 07XXXXXXXX / 01XXXXXXXX.
 * Accepts 07.., 01.., 7.., 1.., +2547.., 2547.., with spaces. Returns null if
 * it is not a plausible 10-digit Safaricom mobile number.
 */
internal fun normalizeKenyanPhone(raw: String): String? {
    val digits = raw.filter { it.isDigit() }
    val national = when {
        digits.length == 12 && digits.startsWith("254") -> "0" + digits.substring(3)
        digits.length == 10 && digits.startsWith("0") -> digits
        digits.length == 9 && (digits.startsWith("7") || digits.startsWith("1")) -> "0$digits"
        else -> null
    } ?: return null
    val ok = national.length == 10 && (national.startsWith("07") || national.startsWith("01"))
    return if (ok) national else null
}

// --- small infinite-transition conveniences (kept local to avoid coupling) ---

@Composable
private fun androidx.compose.animation.core.InfiniteTransition.animateFloatAlt(
    initial: Float,
    target: Float,
    durationMillis: Int,
    label: String
) = animateFloat(
    initialValue = initial,
    targetValue = target,
    animationSpec = infiniteRepeatable(tween(durationMillis, easing = FastOutSlowInEasing), RepeatMode.Reverse),
    label = label
)

@Composable
private fun androidx.compose.animation.core.InfiniteTransition.animateFloatLinear(
    initial: Float,
    target: Float,
    durationMillis: Int,
    label: String
) = animateFloat(
    initialValue = initial,
    targetValue = target,
    animationSpec = infiniteRepeatable(tween(durationMillis, easing = LinearEasing), RepeatMode.Restart),
    label = label
)

/** Rough luminance check so glass fills adapt to light/dark backgrounds. */
private fun Color.luminanceIsDark(): Boolean {
    val l = 0.299f * red + 0.587f * green + 0.114f * blue
    return l < 0.5f
}
