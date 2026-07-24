package com.example.feature.onboarding

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
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AutoAwesome
import androidx.compose.material.icons.outlined.Call
import androidx.compose.material.icons.outlined.CardGiftcard
import androidx.compose.material.icons.outlined.ChatBubbleOutline
import androidx.compose.material.icons.outlined.Payment
import androidx.compose.material.icons.outlined.SignalCellularAlt
import androidx.compose.material.icons.outlined.WifiOff
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.R
import com.example.core.ui.LabelledPhoneField
import com.example.core.ui.LabelledTextField
import com.example.core.ui.PrimaryButton
import com.example.ui.theme.FieldButtonShape
import com.example.ui.theme.TypographyDisplay
import com.example.ui.theme.TypographyPageHeading
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlin.math.cos
import kotlin.math.sin
import kotlin.random.Random

@Composable
fun OnboardingScreen(
    onCompleteOnboarding: (String, String) -> Unit
) {
    var step by remember { mutableIntStateOf(1) }
    var nameInput by remember { mutableStateOf("Bonke") }
    var phoneInput by remember { mutableStateOf("0727 921 038") }
    var nameError by remember { mutableStateOf<String?>(null) }
    var phoneError by remember { mutableStateOf<String?>(null) }

    // Progress bar animated width ratio
    val animatedProgress by animateFloatAsState(
        targetValue = when (step) {
            1 -> 0.33f
            2 -> 0.66f
            else -> 1f
        },
        animationSpec = tween(durationMillis = 500, easing = FastOutSlowInEasing),
        label = "progress_bar_anim"
    )

    Scaffold(
        containerColor = MaterialTheme.colorScheme.background
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(horizontal = 24.dp, vertical = 16.dp)
                    .verticalScroll(rememberScrollState()),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Top Navigation Bar
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Smoothly filling progress track indicator
                    Box(
                        modifier = Modifier
                            .width(120.dp)
                            .height(6.dp)
                            .clip(CircleShape)
                            .background(MaterialTheme.colorScheme.outline.copy(alpha = 0.2f))
                    ) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth(animatedProgress)
                                .height(6.dp)
                                .clip(CircleShape)
                                .background(MaterialTheme.colorScheme.primary)
                        )
                    }

                    if (step < 3) {
                        TextButton(
                            onClick = { step = 3 },
                            modifier = Modifier.testTag("skip_onboarding_button")
                        ) {
                            Text(
                                text = "Skip",
                                style = MaterialTheme.typography.bodyMedium,
                                fontWeight = FontWeight.SemiBold,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    } else {
                        Spacer(modifier = Modifier.width(1.dp))
                    }
                }

                Spacer(modifier = Modifier.height(20.dp))

                // Step Content Animated Transition
                AnimatedContent(
                    targetState = step,
                    transitionSpec = {
                        fadeIn(animationSpec = tween(300)) togetherWith fadeOut(animationSpec = tween(200))
                    },
                    label = "onboarding_step_content"
                ) { currentStep ->
                    when (currentStep) {
                        1 -> StepOneWelcome(onNext = { step = 2 })
                        2 -> StepTwoFeatures(onNext = { step = 3 })
                        3 -> StepThreePersonalise(
                            name = nameInput,
                            onNameChange = {
                                nameInput = it
                                nameError = null
                            },
                            nameError = nameError,
                            phone = phoneInput,
                            onPhoneChange = {
                                phoneInput = it
                                phoneError = null
                            },
                            phoneError = phoneError,
                            onSubmit = { cleanName, cleanPhone ->
                                onCompleteOnboarding(cleanName, cleanPhone)
                            }
                        )
                    }
                }
            }
        }
    }
}

