package com.example.core.model

enum class PaymentStatus(val label: String) {
    RECEIVED("Payment received"),
    WAITING_VERIFY("Waiting to verify"),
    CANCELLED("Payment cancelled"),
    FAILED("Payment failed")
}

enum class PaymentMethod(val label: String) {
    STK_PUSH("M-Pesa STK Push"),
    TILL("M-Pesa Till"),
    PAYBILL("M-Pesa Paybill")
}

data class PurchaseRecord(
    val id: String,
    val offerId: String,
    val offerName: String,
    val allowance: String,
    val priceKsh: Int,
    val recipientNumber: String,
    val payerNumber: String,
    val mpesaCode: String,
    val timestampMillis: Long,
    val status: PaymentStatus,
    val paymentMethod: PaymentMethod
)
