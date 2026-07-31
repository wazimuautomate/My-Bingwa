package com.example.core.notifications.engine

/**
 * The assistant's voice.
 *
 * Every notification My Bingwa posts is rendered from one of these templates, so
 * the copy can be refreshed from the server without shipping a new APK while a
 * complete, honest seed set always exists offline inside the APK.
 *
 * ## Supported placeholders
 *
 * The composer replaces these tokens; an unknown or unsupplied token is removed
 * safely and NEVER left as a literal `{x}`:
 *
 *  - `{name}`      the customer's first name. When it is blank the surrounding
 *                  punctuation is removed too, so the line still reads naturally
 *                  ("Hi ," never appears).
 *  - `{greeting}`  a time-of-day greeting that already includes the name, e.g.
 *                  "Good morning James" / "Still awake James?" / "Good evening".
 *  - `{bundle}`    the bundle the customer usually buys, e.g. "Sh20 = 250MB".
 *                  Falls back to "your usual bundle".
 *  - `{amount}`    the amount they usually spend, already prefixed, e.g. "Sh20".
 *                  Falls back to "a small top-up".
 *  - `{balance}`   the remaining balance EXACTLY as Safaricom stated it. Falls
 *                  back to "very little". See the honesty rule below.
 *  - `{recipient}` the recipient label, already masked by the caller.
 *                  Falls back to "that number".
 *  - `{days}`      days since the last purchase, e.g. "3 days" / "1 day".
 *                  Falls back to "a while".
 *  - `{category}`  "data" | "SMS" | "minutes". Falls back to "data".
 *
 * ## HONESTY RULES (CLAUDE.md §7 and §8) — these bind every template, seed or
 * ## server-published, and are enforced by unit tests.
 *
 * 1. My Bingwa NEVER claims it delivered a bundle. The customer app does not
 *    deliver bundles and does not verify delivery in version 1. These strings
 *    are banned anywhere in a notification: "Bundle delivered", "Data
 *    activated", "Delivery successful", "Bundle confirmed". A payment ends at
 *    "Payment received" and asks the customer to wait.
 * 2. Anything that sounds like a bundle arriving is attributed to Safaricom and
 *    only ever fires from a real carrier SMS event that the caller passes in
 *    (the [NotificationCategory.BUNDLE_RECEIVED] category).
 * 3. Balance statements ("you're almost out of data", `{balance}`) are permitted
 *    ONLY for the [NotificationCategory.isBalanceDriven] categories — LOW_DATA,
 *    VERY_LOW_DATA, NO_DATA, LOW_SMS, LOW_MINUTES — because there the carrier,
 *    not My Bingwa, is the factual source. For every other category the app must
 *    never claim to know the customer's usage. These strings are banned
 *    anywhere: "Recommended for your usage", "Based on your browsing", "You need
 *    more data", "You are running out of data". The composer additionally drops
 *    any template containing `{balance}` when the category is not balance-driven.
 * 4. Bundle suggestions come from the customer's OWN local purchase history
 *    (`{bundle}` / `{amount}`), never from a guess about their consumption.
 *
 * ## Emoji: none
 *
 * NO emoji, in notifications or anywhere else. An earlier revision allowed
 * sparing emoji in notification copy; the owner has since reversed that, which
 * also brings this file back in line with CLAUDE.md §6. A unit test fails the
 * build if an emoji reappears in the seed.
 *
 * ## Length
 *
 * Titles at most 45 characters, bodies at most 90, measured AFTER placeholder
 * substitution with a realistic name — a template that only fits when the name is
 * blank is not actually within budget.
 */
data class NotificationTemplate(
    val id: String = "",
    /**
     * The [NotificationCategory] name as a STRING, so a server-published set that
     * names a category this build does not know can never crash the app — it is
     * simply never selected.
     */
    val category: String = "",
    val title: String = "",
    val body: String = "",
    /** Relative selection weight. Values below 1 are treated as 1. */
    val weight: Int = 1,
    val enabled: Boolean = true
)

