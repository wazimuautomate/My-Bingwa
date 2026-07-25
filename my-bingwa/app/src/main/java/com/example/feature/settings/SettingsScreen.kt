package com.example.feature.settings

import android.content.Intent
import android.net.Uri
import android.provider.Settings
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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.DarkMode
import androidx.compose.material.icons.outlined.DeleteForever
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.LightMode
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.SettingsSuggest
import androidx.compose.material.icons.outlined.Sms
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.BuildConfig
import com.example.core.model.AppThemeSetting
import com.example.core.model.UserProfile
import com.example.core.update.UpdateChecker
import com.example.core.update.UpdateResult
import com.example.core.ui.LabelledPhoneField
import com.example.core.ui.LabelledTextField
import com.example.core.ui.PrimaryButton
import com.example.ui.theme.BottomSheetTopShape
import com.example.ui.theme.FieldButtonShape
import com.example.ui.theme.TypographyPageHeading
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    profile: UserProfile,
    currentTheme: AppThemeSetting,
    onUpdateProfile: (String, String) -> Unit,
    onThemeSelect: (AppThemeSetting) -> Unit,
    onClearLocalData: () -> Unit,
    // Triggered after the in-app explanation when the customer opts into push
    // notifications. MainActivity owns the actual POST_NOTIFICATIONS runtime
    // request (Android 13+) or a no-op on older versions. Defaulted so existing
    // call sites / tests keep compiling.
    onEnablePushNotifications: () -> Unit = {},
    // Triggered after the rationale when the customer opts into Safaricom SMS
    // bundle/balance detection. MainActivity owns the RECEIVE_SMS request.
    onEnableSmsDetection: () -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var showEditProfileSheet by remember { mutableStateOf(false) }
    var showClearDataDialog by remember { mutableStateOf(false) }
    var checkingUpdates by remember { mutableStateOf(false) }
    var updateMessage by remember { mutableStateOf<String?>(null) }
    // Set when a newer direct-channel build is published, so a "Download update"
    // action can open the published APK URL. Play users update via the store.
    var updateApkUrl by remember { mutableStateOf<String?>(null) }

    // Notification / SMS preference state (declared here so the rationale dialogs
    // near the bottom of this composable can also read and update them).
    var notificationsEnabled by remember(profile.notificationsEnabled) { mutableStateOf(profile.notificationsEnabled) }
    var showPushRationale by remember { mutableStateOf(false) }
    // Reflects the real OS RECEIVE_SMS grant (MainActivity writes it into the
    // persisted profile), so the toggle no longer resets to off on every visit.
    var smsAlertsEnabled by remember(profile.smsAlertsEnabled) { mutableStateOf(profile.smsAlertsEnabled) }
    var showSmsRationale by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp, vertical = 12.dp)
    ) {
        Text(
            text = "Settings",
            style = TypographyPageHeading.copy(fontSize = 24.sp),
            color = MaterialTheme.colorScheme.onBackground,
            fontWeight = FontWeight.Bold
        )

        Spacer(modifier = Modifier.height(20.dp))

        // Profile Section Card
        SettingsGroupTitle("Profile")
        Surface(
            shape = FieldButtonShape,
            color = MaterialTheme.colorScheme.surface,
            modifier = Modifier.fillMaxWidth()
        ) {
            Row(
                modifier = Modifier.padding(16.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(54.dp)
                        .clip(CircleShape)
                        .background(MaterialTheme.colorScheme.primaryContainer),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Person,
                        contentDescription = "User Avatar",
                        tint = MaterialTheme.colorScheme.onPrimaryContainer,
                        modifier = Modifier.size(32.dp)
                    )
                }

                Spacer(modifier = Modifier.width(16.dp))

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = profile.name.ifEmpty { "Customer" },
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = profile.primaryNumber.ifEmpty { "No phone set" },
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }

                IconButton(
                    onClick = { showEditProfileSheet = true },
                    modifier = Modifier.testTag("edit_profile_button")
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Edit,
                        contentDescription = "Edit Profile",
                        tint = MaterialTheme.colorScheme.primary
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Appearance Section (Icon-based theme selector)
        SettingsGroupTitle("Appearance")
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            ThemeIconCard(
                label = "System",
                icon = Icons.Outlined.SettingsSuggest,
                isSelected = currentTheme == AppThemeSetting.SYSTEM,
                onClick = { onThemeSelect(AppThemeSetting.SYSTEM) },
                modifier = Modifier.weight(1f)
            )
            ThemeIconCard(
                label = "Light",
                icon = Icons.Outlined.LightMode,
                isSelected = currentTheme == AppThemeSetting.LIGHT,
                onClick = { onThemeSelect(AppThemeSetting.LIGHT) },
                modifier = Modifier.weight(1f)
            )
            ThemeIconCard(
                label = "Dark",
                icon = Icons.Outlined.DarkMode,
                isSelected = currentTheme == AppThemeSetting.DARK,
                onClick = { onThemeSelect(AppThemeSetting.DARK) },
                modifier = Modifier.weight(1f)
            )
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Notification Permissions Section
        SettingsGroupTitle("Push Notifications")
        Surface(
            shape = FieldButtonShape,
            color = MaterialTheme.colorScheme.surface,
            modifier = Modifier.fillMaxWidth()
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.weight(1f)
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Notifications,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(24.dp)
                    )
                    Spacer(modifier = Modifier.width(12.dp))
                    Column {
                        Text(
                            text = "Push Notifications",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            text = if (notificationsEnabled) "Notifications enabled" else "Notifications disabled",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }

                Switch(
                    checked = notificationsEnabled,
                    onCheckedChange = { desired ->
                        // Explain before requesting the OS permission (CLAUDE.md §9).
                        if (desired) showPushRationale = true else notificationsEnabled = false
                    },
                    modifier = Modifier.testTag("push_notifications_switch"),
                    colors = SwitchDefaults.colors(
                        checkedThumbColor = MaterialTheme.colorScheme.onPrimary,
                        checkedTrackColor = MaterialTheme.colorScheme.primary
                    )
                )
            }
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Bundle & balance alerts (optional Safaricom SMS reading) Section
        SettingsGroupTitle("Bundle & balance alerts")
        Surface(
            shape = FieldButtonShape,
            color = MaterialTheme.colorScheme.surface,
            modifier = Modifier.fillMaxWidth()
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.weight(1f)
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Sms,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(24.dp)
                    )
                    Spacer(modifier = Modifier.width(12.dp))
                    Column {
                        Text(
                            text = "Reads Safaricom SMS",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            text = if (smsAlertsEnabled) {
                                "Confirms delivery and suggests top-ups"
                            } else {
                                "Off — no SMS is read"
                            },
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }

                Switch(
                    checked = smsAlertsEnabled,
                    onCheckedChange = { desired ->
                        if (desired) showSmsRationale = true else smsAlertsEnabled = false
                    },
                    modifier = Modifier.testTag("sms_alerts_switch"),
                    colors = SwitchDefaults.colors(
                        checkedThumbColor = MaterialTheme.colorScheme.onPrimary,
                        checkedTrackColor = MaterialTheme.colorScheme.primary
                    )
                )
            }
        }

        Spacer(modifier = Modifier.height(24.dp))

        // About Section
        SettingsGroupTitle("About App")
        Surface(
            shape = FieldButtonShape,
            color = MaterialTheme.colorScheme.surface,
            modifier = Modifier.fillMaxWidth()
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text("App Version", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(BuildConfig.VERSION_NAME, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Bold)
                }

                Spacer(modifier = Modifier.height(12.dp))

                Text(
                    text = "My Bingwa allows you buy safaricom data, sms and minutes even if you have unpaid Okoa jahazi even if you are offline",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )

                Spacer(modifier = Modifier.height(16.dp))

                Button(
                    onClick = {
                        if (checkingUpdates) return@Button
                        checkingUpdates = true
                        updateMessage = null
                        updateApkUrl = null
                        scope.launch {
                            updateMessage = when (val result = UpdateChecker.check()) {
                                is UpdateResult.Available -> {
                                    updateApkUrl = result.apkUrl.takeIf { it.isNotBlank() }
                                    val name = result.versionName.takeIf { it.isNotBlank() }
                                    if (name != null) "Version $name is available." else "A new version is available."
                                }
                                UpdateResult.UpToDate ->
                                    "You are on the latest version of My Bingwa."
                                is UpdateResult.Error -> result.message
                            }
                            checkingUpdates = false
                        }
                    },
                    enabled = !checkingUpdates,
                    shape = FieldButtonShape,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Icon(imageVector = Icons.Outlined.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(if (checkingUpdates) "Checking…" else "Check for updates", fontWeight = FontWeight.Bold)
                }

                if (updateMessage != null) {
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = updateMessage!!,
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.primary,
                        fontWeight = FontWeight.SemiBold
                    )
                }

                // Shown only for the direct (GitHub) channel when a newer build is
                // published; opens the signed APK so the user installs it themselves.
                updateApkUrl?.let { apkUrl ->
                    Spacer(modifier = Modifier.height(12.dp))
                    OutlinedButton(
                        onClick = {
                            runCatching {
                                context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(apkUrl)))
                            }
                        },
                        shape = FieldButtonShape,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text("Download update", fontWeight = FontWeight.Bold)
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Local Data Section
        SettingsGroupTitle("Local Data")
        Surface(
            shape = FieldButtonShape,
            color = MaterialTheme.colorScheme.errorContainer.copy(alpha = 0.3f),
            modifier = Modifier.fillMaxWidth()
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = "Your profile, favourites and Activity are saved on this phone. Clearing them cannot be undone.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurface,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
                Spacer(modifier = Modifier.height(12.dp))
                Button(
                    onClick = { showClearDataDialog = true },
                    shape = FieldButtonShape,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = MaterialTheme.colorScheme.error,
                        contentColor = MaterialTheme.colorScheme.onError
                    ),
                    modifier = Modifier.testTag("clear_local_data_button")
                ) {
                    Icon(imageVector = Icons.Outlined.DeleteForever, contentDescription = null)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Clear local data", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold)
                }
            }
        }

        Spacer(modifier = Modifier.height(36.dp))
    }

    // Edit Profile Sheet
    if (showEditProfileSheet) {
        ModalBottomSheet(
            onDismissRequest = { showEditProfileSheet = false },
            sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
            shape = BottomSheetTopShape,
            containerColor = MaterialTheme.colorScheme.surface
        ) {
            var nameInput by remember { mutableStateOf(profile.name) }
            var phoneInput by remember { mutableStateOf(profile.primaryNumber) }

            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(24.dp)
            ) {
                Text(
                    text = "Edit Profile",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface
                )

                Spacer(modifier = Modifier.height(20.dp))

                LabelledTextField(
                    label = "Your name",
                    value = nameInput,
                    onValueChange = { nameInput = it },
                    testTag = "edit_profile_name"
                )

                Spacer(modifier = Modifier.height(16.dp))

                LabelledPhoneField(
                    label = "Primary Safaricom number",
                    value = phoneInput,
                    onValueChange = { phoneInput = it },
                    testTag = "edit_profile_phone"
                )

                Spacer(modifier = Modifier.height(28.dp))

                PrimaryButton(
                    text = "Save changes",
                    onClick = {
                        onUpdateProfile(nameInput.trim(), phoneInput.trim())
                        showEditProfileSheet = false
                    },
                    testTag = "save_profile_button"
                )
            }
        }
    }

    // Clear Data Confirmation Dialog
    if (showClearDataDialog) {
        AlertDialog(
            onDismissRequest = { showClearDataDialog = false },
            title = { Text("Clear all local data?", fontWeight = FontWeight.Bold) },
            text = { Text("This will reset your profile, purchase activity, favourites and settings on this device. This action cannot be undone.") },
            confirmButton = {
                TextButton(
                    onClick = {
                        onClearLocalData()
                        showClearDataDialog = false
                    }
                ) {
                    Text("Clear local data", color = MaterialTheme.colorScheme.error, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showClearDataDialog = false }) {
                    Text("Keep my data")
                }
            }
        )
    }

    // Push notifications rationale (shown before the OS permission prompt, §9).
    if (showPushRationale) {
        AlertDialog(
            onDismissRequest = { showPushRationale = false },
            title = { Text("Turn on notifications?", fontWeight = FontWeight.Bold) },
            text = {
                Text(
                    "My Bingwa will notify you about your payment status and the offers you " +
                        "choose to follow. You can turn this off any time in system settings."
                )
            },
            confirmButton = {
                RationaleButtons(
                    allowTestTag = "push_rationale_allow",
                    onAllow = {
                        showPushRationale = false
                        notificationsEnabled = true
                        onEnablePushNotifications()
                    },
                    onNotNow = { showPushRationale = false },
                    onOpenSettings = {
                        showPushRationale = false
                        openAppSettings(context)
                    }
                )
            }
        )
    }

    // Safaricom SMS detection rationale (shown before the RECEIVE_SMS prompt).
    if (showSmsRationale) {
        AlertDialog(
            onDismissRequest = { showSmsRationale = false },
            title = { Text("Read Safaricom bundle SMS?", fontWeight = FontWeight.Bold) },
            text = {
                Text(
                    "My Bingwa reads Safaricom messages on this phone only, " +
                        "to confirm your bundle is delivered."
                )
            },
            confirmButton = {
                RationaleButtons(
                    allowTestTag = "sms_rationale_allow",
                    onAllow = {
                        showSmsRationale = false
                        smsAlertsEnabled = true
                        onEnableSmsDetection()
                    },
                    onNotNow = { showSmsRationale = false },
                    onOpenSettings = {
                        showSmsRationale = false
                        openAppSettings(context)
                    }
                )
            }
        )
    }
}

