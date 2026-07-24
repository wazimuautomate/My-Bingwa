package com.example.data.payment

import com.example.core.payment.PaymentTxnState

/**
 * Selects the online payment gateway at composition root time.
 *
 * - When a backend base URL is configured → the real [BackendPaymentGateway]
 *   (no secrets in the app; the backend owns Daraja).
 * - When it is blank → a clearly-labelled [SimulatedPaymentGateway] so the app
 *   stays testable on a phone until the backend exists.
 *
 * The base URL is a non-secret value injected via `BuildConfig.PAYMENTS_BASE_URL`.
 */
object PaymentGatewayProvider {

    /** True when a usable backend base URL is present. */
    fun isBackendConfigured(baseUrl: String?): Boolean =
        !baseUrl.isNullOrBlank() && baseUrl.startsWith("https://")

    fun create(
        baseUrl: String?,
        debugLogging: Boolean,
        simulatedOutcome: () -> PaymentTxnState = { PaymentTxnState.PAYMENT_CONFIRMED }
    ): PaymentGateway =
        if (isBackendConfigured(baseUrl)) {
            BackendPaymentGateway.create(normaliseBaseUrl(baseUrl!!), enableLogging = debugLogging)
        } else {
            SimulatedPaymentGateway(terminalOutcome = simulatedOutcome)
        }

    /** Retrofit requires the base URL to end with a slash. */
    private fun normaliseBaseUrl(baseUrl: String): String =
        if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/"
}