// -----------------------------------------------------------------------------
// SCREEN 1 — MAIN PROMISE
// -----------------------------------------------------------------------------
@Composable
private fun StepOneWelcome(onNext: () -> Unit) {
    val logoScale = remember { Animatable(0.5f) }
    val logoOffsetY = remember { Animatable(60f) }
    val logoAlpha = remember { Animatable(0f) }

    val titleOffsetY = remember { Animatable(40f) }
    val titleAlpha = remember { Animatable(0f) }

    val subOffsetY = remember { Animatable(40f) }
    val subAlpha = remember { Animatable(0f) }

    val btnOffsetY = remember { Animatable(60f) }
    val btnAlpha = remember { Animatable(0f) }

    LaunchedEffect(Unit) {
        // Logo animation: scales in and slides up gently
        launch {
            logoAlpha.animateTo(1f, tween(500))
        }
        launch {
            logoScale.animateTo(1.0f, spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow))
        }
        launch {
            logoOffsetY.animateTo(0f, spring(dampingRatio = Spring.DampingRatioLowBouncy, stiffness = Spring.StiffnessLow))
        }

        delay(120)
        // Title animation
        launch { titleAlpha.animateTo(1f, tween(400)) }
        launch { titleOffsetY.animateTo(0f, spring(stiffness = Spring.StiffnessMediumLow)) }

        delay(150)
        // Subtitle animation
        launch { subAlpha.animateTo(1f, tween(400)) }
        launch { subOffsetY.animateTo(0f, spring(stiffness = Spring.StiffnessMediumLow)) }

        delay(180)
        // Button animation
        launch { btnAlpha.animateTo(1f, tween(400)) }
        launch { btnOffsetY.animateTo(0f, spring(stiffness = Spring.StiffnessLow)) }
    }

    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(modifier = Modifier.height(24.dp))

        // Large Central Logo
        Box(
            modifier = Modifier
                .graphicsLayer {
                    scaleX = logoScale.value
                    scaleY = logoScale.value
                    translationY = logoOffsetY.value
                    alpha = logoAlpha.value
                },
            contentAlignment = Alignment.Center
        ) {
            Icon(
                painter = painterResource(id = R.drawable.ic_mybingwa_symbol),
                contentDescription = "My Bingwa Logo",
                modifier = Modifier.size(140.dp),
                tint = Color.Unspecified
            )
        }

        Spacer(modifier = Modifier.height(36.dp))

        // Title
        Text(
            text = "Welcome to My Bingwa",
            style = TypographyDisplay.copy(fontSize = 30.sp),
            color = MaterialTheme.colorScheme.onBackground,
            textAlign = TextAlign.Center,
            fontWeight = FontWeight.ExtraBold,
            modifier = Modifier.graphicsLayer {
                translationY = titleOffsetY.value
                alpha = titleAlpha.value
            }
        )

        Spacer(modifier = Modifier.height(14.dp))

        // Supporting Text
        Text(
            text = "Buy data, SMS and minutes even with unpaid Okoa Jahazi.",
            style = MaterialTheme.typography.bodyLarge.copy(fontSize = 17.sp, lineHeight = 24.sp),
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .padding(horizontal = 12.dp)
                .graphicsLayer {
                    translationY = subOffsetY.value
                    alpha = subAlpha.value
                }
        )

        Spacer(modifier = Modifier.height(56.dp))

        // Button
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .graphicsLayer {
                    translationY = btnOffsetY.value
                    alpha = btnAlpha.value
                }
        ) {
            PrimaryButton(
                text = "Get started",
                onClick = onNext,
                testTag = "onboarding_get_started_button"
            )
        }
    }
}