/**
 * A whole set of templates plus its revision. The provider prefers a stored set
 * only when its [version] is higher than the in-APK seed's.
 *
 * Every field has a default so a snapshot written by an older build still
 * deserialises through Moshi reflection.
 */
data class NotificationTemplateSet(
    val version: Int = 0,
    val templates: List<NotificationTemplate> = emptyList()
)

private fun t(
    id: String,
    category: NotificationCategory,
    title: String,
    body: String,
    weight: Int = 1
): NotificationTemplate = NotificationTemplate(
    id = id,
    category = category.name,
    title = title,
    body = body,
    weight = weight,
    enabled = true
)

/**
 * The in-APK seed — the assistant's actual voice.
 *
 * ## House style (the owner's notification writing guidelines)
 *
 *  - Titles at most [MAX_TITLE_LENGTH] characters, bodies at most
 *    [MAX_BODY_LENGTH]. Both are asserted by unit tests against a realistic
 *    substituted name, not against the raw template.
 *  - Never sound like a system message, a bank, or an advert.
 *  - NO EMOJI anywhere. (The owner reversed an earlier allowance, which also
 *    brings notifications back in line with CLAUDE.md §6.)
 *  - Sparing exclamation marks.
 *  - At least [MIN_VARIANTS_PER_ACTIVE_CATEGORY] variants for every category that
 *    actually fires, shuffled by the composer and never repeated back-to-back.
 *  - Gentle humour and a light Kenyan conversational tone.
 *  - One short title per category; the variety lives in the body.
 *
 * ## Only notify when there is real value
 *
 * AFTERNOON and LATE_NIGHT deliberately have NO templates. A bare "Good
 * afternoon" carries no information, and [NotificationComposer] returns null when
 * a category has no candidates — so those categories are simply silent. MORNING
 * and EVENING exist only as HABIT reminders and must be raised solely when the
 * personalisation engine has learned the customer actually buys at that hour
 * (`HabitReminderPolicy`), never as a greeting for its own sake.
 *
 * ## Honesty, restated where it bites hardest
 *
 * [NotificationCategory.PURCHASE_SUCCESS] fires when a PAYMENT settles. The app
 * cannot see the bundle arrive (CLAUDE.md §7, and Plan.md: version 1 does not
 * verify delivery), so this copy talks about the payment ONLY.
 * [NotificationCategory.BUNDLE_RECEIVED] is the one category allowed to say a
 * bundle actually arrived, because it fires solely from a real Safaricom SMS —
 * the carrier, not My Bingwa, is the source. Never move a "your bundle is ready"
 * line into PURCHASE_SUCCESS.
 */
object DefaultNotificationTemplates {

    /** Bump when the seed copy changes; a server set must exceed this to win. */
    const val SEED_VERSION: Int = 2

    /** House style, enforced by `DefaultNotificationTemplatesTest`. */
    const val MAX_TITLE_LENGTH: Int = 45
    const val MAX_BODY_LENGTH: Int = 90
    const val MIN_VARIANTS_PER_ACTIVE_CATEGORY: Int = 10

    /**
     * Claims no notification may ever make, seed or server-published, compared
     * case-insensitively (CLAUDE.md §7 and §8).
     *
     * These are PHRASES, not bare words: "Payment confirmed" is honest and
     * allowed, while "Bundle confirmed" is a delivery claim the app cannot make.
     */
    val BANNED_PHRASES: List<String> = listOf(
        // §7 — the app neither delivers bundles nor verifies delivery in v1.
        "bundle delivered",
        "bundle is delivered",
        "data activated",
        "delivery successful",
        "bundle confirmed",
        "delivery confirmed",
        // §8 — the app never claims to know the customer's usage or to profile it.
        "recommended for your usage",
        "based on your browsing",
        "you need more data",
        "you are running out of data"
    )

    /**
     * Categories that are deliberately SILENT: a bare greeting carries no
     * information, so they ship no templates and the composer posts nothing.
     */
    val SILENT_CATEGORIES: Set<NotificationCategory> = setOf(
        NotificationCategory.AFTERNOON,
        NotificationCategory.LATE_NIGHT
    )

