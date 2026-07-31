package com.example.notifications

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import com.example.core.model.OfferCategory
import com.example.core.notifications.SmsSignal
import com.example.core.sms.DefaultSmsRules
import com.example.core.sms.DynamicSmsParser
import com.example.core.sms.SmsEventMapping
import com.example.core.sms.SmsMatch
import com.example.core.sms.SmsRuleProvider
import com.example.core.sms.SmsRuleSet
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.coroutines.withTimeoutOrNull

/**
 * Receives incoming SMS ([Telephony.Sms.Intents.SMS_RECEIVED_ACTION]) and, when a
 * message matches a rule from the DYNAMIC, server-taught engine, emits classified
 * signals on [SmsSignalBus] for the app to reconcile.
 *
 * Rules come from [SmsRuleProvider] — the in-APK seed until the server teaches
 * the app newer message shapes — so a Safaricom wording change needs no app
 * release (Feature 2).
 *
 * THREADING. `onReceive` runs on the main thread and a [BroadcastReceiver] may
 * not block on a suspending DataStore read there. So:
 *  1. the FAST PATH parses against [SmsRuleProvider.cachedOrSeed], which is
 *     synchronous and, after the first sync, is the normal case;
 *  2. only when that finds nothing do we [goAsync] and read the cached rules off
 *     disk on [Dispatchers.IO] with a hard 3s timeout, always finishing the
 *     pending result in a `finally` so the receiver can never be left dangling.
 *
 * PRIVACY (CLAUDE.md §10): the body and the originating address are never
 * logged, never persisted and never sent anywhere. Parsing is 100% on-device and
 * only the classified signal is published, in-process.
 *
 * PERMISSIONS: this receiver simply never fires without RECEIVE_SMS (and the
 * `play` flavour removes it from the manifest entirely), so an ungranted
 * permission degrades silently to "detection inactive" — no UI, no error.
 */
class SmsDeliveryReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        // A malformed or unrelated broadcast must never reach the parser.
        if (intent.action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION) return

        val message = readMessage(intent) ?: return
        val sender = message.first
        val body = message.second

        val provider = try {
            SmsRuleProvider.shared(context)
        } catch (e: Exception) {
            null
        }

        // FAST PATH — synchronous, no disk, covers the normal case.
        val cachedRules: SmsRuleSet = provider?.cachedOrSeed() ?: DefaultSmsRules.SEED
        val fastMatch = safeParse(sender, body, cachedRules)
        if (fastMatch != null) {
            publish(fastMatch, body)
            return
        }
        val activeProvider = provider ?: return

        // SLOW PATH — the phone may have server rules on disk that this cold
        // process has not loaded yet. Keep the broadcast alive while we look.
        val pendingOrNull: BroadcastReceiver.PendingResult? = try {
            goAsync()
        } catch (e: Exception) {
            null
        }
        val pending = pendingOrNull ?: return

        scope.launch {
            try {
                val stored = withTimeoutOrNull(RULE_LOAD_TIMEOUT_MILLIS) { activeProvider.current() }
                if (stored != null && stored !== cachedRules) {
                    val match = safeParse(sender, body, stored)
                    if (match != null) publish(match, body)
                }
            } catch (e: Exception) {
                // Unreadable cache / cancelled load: the message is simply ignored.
            } finally {
                try {
                    pending.finish()
                } catch (e: Exception) {
                    // Already finished or the process is going away — nothing to do.
                }
            }
        }
    }

    /**
     * Pull (sender, full body) out of the broadcast, or null when the intent is
     * malformed/empty. A long SMS arrives as several parts sharing one
     * originating address, so the bodies are concatenated back together.
     *
     * Neither value is logged or retained — they exist only for the duration of
     * the on-device parse (CLAUDE.md §10).
     */
    private fun readMessage(intent: Intent): Pair<String, String>? = try {
        val messages = Telephony.Sms.Intents.getMessagesFromIntent(intent)
        if (messages == null || messages.isEmpty()) {
            null
        } else {
            val sender = messages.firstOrNull()?.originatingAddress
            val body = buildString {
                for (part in messages) {
                    if (part == null) continue
                    append(part.messageBody ?: part.displayMessageBody ?: "")
                }
            }
            if (sender == null || body.isBlank()) null else Pair(sender, body)
        }
    } catch (e: Exception) {
        // Never crash on a malformed broadcast; nothing sensitive is logged.
        null
    }

    /** Parsing is pure, but a hostile rule set must still never crash the receiver. */
    private fun safeParse(sender: String, body: String, rules: SmsRuleSet): SmsMatch? = try {
        DynamicSmsParser.parse(sender, body, rules)
    } catch (e: Exception) {
        null
    }

    /**
     * Publish one match.
     *
     * The rich [SmsSignal.EventDetected] goes out first, then — for backwards
     * compatibility with the existing reconciliation in MainActivity and the
     * repository — the legacy [SmsSignal.DeliveryDetected] /
     * [SmsSignal.LowBalanceDetected] each event maps onto. Duplicate
     * (kind, category) pairs are collapsed so a message mentioning the same
     * category twice cannot double-reconcile.
     */
    private fun publish(match: SmsMatch, body: String) {
        try {
            SmsSignalBus.emit(
                SmsSignal.EventDetected(
                    match = match,
                    rawBody = body,
                    category = SmsEventMapping.primaryCategory(match)
                )
            )

            val alreadyEmitted = mutableSetOf<Pair<Boolean, OfferCategory>>()
            for (event in match.events) {
                val category = SmsEventMapping.categoryFor(event, match.extraction) ?: continue
                when {
                    SmsEventMapping.isDelivery(event) ->
                        if (alreadyEmitted.add(true to category)) {
                            SmsSignalBus.emit(SmsSignal.DeliveryDetected(category, body))
                        }

                    SmsEventMapping.isLowBalance(event) ->
                        if (alreadyEmitted.add(false to category)) {
                            SmsSignalBus.emit(SmsSignal.LowBalanceDetected(category, body))
                        }
                }
            }
        } catch (e: Exception) {
            // Emission is best-effort; a full buffer or a torn-down process is not fatal.
        }
    }

    private companion object {
        /**
         * A broadcast must not hold the system hostage. 3s is generous for one
         * DataStore read and well inside the receiver's own execution budget.
         */
        const val RULE_LOAD_TIMEOUT_MILLIS = 3000L

        /**
         * One shared scope for the rare slow path. A [SupervisorJob] keeps one
         * failed load from cancelling the next broadcast's work.
         */
        val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    }
}
