package com.example.feature.purchase

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
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
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.HourglassEmpty
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.core.model.OfferItem
import com.example.core.model.PaymentStatus
import com.example.core.model.PurchaseRecord
import com.example.core.ui.CopyableValueBlock
import com.example.core.ui.LabelledPhoneField
import com.example.core.ui.PrimaryButton
import com.example.core.ui.SecondaryButton
import com.example.ui.theme.BottomSheetTopShape
import com.example.ui.theme.FieldButtonShape
import com.example.ui.theme.PromotionStatusShape
import com.example.ui.theme.TypographyPageHeading
import com.example.ui.theme.TypographyReviewTotal
import com.example.ui.theme.TypographySheetHeading
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PurchaseBottomSheet(
    offer: OfferItem,
    userPrimaryNumber: String,
    recentRecipients: List<String>,
    isOffline: Boolean,
    onExecuteStkPush: suspend (OfferItem, String, String) -> PurchaseRecord,
    onExecuteOfflinePayment: suspend (OfferItem, String, String, Boolean) -> PurchaseRecord,
    onDismiss: () -> Unit,
    onViewActivity: () -> Unit
) {
    val coroutineScope = rememberCoroutineScope()
    var purchaseStep by remember { mutableIntStateOf(1) } // 1: Recipient, 2: Review, 3: Processing STK, 4: Result, 5: Offline Steps

    var isForSelf by remember { mutableStateOf(true) }
    var recipientNumber by remember { mutableStateOf(userPrimaryNumber) }
    var payerNumber by remember { mutableStateOf(userPrimaryNumber) }

    var lastRecord by remember { mutableStateOf<PurchaseRecord?>(null) }
    var isLoading by remember { mutableStateOf(false) }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        shape = BottomSheetTopShape,
        containerColor = MaterialTheme.colorScheme.surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 24.dp, vertical = 16.dp)
                .verticalScroll(rememberScrollState())
        ) {
            AnimatedContent(
                targetState = purchaseStep,
                transitionSpec = { fadeIn() togetherWith fadeOut() },
                label = "purchase_flow_step"
            ) { step ->
                when (step) {
                    1 -> RecipientSelectionStep(
                        offer = offer,
                        isForSelf = isForSelf,
                        recipientNumber = recipientNumber,
                        payerNumber = payerNumber,
                        userPrimaryNumber = userPrimaryNumber,
                        recentRecipients = recentRecipients,
                        onOptionSelect = { selfSelected ->
                            isForSelf = selfSelected
                            if (selfSelected) {
                                recipientNumber = userPrimaryNumber
                                payerNumber = userPrimaryNumber
                            } else {
                                if (recipientNumber == userPrimaryNumber) recipientNumber = ""
                                payerNumber = userPrimaryNumber
                            }
                        },
                        onRecipientChange = { recipientNumber = it },
                        onPayerChange = { payerNumber = it },
                        onNext = { purchaseStep = 2 }
                    )

                    2 -> ReviewPurchaseStep(
                        offer = offer,
                        recipientNumber = recipientNumber,
                        payerNumber = payerNumber,
                        isOffline = isOffline,
                        onPayClick = {
                            if (isOffline) {
                                purchaseStep = 5
                            } else {
                                purchaseStep = 3
                                coroutineScope.launch {
                                    isLoading = true
                                    val record = onExecuteStkPush(offer, recipientNumber, payerNumber)
                                    lastRecord = record
                                    isLoading = false
                                    purchaseStep = 4
                                }
                            }
                        },
                        onChangeDetails = { purchaseStep = 1 }
                    )

                    3 -> StkProcessingStep(
                        payerNumber = payerNumber,
                        onChangeNumber = { purchaseStep = 1 }
                    )

                    4 -> PaymentResultStep(
                        record = lastRecord,
                        onDone = onDismiss,
                        onViewActivity = {
                            onDismiss()
                            onViewActivity()
                        },
                        onTryAgain = { purchaseStep = 2 }
                    )

                    5 -> OfflinePaymentInstructionsStep(
                        offer = offer,
                        isTill = isForSelf,
                        recipientNumber = recipientNumber,
                        onPaidConfirmed = {
                            coroutineScope.launch {
                                onExecuteOfflinePayment(offer, recipientNumber, payerNumber, isForSelf)
                                onDismiss()
                                onViewActivity()
                            }
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun RecipientSelectionStep(
    offer: OfferItem,
    isForSelf: Boolean,
    recipientNumber: String,
    payerNumber: String,
    userPrimaryNumber: String,
    recentRecipients: List<String>,
    onOptionSelect: (Boolean) -> Unit,
    onRecipientChange: (String) -> Unit,
    onPayerChange: (String) -> Unit,
    onNext: () -> Unit
) {
    Column(modifier = Modifier.fillMaxWidth()) {
        Text(
            text = "Who is the bundle for?",
            style = TypographySheetHeading,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )

        Spacer(modifier = Modifier.height(16.dp))

        // Large Option 1: For my number
        SelectableRecipientCard(
            title = "For my number",
            subtitle = userPrimaryNumber,
            isSelected = isForSelf,
            onClick = { onOptionSelect(true) },
            testTag = "recipient_for_my_number"
        )

        Spacer(modifier = Modifier.height(12.dp))

        // Large Option 2: For another number
        SelectableRecipientCard(
            title = "For another number",
            subtitle = "Buy data or SMS for family and friends",
            isSelected = !isForSelf,
            onClick = { onOptionSelect(false) },
            testTag = "recipient_for_another_number"
        )

        Spacer(modifier = Modifier.height(20.dp))

        if (!isForSelf) {
            LabelledPhoneField(
                label = "Bundle recipient number",
                value = recipientNumber,
                onValueChange = onRecipientChange,
                placeholder = "0712 345 678",
                testTag = "recipient_number_field"
            )

            if (recentRecipients.isNotEmpty()) {
                Spacer(modifier = Modifier.height(12.dp))
                Text(
                    text = "Recent Recipients",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Spacer(modifier = Modifier.height(6.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    recentRecipients.take(3).forEach { rec ->
                        Surface(
                            shape = FieldButtonShape,
                            color = MaterialTheme.colorScheme.surfaceVariant,
                            modifier = Modifier.clickable { onRecipientChange(rec) }
                        ) {
                            Text(
                                text = rec,
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.SemiBold,
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                            )
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            LabelledPhoneField(
                label = "M-Pesa payment number (payer)",
                value = payerNumber,
                onValueChange = onPayerChange,
                placeholder = userPrimaryNumber,
                testTag = "payer_number_field"
            )
        }

        Spacer(modifier = Modifier.height(28.dp))

        PrimaryButton(
            text = "Confirm",
            onClick = onNext,
            enabled = recipientNumber.isNotBlank() && payerNumber.isNotBlank(),
            testTag = "review_purchase_button"
        )
    }
}

@Composable
private fun SelectableRecipientCard(
    title: String,
    subtitle: String,
    isSelected: Boolean,
    onClick: () -> Unit,
    testTag: String
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(FieldButtonShape)
            .background(if (isSelected) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface)
            .border(
                1.5.dp,
                if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline.copy(alpha = 0.5f),
                FieldButtonShape
            )
            .clickable { onClick() }
            .padding(16.dp)
            .testTag(testTag),
        verticalAlignment = Alignment.CenterVertically
    ) {
        RadioButton(
            selected = isSelected,
            onClick = onClick,
            colors = RadioButtonDefaults.colors(selectedColor = MaterialTheme.colorScheme.primary)
        )
        Spacer(modifier = Modifier.width(12.dp))
        Column {
            Text(
                text = title,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface
            )
            Text(
                text = subtitle,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun ReviewPurchaseStep(
    offer: OfferItem,
    recipientNumber: String,
    payerNumber: String,
    isOffline: Boolean,
    onPayClick: () -> Unit,
    onChangeDetails: () -> Unit
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = "Confirm Purchase",
            style = TypographySheetHeading,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )

        Spacer(modifier = Modifier.height(16.dp))

        // Big Offer Name & Validity Banner
        Surface(
            color = MaterialTheme.colorScheme.primaryContainer,
            shape = FieldButtonShape,
            modifier = Modifier.fillMaxWidth()
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = offer.name,
                    style = MaterialTheme.typography.titleLarge,
                    color = MaterialTheme.colorScheme.onPrimaryContainer,
                    fontWeight = FontWeight.ExtraBold,
                    textAlign = TextAlign.Center
                )
                Text(
                    text = "Validity: ${offer.validity}",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.85f),
                    fontWeight = FontWeight.SemiBold
                )
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Summary Group
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(FieldButtonShape)
                .background(MaterialTheme.colorScheme.surfaceVariant)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            SummaryRow(label = "Recipient", value = recipientNumber)
            SummaryRow(label = "Daily Rule", value = offer.dailyRule.displayText)
            SummaryRow(label = "Amount", value = "KSh ${offer.priceKsh}")
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Total Price (Strongest hierarchy)
        Text(
            text = "Total Amount",
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Text(
            text = "KSh ${offer.priceKsh}",
            style = TypographyReviewTotal,
            color = MaterialTheme.colorScheme.primary,
            fontWeight = FontWeight.ExtraBold
        )

        Spacer(modifier = Modifier.height(24.dp))

        PrimaryButton(
            text = if (isOffline) "Confirm & Pay Offline" else "Confirm & Pay KSh ${offer.priceKsh}",
            onClick = onPayClick,
            testTag = "pay_now_button"
        )

        Spacer(modifier = Modifier.height(8.dp))

        SecondaryButton(
            text = "Change details",
            onClick = onChangeDetails,
            testTag = "change_details_button"
        )
    }
}

@Composable
private fun SummaryRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )
    }
}

@Composable
private fun StkProcessingStep(
    payerNumber: String,
    onChangeNumber: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        CircularProgressIndicator(
            modifier = Modifier.size(56.dp),
            color = MaterialTheme.colorScheme.primary,
            strokeWidth = 4.dp
        )

        Spacer(modifier = Modifier.height(24.dp))

        Text(
            text = "Check your phone",
            style = TypographyPageHeading.copy(fontSize = 24.sp),
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )

        Spacer(modifier = Modifier.height(12.dp))

        Text(
            text = "We sent an M-Pesa prompt to $payerNumber. Enter your M-Pesa PIN on that phone.",
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center
        )

        Spacer(modifier = Modifier.height(28.dp))

        SecondaryButton(
            text = "Change payment number",
            onClick = onChangeNumber
        )
    }
}

@Composable
private fun PaymentResultStep(
    record: PurchaseRecord?,
    onDone: () -> Unit,
    onViewActivity: () -> Unit,
    onTryAgain: () -> Unit
) {
    if (record == null) return

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 16.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        when (record.status) {
            PaymentStatus.RECEIVED -> {
                Box(
                    modifier = Modifier
                        .size(64.dp)
                        .clip(CircleShape)
                        .background(MaterialTheme.colorScheme.primaryContainer),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Check,
                        contentDescription = "Success",
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(36.dp)
                    )
                }

                Spacer(modifier = Modifier.height(16.dp))

                Text(
                    text = "Purchase successful",
                    style = TypographyPageHeading.copy(fontSize = 24.sp),
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface
                )

                Spacer(modifier = Modifier.height(8.dp))

                Text(
                    text = "Your bundle will be received in a few minutes.",
                    style = MaterialTheme.typography.bodyLarge,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center
                )

                Spacer(modifier = Modifier.height(16.dp))

                // Summary Box
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(FieldButtonShape)
                        .background(MaterialTheme.colorScheme.surfaceVariant)
                        .padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    SummaryRow(label = "Offer bought", value = record.offerName)
                    SummaryRow(label = "Recipient", value = record.recipientNumber)
                    SummaryRow(label = "Amount", value = "KSh ${record.priceKsh}")
                    SummaryRow(label = "M-Pesa Code", value = record.mpesaCode)
                }

                Spacer(modifier = Modifier.height(28.dp))

                PrimaryButton(
                    text = "Done",
                    onClick = onDone,
                    testTag = "payment_done_button"
                )

                Spacer(modifier = Modifier.height(10.dp))

                SecondaryButton(
                    text = "View activity",
                    onClick = onViewActivity,
                    testTag = "view_activity_button"
                )
            }

            PaymentStatus.CANCELLED -> {
                Icon(
                    imageVector = Icons.Outlined.ErrorOutline,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.error,
                    modifier = Modifier.size(56.dp)
                )
                Spacer(modifier = Modifier.height(16.dp))
                Text("Payment cancelled", style = TypographyPageHeading.copy(fontSize = 22.sp), fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(8.dp))
                Text("No payment was completed. Your details are still here.", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant, textAlign = TextAlign.Center)
                Spacer(modifier = Modifier.height(24.dp))
                PrimaryButton(text = "Try again", onClick = onTryAgain)
            }

            PaymentStatus.FAILED -> {
                Icon(
                    imageVector = Icons.Outlined.ErrorOutline,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.error,
                    modifier = Modifier.size(56.dp)
                )
                Spacer(modifier = Modifier.height(16.dp))
                Text("We couldn't complete the payment", style = TypographyPageHeading.copy(fontSize = 22.sp), fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
                Spacer(modifier = Modifier.height(8.dp))
                Text("Please check your M-Pesa balance or signal and try again.", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant, textAlign = TextAlign.Center)
                Spacer(modifier = Modifier.height(24.dp))
                PrimaryButton(text = "Try again", onClick = onTryAgain)
            }

            PaymentStatus.WAITING_VERIFY -> {
                Icon(
                    imageVector = Icons.Outlined.HourglassEmpty,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.tertiary,
                    modifier = Modifier.size(56.dp)
                )
                Spacer(modifier = Modifier.height(16.dp))
                Text("Still checking payment", style = TypographyPageHeading.copy(fontSize = 22.sp), fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(8.dp))
                Text("This is taking longer than usual. You can return to Activity or contact support.", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant, textAlign = TextAlign.Center)
                Spacer(modifier = Modifier.height(24.dp))
                PrimaryButton(text = "View activity", onClick = onViewActivity)
            }
        }
    }
}

@Composable
private fun OfflinePaymentInstructionsStep(
    offer: OfferItem,
    isTill: Boolean,
    recipientNumber: String,
    onPaidConfirmed: () -> Unit
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = if (isTill) "Pay using M-Pesa Till" else "Pay using M-Pesa Paybill",
            style = TypographySheetHeading,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )

        Spacer(modifier = Modifier.height(16.dp))

        if (isTill) {
            CopyableValueBlock(label = "M-Pesa Till Number", value = "4953696")
        } else {
            CopyableValueBlock(label = "Paybill Business Number", value = "40450595")
            Spacer(modifier = Modifier.height(10.dp))
            CopyableValueBlock(label = "Account Number (Recipient)", value = recipientNumber)
        }

        Spacer(modifier = Modifier.height(10.dp))
        CopyableValueBlock(label = "Exact Amount", value = "KSh ${offer.priceKsh}")

        Spacer(modifier = Modifier.height(20.dp))

        Text(
            text = "Numbered Instructions:",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.fillMaxWidth()
        )

        Spacer(modifier = Modifier.height(8.dp))

        Column(
            modifier = Modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            InstructionLine("1", "Open M-Pesa menu on your phone")
            InstructionLine("2", "Choose Lipa na M-Pesa")
            InstructionLine("3", if (isTill) "Choose Buy Goods and Services" else "Choose Paybill")
            InstructionLine("4", if (isTill) "Enter Till number 4953696" else "Enter Business number 40450595")
            if (!isTill) InstructionLine("5", "Enter $recipientNumber as the account number")
            InstructionLine(if (isTill) "5" else "6", "Enter exact amount KSh ${offer.priceKsh}")
            InstructionLine(if (isTill) "6" else "7", "Enter your M-Pesa PIN and confirm")
        }

        Spacer(modifier = Modifier.height(28.dp))

        PrimaryButton(
            text = "I've paid",
            onClick = onPaidConfirmed,
            testTag = "ive_paid_button"
        )
    }
}

@Composable
private fun InstructionLine(step: String, text: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.Top
    ) {
        Text(
            text = "$step.",
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.primary,
            modifier = Modifier.width(24.dp)
        )
        Text(
            text = text,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurface
        )
    }
}