/**
 * A clean, full-width vertical button stack for the permission rationale dialogs:
 * primary **Allow**, then **Not now**, then **Open app settings**. Stacking them
 * avoids the cramped cross-aligned look of a confirm/dismiss button row (owner
 * feedback on the SMS / notification confirmation).
 */
@Composable
private fun RationaleButtons(
    allowTestTag: String,
    onAllow: () -> Unit,
    onNotNow: () -> Unit,
    onOpenSettings: () -> Unit
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(4.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        PrimaryButton(
            text = "Allow",
            onClick = onAllow,
            modifier = Modifier.fillMaxWidth(),
            testTag = allowTestTag
        )
        TextButton(
            onClick = onNotNow,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Not now")
        }
        TextButton(
            onClick = onOpenSettings,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text(
                "Open app settings",
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

/** Opens this app's system settings page so the customer can review a denied permission. */
private fun openAppSettings(context: android.content.Context) {
    val intent = Intent(
        Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
        android.net.Uri.fromParts("package", context.packageName, null)
    ).apply { addFlags(Intent.FLAG_ACTIVITY_NEW_TASK) }
    try {
        context.startActivity(intent)
    } catch (e: Exception) {
        // No settings activity available; nothing else to do.
    }
}

@Composable
private fun ThemeIconCard(
    label: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        shape = FieldButtonShape,
        color = if (isSelected) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface,
        modifier = modifier
            .clip(FieldButtonShape)
            .border(
                width = 1.5.dp,
                color = if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f),
                shape = FieldButtonShape
            )
            .clickable { onClick() }
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(28.dp)
            )
            Spacer(modifier = Modifier.height(6.dp))
            Text(
                text = label,
                style = MaterialTheme.typography.labelLarge,
                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                color = if (isSelected) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurface
            )
        }
    }
}

@Composable
private fun SettingsGroupTitle(title: String) {
    Text(
        text = title,
        style = MaterialTheme.typography.titleMedium,
        fontWeight = FontWeight.Bold,
        color = MaterialTheme.colorScheme.onBackground,
        modifier = Modifier.padding(bottom = 8.dp)
    )
}
