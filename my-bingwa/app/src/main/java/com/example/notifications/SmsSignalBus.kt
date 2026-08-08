package com.example.notifications

import com.example.core.notifications.SmsSignal
import kotlinx.coroutines.flow.MutableSharedFlow

/**
 * Process-wide, DI-free seam between the manifest-registered
 * [SmsDeliveryReceiver] and the running UI.
 *
 * The receiver has no access to the repository (it is constructed by the
 * system), so it emits the parsed [SmsSignal] here and MainActivity collects it
 * while the app is in the foreground, forwarding it to the repository. This is
 * intentionally tiny: a single hot [MutableSharedFlow] with a small buffer so a
 * burst of messages is not dropped, and **no replay** so a late collector does
 * not re-process an old signal.
 *
 * One matched SMS can now produce SEVERAL signals — the rich
 * [SmsSignal.EventDetected] plus the legacy delivery/low-balance signals it maps
 * onto (a "50 Minutes + 50 SMS" message emits three). The buffer is sized for
 * that fan-out, not just for one signal per message.
 *
 * Only classified signals (category + raw body, consumed in-process) travel here
 * — never the originating number. Nothing is logged (CLAUDE.md §10).
 */
object SmsSignalBus {

    val signals: MutableSharedFlow<SmsSignal> = MutableSharedFlow(
        replay = 0,
        extraBufferCapacity = 32
    )

    /** Non-suspending emit; drops silently if the buffer is somehow full. */
    fun emit(signal: SmsSignal) {
        signals.tryEmit(signal)
    }
}
