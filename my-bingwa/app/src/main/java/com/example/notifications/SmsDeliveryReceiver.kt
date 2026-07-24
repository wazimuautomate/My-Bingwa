package com.example.notifications

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import com.example.core.notifications.LocalSeedTemplateProvider
import com.example.core.notifications.SafaricomSmsParser

/**
 * Receives incoming SMS ([Telephony.Sms.Intents.SMS_RECEIVED_ACTION]) and, when
 * a message matches a Safaricom bundle-delivery or low-balance template, emits a
 * classified signal on [SmsSignalBus] for the app to reconcile.
 *
 * Honesty & privacy (CLAUDE.md §10):
 * - Full SMS bodies and originating numbers are NEVER logged.
 * - Only the classified [com.example.core.notifications.SmsSignal] (category +
 *   raw body, consumed in-process) leaves this receiver, via the in-memory bus.
 * - Everything is wrapped so a malformed intent can never crash the app.
 *
 * The parser and templates are pure/offline; the seed template provider is used
 * directly here (no DI). A future server-synced provider can replace it.
 */
class SmsDeliveryReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION) return

        try {
            val messages = Telephony.Sms.Intents.getMessagesFromIntent(intent)
            if (messages == null || messages.isEmpty()) return

            // A long SMS arrives as multiple parts sharing one originating
            // address; concatenate the bodies to reconstruct the full text.
            val sender = messages.firstOrNull()?.originatingAddress ?: return
            val body = buildString {
                for (message in messages) {
                    append(message.messageBody ?: message.displayMessageBody ?: "")
                }
            }
            if (body.isBlank()) return

            val signal = SafaricomSmsParser.parse(
                sender = sender,
                body = body,
                templates = LocalSeedTemplateProvider().current()
            ) ?: return

            SmsSignalBus.emit(signal)
        } catch (e: Exception) {
            // Never crash on a malformed broadcast; nothing sensitive is logged.
        }
    }
}
