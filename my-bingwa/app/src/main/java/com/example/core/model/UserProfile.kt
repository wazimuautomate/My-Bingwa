package com.example.core.model

data class UserProfile(
    val name: String = "",
    val primaryNumber: String = "",
    val isOnboardingCompleted: Boolean = false,
    val notificationsEnabled: Boolean = false,
    val offersPromotionsEnabled: Boolean = true,
    val helpfulRemindersEnabled: Boolean = true,
    val personalisedMessagesEnabled: Boolean = true,
    val appUpdatesEnabled: Boolean = true,
    /**
     * Whether the customer opted into Safaricom bundle/balance SMS detection.
     * Reflects the real OS RECEIVE_SMS grant (set from MainActivity), and is
     * persisted so the Settings toggle no longer resets on every visit.
     */
    val smsAlertsEnabled: Boolean = false
)