    /** Every enabled seed template for [category]. */
    fun forCategory(category: NotificationCategory): List<NotificationTemplate> =
        SEED.templates.filter { it.category.equals(category.name, ignoreCase = true) }

    // One short, calm title per category. The body carries the personality.
    private const val TITLE_OFFLINE = "You're offline"
    private const val TITLE_ONLINE = "You're back"
    private const val TITLE_LOW_DATA = "Almost there"
    private const val TITLE_VERY_LOW_DATA = "Down to the last bit"
    private const val TITLE_NO_DATA = "No active bundle"
    private const val TITLE_LOW_SMS = "SMS almost finished"
    private const val TITLE_LOW_MINUTES = "Minutes running low"
    private const val TITLE_BUNDLE_RECEIVED = "Bundle received"
    private const val TITLE_GIFT = "Someone came through"
    private const val TITLE_PAYMENT = "Payment received"
    private const val TITLE_MORNING = "Morning"
    private const val TITLE_EVENING = "Evening"
    private const val TITLE_QUIET = "Been a minute"
    private const val TITLE_OFFERS = "Worth a look"

    val SEED: NotificationTemplateSet = NotificationTemplateSet(
        version = SEED_VERSION,
        templates = listOf(

            // ----- OFFLINE ----------------------------------------------------
            // Observed locally: the device genuinely cannot reach the internet.
            // Any guess about WHY stays hedged ("looks like", "?"), because only
            // Safaricom can actually confirm a balance.
            t("offline_01", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "{name}, the internet left without saying goodbye. Want it back?"),
            t("offline_02", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "{name}, looks like you're out of data. Let's fix that."),
            t("offline_03", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "Quiet... a little too quiet. Time for another bundle?"),
            t("offline_04", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "Still offline? The internet is waiting for you."),
            t("offline_05", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "{name}, today's chats won't read themselves."),
            t("offline_06", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "Your apps are probably wondering where you went."),
            t("offline_07", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "No data, no scrolling. That doesn't sound like you."),
            t("offline_08", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "{name}, your bundle clock finally gave up."),
            t("offline_09", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "Internet break over? We've got bundles ready."),
            t("offline_10", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "Looks like today needs a little more data."),
            t("offline_11", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "{name}, TikTok isn't loading itself."),
            t("offline_12", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "WhatsApp is wondering where you disappeared to."),
            t("offline_13", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "Hii silence si yako. Bundle imeisha?"),
            t("offline_14", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "The group chats are moving without you."),
            t("offline_15", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "Don't let buffering win today."),
            t("offline_16", NotificationCategory.OFFLINE, TITLE_OFFLINE,
                "The memes won't wait forever."),

            // ----- ONLINE (internet restored) ---------------------------------
            t("online_01", NotificationCategory.ONLINE, TITLE_ONLINE,
                "Welcome back, {name}."),
            t("online_02", NotificationCategory.ONLINE, TITLE_ONLINE,
                "Internet restored. Business as usual."),
            t("online_03", NotificationCategory.ONLINE, TITLE_ONLINE,
                "You're connected again."),
            t("online_04", NotificationCategory.ONLINE, TITLE_ONLINE,
                "Back online. Let's keep going."),
            t("online_05", NotificationCategory.ONLINE, TITLE_ONLINE,
                "Good to have you back."),
            t("online_06", NotificationCategory.ONLINE, TITLE_ONLINE,
                "Everything's working again."),
            t("online_07", NotificationCategory.ONLINE, TITLE_ONLINE,
                "{name}, the network found its way home."),
            t("online_08", NotificationCategory.ONLINE, TITLE_ONLINE,
                "That's better. You're back on."),
            t("online_09", NotificationCategory.ONLINE, TITLE_ONLINE,
                "Signal's back. Carry on."),
            t("online_10", NotificationCategory.ONLINE, TITLE_ONLINE,
                "The group chats can see you again."),
            t("online_11", NotificationCategory.ONLINE, TITLE_ONLINE,
                "{name}, you're back where you left off."),
            t("online_12", NotificationCategory.ONLINE, TITLE_ONLINE,
                "Connection sorted. Enjoy."),

            // ----- LOW DATA ---------------------------------------------------
            // Balance-driven: Safaricom told us, so a balance may be stated.
            t("lowdata_01", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "{name}, your data is almost finished."),
            t("lowdata_02", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "Only a little data left. Don't get caught halfway."),
            t("lowdata_03", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "You're down to the last few MBs."),
            t("lowdata_04", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "Your bundle is breathing its last breath."),
            t("lowdata_05", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "{name}, one more video might finish it."),
            t("lowdata_06", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "The next reel could be the last one."),
            t("lowdata_07", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "Almost out. Better top up before it surprises you."),
            t("lowdata_08", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "Your bundle has worked hard today."),
            t("lowdata_09", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "{name}, Safaricom says you're at {balance}."),
            t("lowdata_10", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "{balance} left. Enough for a bit, not for the day."),
            t("lowdata_11", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "Running thin. A top-up now saves the evening."),
            t("lowdata_12", NotificationCategory.LOW_DATA, TITLE_LOW_DATA,
                "{name}, your streak deserves better than this."),

            // ----- VERY LOW DATA ----------------------------------------------
            t("verylow_01", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "{name}, that's barely enough to open anything."),
            t("verylow_02", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "Safaricom says {balance}. That won't go far."),
            t("verylow_03", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "You're on the very last drop."),
            t("verylow_04", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "{name}, this is the final stretch."),
            t("verylow_05", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "One page load and it's gone."),
            t("verylow_06", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "Almost nothing left. Top up before it cuts."),
            t("verylow_07", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "{name}, don't let it end mid-conversation."),
            t("verylow_08", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "This is your data waving goodbye."),
            t("verylow_09", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "Down to {balance}. Worth sorting now."),
            t("verylow_10", NotificationCategory.VERY_LOW_DATA, TITLE_VERY_LOW_DATA,
                "Nearly done. A small bundle keeps you going."),

            // ----- NO DATA ----------------------------------------------------
            t("nodata_01", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "{name}, you're officially out of data."),
            t("nodata_02", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "The internet is on pause until your next bundle."),
            t("nodata_03", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "Looks like it's bundle time again."),
            t("nodata_04", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "No data today. Let's change that."),
            t("nodata_05", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "End of the road. Time for another bundle."),
            t("nodata_06", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "{name}, your apps are waiting patiently."),
            t("nodata_07", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "That's the bundle done. Ready for the next one?"),
            t("nodata_08", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "{name}, Instagram is taking attendance."),
            t("nodata_09", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "Your friends are already online."),
            t("nodata_10", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "Bundle imeisha. Tuanze upya?"),
            t("nodata_11", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "The internet misses you too."),
            t("nodata_12", NotificationCategory.NO_DATA, TITLE_NO_DATA,
                "Empty tank. A small bundle sorts it."),

            // ----- LOW SMS ----------------------------------------------------
            t("lowsms_01", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "{name}, only a few texts left."),
            t("lowsms_02", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "Your SMS bundle is almost done."),
            t("lowsms_03", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "Better reload before they're gone."),
            t("lowsms_04", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "Your last few messages are waiting."),
            t("lowsms_05", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "{name}, Safaricom says you're at {balance}."),
            t("lowsms_06", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "Nearly out of texts. Worth topping up."),
            t("lowsms_07", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "A few messages left, then silence."),
            t("lowsms_08", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "{name}, don't run out mid-sentence."),
            t("lowsms_09", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "Your texts are counting down."),
            t("lowsms_10", NotificationCategory.LOW_SMS, TITLE_LOW_SMS,
                "Almost done. Reload when you can."),

            // ----- LOW MINUTES ------------------------------------------------
            t("lowmin_01", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "{name}, your talk time is almost over."),
            t("lowmin_02", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "Just a few minutes left."),
            t("lowmin_03", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "Your calls might get cut short soon."),
            t("lowmin_04", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "Running low? Better top up now."),
            t("lowmin_05", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "Only a little talk time remaining."),
            t("lowmin_06", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "{name}, Safaricom says you're at {balance}."),
            t("lowmin_07", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "Don't get cut off mid-story."),
            t("lowmin_08", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "{name}, that call might not finish."),
            t("lowmin_09", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "Your minutes are nearly spent."),
            t("lowmin_10", NotificationCategory.LOW_MINUTES, TITLE_LOW_MINUTES,
                "A small top-up keeps the call going."),

            // ----- BUNDLE RECEIVED --------------------------------------------
            // ONLY ever raised from a real Safaricom SMS. This is the single
            // category permitted to say a bundle actually arrived.
            t("received_01", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "Done. Your bundle is ready."),
            t("received_02", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "You're back online."),
            t("received_03", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "You're good to go."),
            t("received_04", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "{name}, Safaricom says it has landed."),
            t("received_05", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "Ready when you are."),
            t("received_06", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "That's it. Enjoy."),
            t("received_07", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "{name}, your bundle just arrived."),
            t("received_08", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "All set. Back to business."),
            t("received_09", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "Safaricom has confirmed it. You're on."),
            t("received_10", NotificationCategory.BUNDLE_RECEIVED, TITLE_BUNDLE_RECEIVED,
                "{name}, everything is in place."),

            // ----- GIFT RECEIVED ----------------------------------------------
            t("gift_01", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "{name}, someone just sent you a bundle."),
            t("gift_02", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "Nice surprise. You've received a gift."),
            t("gift_03", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "Lucky you. A bundle just landed."),
            t("gift_04", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "Someone thought about you today."),
            t("gift_05", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "Your gift has arrived."),
            t("gift_06", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "{name}, that's one good friend."),
            t("gift_07", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "A bundle showed up for you."),
            t("gift_08", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "Someone is looking out for you."),
            t("gift_09", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "{name}, you've been gifted a bundle."),
            t("gift_10", NotificationCategory.GIFT_RECEIVED, TITLE_GIFT,
                "Free bundle, courtesy of a friend."),

            // ----- PAYMENT RECEIVED -------------------------------------------
            // Fires when a PAYMENT settles. The app cannot see the bundle arrive
            // (CLAUDE.md §7), so nothing here claims that it did.
            t("payment_01", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "{name}, your payment went through."),
            t("payment_02", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "Payment confirmed. Safaricom takes it from here."),
            t("payment_03", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "Your purchase is complete."),
            t("payment_04", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "{name}, that's paid for. Hang tight."),
            t("payment_05", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "All paid. Your bundle is on its way."),
            t("payment_06", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "Got it. Payment received."),
            t("payment_07", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "{name}, everything went through."),
            t("payment_08", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "Sorted. Give it a moment to arrive."),
            t("payment_09", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "Payment done. Sit tight for a second."),
            t("payment_10", NotificationCategory.PURCHASE_SUCCESS, TITLE_PAYMENT,
                "That's settled, {name}."),

            // ----- MORNING (habit-gated: learned morning buyer) ----------------
            t("morning_01", NotificationCategory.MORNING, TITLE_MORNING,
                "Morning {name}. Starting the day online?"),
            t("morning_02", NotificationCategory.MORNING, TITLE_MORNING,
                "Your usual bundle is waiting."),
            t("morning_03", NotificationCategory.MORNING, TITLE_MORNING,
                "Ready for today's bundle?"),
            t("morning_04", NotificationCategory.MORNING, TITLE_MORNING,
                "Let's get you connected before the day gets busy."),
            t("morning_05", NotificationCategory.MORNING, TITLE_MORNING,
                "Same bundle as yesterday?"),
            t("morning_06", NotificationCategory.MORNING, TITLE_MORNING,
                "{name}, {bundle} whenever you're ready."),
            t("morning_07", NotificationCategory.MORNING, TITLE_MORNING,
                "Early start? Sort the data first."),
            t("morning_08", NotificationCategory.MORNING, TITLE_MORNING,
                "The usual, {name}?"),
            t("morning_09", NotificationCategory.MORNING, TITLE_MORNING,
                "New day, same routine. Bundle first."),
            t("morning_10", NotificationCategory.MORNING, TITLE_MORNING,
                "Morning. Shall we get you online?"),

            // ----- EVENING (habit-gated: learned evening buyer) ----------------
            t("evening_01", NotificationCategory.EVENING, TITLE_EVENING,
                "Evening {name}. Time for your usual bundle?"),
            t("evening_02", NotificationCategory.EVENING, TITLE_EVENING,
                "Heading home? Stay connected."),
            t("evening_03", NotificationCategory.EVENING, TITLE_EVENING,
                "Your evening bundle is waiting."),
            t("evening_04", NotificationCategory.EVENING, TITLE_EVENING,
                "Looks like your regular bundle hour."),
            t("evening_05", NotificationCategory.EVENING, TITLE_EVENING,
                "{name}, {bundle} same as always?"),
            t("evening_06", NotificationCategory.EVENING, TITLE_EVENING,
                "Winding down? Sort the data first."),
            t("evening_07", NotificationCategory.EVENING, TITLE_EVENING,
                "The usual hour, {name}."),
            t("evening_08", NotificationCategory.EVENING, TITLE_EVENING,
                "Evening scrolling starts here."),
            t("evening_09", NotificationCategory.EVENING, TITLE_EVENING,
                "Long day? Get yourself back online."),
            t("evening_10", NotificationCategory.EVENING, TITLE_EVENING,
                "Ready for tonight's bundle?"),

            // ----- HABIT REMINDER / WEEKEND -----------------------------------
            t("habit_01", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "{name}, this is usually your bundle time."),
            t("habit_02", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "Weekend plans need data too."),
            t("habit_03", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "Don't let the weekend go offline."),
            t("habit_04", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "Saturday feels better online."),
            t("habit_05", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "Sunday scrolling starts here."),
            t("habit_06", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "{name}, your usual hour came and went."),
            t("habit_07", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "No bundle yet today. Everything alright?"),
            t("habit_08", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "{bundle} is one tap away."),
            t("habit_09", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "Same time, same bundle?"),
            t("habit_10", NotificationCategory.HABIT_REMINDER, TITLE_QUIET,
                "{name}, keeping the streak going?"),

            // ----- INACTIVITY -------------------------------------------------
            t("quiet_01", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "{name}, it's been {days}. All good?"),
            t("quiet_02", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "Haven't seen you in a while."),
            t("quiet_03", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "The internet misses you too."),
            t("quiet_04", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "{name}, your usual bundle is still here."),
            t("quiet_05", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "Been quiet lately. Everything fine?"),
            t("quiet_06", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "{days} without a bundle. That's unlike you."),
            t("quiet_07", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "Your friends are already online."),
            t("quiet_08", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "{name}, whenever you're ready, we're here."),
            t("quiet_09", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "The group chats moved on without you."),
            t("quiet_10", NotificationCategory.INACTIVITY, TITLE_QUIET,
                "Still around, {name}?"),

            // ----- PROMOTION / GENERAL ----------------------------------------
            // Deliberately restrained. A promotion only earns a notification when
            // it is a real saving, so the copy never oversells.
            t("promo_01", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "{name}, there's a better price on today's bundles."),
            t("promo_02", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "Today's deals are a little kinder than usual."),
            t("promo_03", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "New offers just landed."),
            t("promo_04", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "{name}, {bundle} is cheaper today."),
            t("promo_05", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "Something worth checking in the offers."),
            t("promo_06", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "A few fresh deals for today."),
            t("promo_07", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "{name}, this one is worth a look."),
            t("promo_08", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "Better value than yesterday."),
            t("promo_09", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "Today's list has something new."),
            t("promo_10", NotificationCategory.PROMOTION, TITLE_OFFERS,
                "Fresh offers, no fuss."),

            t("general_01", NotificationCategory.GENERAL, TITLE_OFFERS,
                "{name}, there's an update from My Bingwa."),
            t("general_02", NotificationCategory.GENERAL, TITLE_OFFERS,
                "Something new from My Bingwa."),
            t("general_03", NotificationCategory.GENERAL, TITLE_OFFERS,
                "A quick note from My Bingwa.")

            // AFTERNOON and LATE_NIGHT intentionally have NO templates — a bare
            // greeting is not worth a notification. The composer returns null and
            // nothing is posted. See the KDoc above.
        )
    )
}