// -----------------------------------------------------------------------------
// SCREEN 2 — WHAT THE USER GAINS
// -----------------------------------------------------------------------------
@Composable
private fun StepTwoFeatures(onNext: () -> Unit) {
    val titleOffsetY = remember { Animatable(-30f) }
    val titleAlpha = remember { Animatable(0f) }

    // Category icon bounces (4 items)
    val cat1Anim = remember { Animatable(-70f) }
    val cat2Anim = remember { Animatable(-70f) }
    val cat3Anim = remember { Animatable(-70f) }
    val cat4Anim = remember { Animatable(-70f) }

    val cat1Alpha = remember { Animatable(0f) }
    val cat2Alpha = remember { Animatable(0f) }
    val cat3Alpha = remember { Animatable(0f) }
    val cat4Alpha = remember { Animatable(0f) }

    // Staggered benefit cards
    val card1OffsetY = remember { Animatable(50f) }
    val card1Alpha = remember { Animatable(0f) }

    val card2OffsetY = remember { Animatable(50f) }
    val card2Alpha = remember { Animatable(0f) }

    val card3OffsetY = remember { Animatable(50f) }
    val card3Alpha = remember { Animatable(0f) }

    val btnOffsetY = remember { Animatable(50f) }
    val btnAlpha = remember { Animatable(0f) }

    // Infinite rotation transition for the glowing circle around category icons
    val infiniteTransition = rememberInfiniteTransition(label = "category_glow_rotation")
    val glowAngle by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(3000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "glow_angle"
    )

    LaunchedEffect(Unit) {
        // Title animation
        launch { titleAlpha.animateTo(1f, tween(300)) }
        launch { titleOffsetY.animateTo(0f, spring(stiffness = Spring.StiffnessMediumLow)) }

        // Category icons drop & bounce one after another
        delay(100)
        launch { cat1Alpha.animateTo(1f, tween(200)) }
        launch { cat1Anim.animateTo(0f, spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow)) }

        delay(100)
        launch { cat2Alpha.animateTo(1f, tween(200)) }
        launch { cat2Anim.animateTo(0f, spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow)) }

        delay(100)
        launch { cat3Alpha.animateTo(1f, tween(200)) }
        launch { cat3Anim.animateTo(0f, spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow)) }

        delay(100)
        launch { cat4Alpha.animateTo(1f, tween(200)) }
        launch { cat4Anim.animateTo(0f, spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow)) }

        // Benefit cards rise individually with stagger
        delay(150)
        launch { card1Alpha.animateTo(1f, tween(300)) }
        launch { card1OffsetY.animateTo(0f, spring(stiffness = Spring.StiffnessMediumLow)) }

        delay(120)
        launch { card2Alpha.animateTo(1f, tween(300)) }
        launch { card2OffsetY.animateTo(0f, spring(stiffness = Spring.StiffnessMediumLow)) }

        delay(120)
        launch { card3Alpha.animateTo(1f, tween(300)) }
        launch { card3OffsetY.animateTo(0f, spring(stiffness = Spring.StiffnessMediumLow)) }

        delay(150)
        launch { btnAlpha.animateTo(1f, tween(300)) }
        launch { btnOffsetY.animateTo(0f, spring(stiffness = Spring.StiffnessLow)) }
    }

    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Title
        Text(
            text = "Buy what you need, anytime, anywhere.",
            style = TypographyPageHeading.copy(fontSize = 24.sp, lineHeight = 30.sp),
            color = MaterialTheme.colorScheme.onBackground,
            textAlign = TextAlign.Center,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.graphicsLayer {
                translationY = titleOffsetY.value
                alpha = titleAlpha.value
            }
        )

        Spacer(modifier = Modifier.height(20.dp))

        // Horizontal Row of Circular Category Labels & Icons
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.CenterVertically
        ) {
            AnimatedCategoryIcon(
                label = "Data",
                icon = Icons.Outlined.SignalCellularAlt,
                color = MaterialTheme.colorScheme.secondary,
                offsetY = cat1Anim.value,
                alpha = cat1Alpha.value,
                glowAngle = glowAngle
            )
            AnimatedCategoryIcon(
                label = "SMS",
                icon = Icons.Outlined.ChatBubbleOutline,
                color = MaterialTheme.colorScheme.tertiary,
                offsetY = cat2Anim.value,
                alpha = cat2Alpha.value,
                glowAngle = glowAngle
            )
            AnimatedCategoryIcon(
                label = "Minutes",
                icon = Icons.Outlined.Call,
                color = MaterialTheme.colorScheme.primary,
                offsetY = cat3Anim.value,
                alpha = cat3Alpha.value,
                glowAngle = glowAngle
            )
            AnimatedCategoryIcon(
                label = "Special",
                icon = Icons.Outlined.AutoAwesome,
                color = MaterialTheme.colorScheme.error,
                offsetY = cat4Anim.value,
                alpha = cat4Alpha.value,
                glowAngle = glowAngle
            )
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Three Short Glass-Style Benefit Cards
        Column(
            modifier = Modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            GlassBenefitCard(
                icon = Icons.Outlined.Payment,
                title = "Pay easily with M-Pesa",
                description = "Approve the payment directly from your phone.",
                offsetY = card1OffsetY.value,
                alpha = card1Alpha.value
            )

            GlassBenefitCard(
                icon = Icons.Outlined.CardGiftcard,
                title = "Gift others",
                description = "You can buy for another number with ease.",
                offsetY = card2OffsetY.value,
                alpha = card2Alpha.value
            )

            GlassBenefitCard(
                icon = Icons.Outlined.WifiOff,
                title = "Buy even when offline",
                description = "Use Till number and Paybill to buy anytime when offline.",
                offsetY = card3OffsetY.value,
                alpha = card3Alpha.value
            )
        }

        Spacer(modifier = Modifier.height(28.dp))

        // Button
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .graphicsLayer {
                    translationY = btnOffsetY.value
                    alpha = btnAlpha.value
                }
        ) {
            PrimaryButton(
                text = "I love it! Continue.",
                onClick = onNext,
                testTag = "onboarding_continue_button"
            )
        }
    }
}

