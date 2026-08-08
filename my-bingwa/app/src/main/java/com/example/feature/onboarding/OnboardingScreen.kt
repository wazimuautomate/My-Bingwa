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
import androidx.compose.material.icons.rounded.Notifications
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
import androidx.compose.runtime.saveable.rememberSaveable
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
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.example.BuildConfig
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

private const val TOTAL_STEPS = 4

/**
 * @param notificationsGranted live OS state of POST_NOTIFICATIONS.
 * @param smsGranted           live OS state of RECEIVE_SMS.
 * @param onRequestNotifications launches the system permission prompt.
 * @param onRequestSms           launches the system permission prompt.
 */
@Composable
fun OnboardingScreen(
    onCompleteOnboarding: (String, String) -> Unit,
    notificationsGranted: Boolean = false,
    smsGranted: Boolean = false,
    onRequestNotifications: () -> Unit = {},
    onRequestSms: () -> Unit = {}
) {
    val reducedMotion = rememberReducedMotion()
    val scope = rememberCoroutineScope()

    var step by remember { mutableIntStateOf(1) }
    var nameInput by remember { mutableStateOf("") }
    var phoneInput by remember { mutableStateOf("") }
    var nameError by remember { mutableStateOf<String?>(null) }
    var phoneError by remember { mutableStateOf<String?>(null) }
    var launching by remember { mutableStateOf(false) }

    // How many times a permission prompt has been fired from this screen. Android stops
    // showing a runtime dialog after the second refusal of the same permission, so
    // insisting past that would trap the customer on a screen whose button does nothing.
    // Four taps covers two asks for each permission; after that the journey continues and
    // Settings keeps both switches available.
    var permissionAsks by rememberSaveable { mutableIntStateOf(0) }

    // SMS only counts when the flavour actually ships the permission.
    val smsRequired = BuildConfig.SMS_DETECTION_AVAILABLE
    val allPermissionsGranted = notificationsGranted && (smsGranted || !smsRequired)
    val permissionsSettled = allPermissionsGranted || permissionAsks >= 4

    /**
     * Ask for ONE permission per tap. Firing both launchers together races: the second
     * request arrives while the first dialog owns the screen and is silently dropped, so
     * the customer would never see it.
     */
    fun requestMissingPermissions() {
        permissionAsks += 1
        when {
            !notificationsGranted -> onRequestNotifications()
            smsRequired && !smsGranted -> onRequestSms()
        }
    }

    /** True when the typed name and number are usable; sets the inline errors otherwise. */
    fun validateSetup(): Boolean {
        val trimmedName = nameInput.trim()
        val normalized = normalizeKenyanPhone(phoneInput)
        nameError = if (trimmedName.length < 2) "Enter your name" else null
        phoneError = if (normalized == null) "Enter a valid Safaricom number" else null
        return nameError == null && phoneError == null
    }

    fun finish() {
        val trimmedName = nameInput.trim()
        val normalized = normalizeKenyanPhone(phoneInput)
        if (!validateSetup()) {
            // Should be unreachable (step 3 gates on the same check) but keeps the
            // completion callback from ever firing with an unusable profile.
            step = 3
            return
        }

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
                StepProgress(step = step)
                // Skip jumps to the SETUP step (3), never past it — name, number and
                // the permission ask are all required, only the two intro slides are not.
                if (step < 3) {
                    TextButton(onClick = { step = 3 }) {
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
                targetState = step,
                transitionSpec = {
                    (slideInHorizontally(tween(420)) { w -> w / 2 } + fadeIn(tween(420))) togetherWith
                        (slideOutHorizontally(tween(420)) { w -> -w / 2 } + fadeOut(tween(220)))
                },
                label = "onboarding_step",
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
            ) { current ->
                when (current) {
                    1 -> StepPromise(reducedMotion = reducedMotion)
                    2 -> StepGains(reducedMotion = reducedMotion)
                    3 -> StepSetup(
                        name = nameInput,
                        phone = phoneInput,
                        nameError = nameError,
                        phoneError = phoneError,
                        reducedMotion = reducedMotion,
                        onNameChange = { nameInput = it; nameError = null },
                        onPhoneChange = { phoneInput = it; phoneError = null }
                    )
                    else -> StepPermissions(
                        notificationsGranted = notificationsGranted,
                        smsGranted = smsGranted,
                        onRequestNotifications = onRequestNotifications,
                        onRequestSms = onRequestSms
                    )
                }
            }

            Spacer(Modifier.height(12.dp))

            PrimaryCtaButton(
                text = when (step) {
                    1 -> "Get started"
                    2 -> "I love it! Continue."
                    3 -> "Continue"
                    // Step 4 asks for the two permissions. While either is still
                    // missing the button re-opens the prompts rather than moving on,
                    // so the customer is asked here and not left to find Settings.
                    else -> if (permissionsSettled) "Start using My Bingwa" else "Allow and continue"
                },
                enabled = !launching,
                onClick = {
                    when (step) {
                        1, 2 -> step += 1
                        // Validate name/number BEFORE the permissions step so a
                        // customer is never asked for permissions and then bounced
                        // back to fix a typo.
                        3 -> if (validateSetup()) step += 1
                        else -> if (permissionsSettled) finish() else requestMissingPermissions()
                    }
                }
            )

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
// Screen 3 — Personal Setup: name slides from left, phone from right.
// ---------------------------------------------------------------------------

@Composable
private fun StepPermissions(
    notificationsGranted: Boolean,
    smsGranted: Boolean,
    onRequestNotifications: () -> Unit,
    onRequestSms: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(12.dp))
        Text(
            text = "Two last things.",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground,
            textAlign = TextAlign.Center
        )
        Spacer(Modifier.height(10.dp))
        Text(
            text = "My Bingwa needs both to be useful. You can change them any time in Settings.",
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(horizontal = 12.dp)
        )
        Spacer(Modifier.height(28.dp))

        PermissionRow(
            icon = Icons.Rounded.Notifications,
            title = "Offer alerts",
            // Says what arrives and how often, so "Allow" is an informed tap
            // (CLAUDE.md §9 — explain before asking).
            body = "A short note when there is a bundle worth your money. Twice a day at most, never at night.",
            granted = notificationsGranted,
            onAllow = onRequestNotifications
        )

        if (BuildConfig.SMS_DETECTION_AVAILABLE) {
            Spacer(Modifier.height(16.dp))
            PermissionRow(
                icon = Icons.Rounded.Sms,
                title = "Read Safaricom messages",
                // Be precise about the scope: Safaricom's own confirmations, read on the
                // device, never uploaded. Nothing else is touched.
                body = "So the app can see Safaricom's own confirmation that your bundle arrived. Read on your phone only — nothing is ever sent anywhere.",
                granted = smsGranted,
                onAllow = onRequestSms
            )
        }

        Spacer(Modifier.height(24.dp))
    }
}

@Composable
private fun PermissionRow(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    title: String,
    body: String,
    granted: Boolean,
    onAllow: () -> Unit
) {
    GlassSurface(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clickable(enabled = !granted, onClick = onAllow)
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = if (granted) Icons.Rounded.CheckCircle else icon,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(28.dp)
            )
            Spacer(Modifier.width(14.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Spacer(Modifier.height(4.dp))
                Text(
                    text = if (granted) "Allowed" else body,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

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
            .verticalScroll(rememberScrollState()),
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
private fun StepProgress(step: Int) {
    val target = step.toFloat() / TOTAL_STEPS
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
private fun PrimaryCtaButton(text: String, enabled: Boolean, onClick: () -> Unit) {
    Box(
        modifier = Modifier
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