@Composable
private fun AnimatedCategoryIcon(
    label: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    color: Color,
    offsetY: Float,
    alpha: Float,
    glowAngle: Float
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.graphicsLayer {
            translationY = offsetY
            this.alpha = alpha
        }
    ) {
        Box(
            modifier = Modifier
                .size(58.dp)
                .clip(CircleShape)
                .background(color.copy(alpha = 0.12f))
                .border(
                    width = 2.dp,
                    brush = Brush.sweepGradient(
                        colors = listOf(
                            color,
                            color.copy(alpha = 0.2f),
                            color,
                            color.copy(alpha = 0.2f)
                        )
                    ),
                    shape = CircleShape
                ),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = color,
                modifier = Modifier.size(26.dp)
            )
        }
        Spacer(modifier = Modifier.height(6.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelMedium,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )
    }
}

@Composable
private fun GlassBenefitCard(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    title: String,
    description: String,
    offsetY: Float,
    alpha: Float
) {
    Surface(
        shape = RoundedCornerShape(16.dp),
        color = MaterialTheme.colorScheme.surface.copy(alpha = 0.9f),
        modifier = Modifier
            .fillMaxWidth()
            .graphicsLayer {
                translationY = offsetY
                this.alpha = alpha
            }
            .border(
                width = 1.dp,
                color = MaterialTheme.colorScheme.outline.copy(alpha = 0.25f),
                shape = RoundedCornerShape(16.dp)
            )
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(MaterialTheme.colorScheme.primaryContainer),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = title,
                    tint = MaterialTheme.colorScheme.onPrimaryContainer,
                    modifier = Modifier.size(22.dp)
                )
            }

            Spacer(modifier = Modifier.width(14.dp))

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Spacer(modifier = Modifier.height(2.dp))
                Text(
                    text = description,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    lineHeight = 16.sp
                )
            }
        }
    }
}

// -----------------------------------------------------------------------------
// SCREEN 3 — PERSONAL SETUP
// -----------------------------------------------------------------------------
@Composable
private fun StepThreePersonalise(
    name: String,
    onNameChange: (String) -> Unit,
    nameError: String?,
    phone: String,
    onPhoneChange: (String) -> Unit,
    phoneError: String?,
    onSubmit: (String, String) -> Unit
) {
    var nameVal by remember(name) { mutableStateOf(name) }
    var phoneVal by remember(phone) { mutableStateOf(phone) }
    var localNameError by remember(nameError) { mutableStateOf(nameError) }
    var localPhoneError by remember(phoneError) { mutableStateOf(phoneError) }

    val coroutineScope = rememberCoroutineScope()
    var isSubmitting by remember { mutableStateOf(false) }
    var showConfetti by remember { mutableStateOf(false) }

    // Entrance Animations
    val headerAlpha = remember { Animatable(0f) }

    // Name field slides in from Left (-120f)
    val nameOffsetX = remember { Animatable(-120f) }
    val nameAlpha = remember { Animatable(0f) }

    // Phone field slides in from Right (+120f)
    val phoneOffsetX = remember { Animatable(120f) }
    val phoneAlpha = remember { Animatable(0f) }

    val btnOffsetY = remember { Animatable(60f) }
    val btnAlpha = remember { Animatable(0f) }

    LaunchedEffect(Unit) {
        // Header fades in first
        launch { headerAlpha.animateTo(1f, tween(300)) }

        delay(120)
        // Name field slides in from Left
        launch { nameAlpha.animateTo(1f, tween(350)) }
        launch { nameOffsetX.animateTo(0f, spring(dampingRatio = Spring.DampingRatioLowBouncy, stiffness = Spring.StiffnessMediumLow)) }

        delay(150)
        // Phone field slides in from Right
        launch { phoneAlpha.animateTo(1f, tween(350)) }
        launch { phoneOffsetX.animateTo(0f, spring(dampingRatio = Spring.DampingRatioLowBouncy, stiffness = Spring.StiffnessMediumLow)) }

        delay(180)
        // Button rises into position
        launch { btnAlpha.animateTo(1f, tween(350)) }
        launch { btnOffsetY.animateTo(0f, spring(stiffness = Spring.StiffnessLow)) }
    }

    Box(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier.fillMaxWidth(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Header: Title & Supporting Text
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.graphicsLayer { alpha = headerAlpha.value }
            ) {
                Text(
                    text = "Make My Bingwa yours.",
                    style = TypographyPageHeading.copy(fontSize = 26.sp),
                    color = MaterialTheme.colorScheme.onBackground,
                    textAlign = TextAlign.Center,
                    fontWeight = FontWeight.Bold
                )

                Spacer(modifier = Modifier.height(8.dp))

                Text(
                    text = "Just your name and phone number. No account needed.",
                    style = MaterialTheme.typography.bodyLarge,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center
                )
            }

            Spacer(modifier = Modifier.height(28.dp))

            // Name Field (Slides in from Left)
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .graphicsLayer {
                        translationX = nameOffsetX.value
                        alpha = nameAlpha.value
                    }
            ) {
                LabelledTextField(
                    label = "Your name",
                    value = nameVal,
                    onValueChange = {
                        nameVal = it
                        onNameChange(it)
                        localNameError = null
                    },
                    placeholder = "Enter your name",
                    errorMessage = localNameError,
                    testTag = "onboarding_name_input"
                )
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Safaricom Number Field (Slides in from Right)
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .graphicsLayer {
                        translationX = phoneOffsetX.value
                        alpha = phoneAlpha.value
                    }
            ) {
                LabelledPhoneField(
                    label = "Safaricom number",
                    value = phoneVal,
                    onValueChange = {
                        phoneVal = it
                        onPhoneChange(it)
                        localPhoneError = null
                    },
                    placeholder = "07XX XXX XXX",
                    errorMessage = localPhoneError,
                    testTag = "onboarding_phone_input"
                )
            }

            Spacer(modifier = Modifier.height(36.dp))

            // Button (Rises into position)
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .graphicsLayer {
                        translationY = btnOffsetY.value
                        alpha = btnAlpha.value
                    }
            ) {
                PrimaryButton(
                    text = "Start using My Bingwa",
                    onClick = {
                        if (isSubmitting) return@PrimaryButton
                        val cleanName = nameVal.trim()
                        val cleanPhone = phoneVal.trim()
                        var valid = true

                        if (cleanName.isBlank()) {
                            localNameError = "Please enter your name"
                            valid = false
                        }
                        if (cleanPhone.isBlank() || cleanPhone.length < 9) {
                            localPhoneError = "Enter a valid Safaricom number"
                            valid = false
                        }

                        if (valid) {
                            isSubmitting = true
                            showConfetti = true
                            coroutineScope.launch {
                                delay(1100) // Brief success confetti animation
                                onSubmit(cleanName, cleanPhone)
                            }
                        }
                    },
                    testTag = "start_using_bingwa_button"
                )
            }
        }

        // Celebratory Confetti Burst Effect on Successful Submit
        if (showConfetti) {
            ConfettiBurstOverlay(modifier = Modifier.matchParentSize())
        }
    }
}

// -----------------------------------------------------------------------------
// CONFETTI BURST ANIMATION
// -----------------------------------------------------------------------------
private data class ConfettiParticle(
    val x: Float,
    val y: Float,
    val vx: Float,
    val vy: Float,
    val size: Float,
    val color: Color,
    val rotation: Float
)

@Composable
private fun ConfettiBurstOverlay(modifier: Modifier = Modifier) {
    val progress = remember { Animatable(0f) }

    val particles = remember {
        val colors = listOf(
            Color(0xFF00C853), // Safaricom Green
            Color(0xFFFFD600), // Bright Gold
            Color(0xFF2979FF), // Vivid Blue
            Color(0xFFFF1744), // Crimson Red
            Color(0xFFAA00FF)  // Purple
        )
        List(60) {
            val angle = Random.nextFloat() * 2f * Math.PI.toFloat()
            val speed = Random.nextFloat() * 350f + 150f
            ConfettiParticle(
                x = 0.5f,
                y = 0.6f,
                vx = cos(angle) * speed,
                vy = sin(angle) * speed - 200f, // initial upward burst
                size = Random.nextFloat() * 10f + 8f,
                color = colors.random(),
                rotation = Random.nextFloat() * 360f
            )
        }
    }

    LaunchedEffect(Unit) {
        progress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 1100, easing = LinearEasing)
        )
    }

    Canvas(modifier = modifier) {
        val width = size.width
        val height = size.height
        val t = progress.value

        particles.forEach { p ->
            val px = p.x * width + p.vx * t
            val py = p.y * height + p.vy * t + 400f * t * t // Gravity curve
            val currentAlpha = (1f - t * 0.9f).coerceIn(0f, 1f)

            if (px in 0f..width && py in 0f..height) {
                rotate(p.rotation + t * 360f, pivot = Offset(px, py)) {
                    drawRect(
                        color = p.color.copy(alpha = currentAlpha),
                        topLeft = Offset(px - p.size / 2, py - p.size / 2),
                        size = androidx.compose.ui.geometry.Size(p.size, p.size * 0.6f)
                    )
                }
            }
        }
    }
}
