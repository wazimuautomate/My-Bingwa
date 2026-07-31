package com.example.core.notifications.engine

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The seed copy is the app's voice AND its honesty guarantee.
 *
 * These tests enforce two separate things:
 *  - the owner's notification writing guidelines (length, no emoji, enough
 *    variants to shuffle, no system/bank/advert tone);
 *  - CLAUDE.md §7 (never claim delivery) and §8 (never claim to know usage).
 *
 * Lengths are measured AFTER placeholder substitution with a realistic name. A
 * line that only fits when the name is blank is not actually within budget.
 */
class DefaultNotificationTemplatesTest {

    /** A realistic Kenyan first name, longer than "James", to size the copy against. */
    private val sampleName = "Wanjiku"

    private fun rendered(template: NotificationTemplate): Pair<String, String> {
        val personalization = NotificationPersonalization(
            userName = sampleName,
            nowMillis = 0L,
            usualBundleLabel = "Sh100 = 1.5GB",
            usualAmountKsh = 100,
            balanceText = "75MB",
            daysSinceLastPurchase = 3,
            categoryLabel = "data"
        )
        val category = NotificationCategory.fromName(template.category)!!
        val composed = NotificationComposer.compose(
            category = category,
            personalization = personalization,
            templates = NotificationTemplateSet(
                version = DefaultNotificationTemplates.SEED_VERSION,
                templates = listOf(template)
            ),
            seed = 1L,
            lastTemplateId = null
        )!!
        return composed.title to composed.body
    }

    // ----- Owner's writing guidelines ---------------------------------------

    @Test
    fun `titles fit the 45 character budget once the name is substituted`() {
        for (template in DefaultNotificationTemplates.SEED.templates) {
            val (title, _) = rendered(template)
            assertTrue(
                "title of '${template.id}' is ${title.length} chars: \"$title\"",
                title.length <= DefaultNotificationTemplates.MAX_TITLE_LENGTH
            )
        }
    }

    @Test
    fun `bodies fit the 90 character budget once the name is substituted`() {
        for (template in DefaultNotificationTemplates.SEED.templates) {
            val (_, body) = rendered(template)
            assertTrue(
                "body of '${template.id}' is ${body.length} chars: \"$body\"",
                body.length <= DefaultNotificationTemplates.MAX_BODY_LENGTH
            )
        }
    }

    @Test
    fun `no seed template contains an emoji`() {
        for (template in DefaultNotificationTemplates.SEED.templates) {
            val text = template.title + " " + template.body
            for (character in text) {
                val code = character.code
                val isEmoji = code in 0x1F000..0x1FAFF ||
                    code in 0x2600..0x27BF ||
                    code == 0xFE0F ||
                    Character.isSurrogate(character)
                assertTrue(
                    "template '${template.id}' contains an emoji: \"$text\"",
                    !isEmoji
                )
            }
        }
    }

    @Test
    fun `every category that fires has at least ten variants to shuffle`() {
        for (category in NotificationCategory.values()) {
            if (category in DefaultNotificationTemplates.SILENT_CATEGORIES) continue
            // GENERAL is admin-published: notifyRaw supplies the copy directly.
            if (category == NotificationCategory.GENERAL) continue
            val templates = DefaultNotificationTemplates.forCategory(category)
            assertTrue(
                "${category.name} has only ${templates.size} variants",
                templates.size >= DefaultNotificationTemplates.MIN_VARIANTS_PER_ACTIVE_CATEGORY
            )
        }
    }

    @Test
    fun `silent categories ship no copy so nothing generic is ever posted`() {
        for (category in DefaultNotificationTemplates.SILENT_CATEGORIES) {
            assertTrue(
                "${category.name} must stay silent but has seed copy",
                DefaultNotificationTemplates.forCategory(category).isEmpty()
            )
            // And the composer must actually decline to post for them.
            val composed = NotificationComposer.compose(
                category = category,
                personalization = NotificationPersonalization(userName = sampleName),
                templates = DefaultNotificationTemplates.SEED,
                seed = 1L,
                lastTemplateId = null
            )
            assertTrue("${category.name} composed a notification anyway", composed == null)
        }
    }

    @Test
    fun `copy does not shout`() {
        for (template in DefaultNotificationTemplates.SEED.templates) {
            val text = template.title + " " + template.body
            assertTrue(
                "template '${template.id}' uses more than one exclamation mark",
                text.count { it == '!' } <= 1
            )
            val letters = text.filter { it.isLetter() }
            val shouted = letters.isNotEmpty() && letters.all { it.isUpperCase() }
            assertTrue("template '${template.id}' is in all caps", !shouted)
        }
    }

    @Test
    fun `copy never sounds like a system message or a bank`() {
        val systemy = listOf(
            "error", "failure", "invalid", "request failed",
            "dear customer", "dear valued", "please be advised", "kindly note",
            "transaction reference", "terms and conditions apply"
        )
        for (template in DefaultNotificationTemplates.SEED.templates) {
            val text = (template.title + " " + template.body).lowercase()
            for (word in systemy) {
                assertTrue(
                    "template '${template.id}' sounds like a system message ('$word')",
                    !text.contains(word)
                )
            }
        }
    }

    // ----- Honesty (CLAUDE.md §7 / §8) --------------------------------------

    @Test
    fun `no seed template contains a banned claim`() {
        for (template in DefaultNotificationTemplates.SEED.templates) {
            val text = (template.title + " " + template.body).lowercase()
            for (banned in DefaultNotificationTemplates.BANNED_PHRASES) {
                assertTrue(
                    "template '${template.id}' contains banned phrase '$banned'",
                    !text.contains(banned)
                )
            }
        }
    }

    @Test
    fun `only balance-driven categories mention a balance`() {
        for (template in DefaultNotificationTemplates.SEED.templates) {
            if (!template.title.contains("{balance}") && !template.body.contains("{balance}")) continue
            val category = NotificationCategory.fromName(template.category)
            assertTrue(
                "template '${template.id}' uses {balance} on a non-balance-driven category",
                category != null && category.isBalanceDriven
            )
        }
    }

    @Test
    fun `payment copy talks about the payment, never a delivered bundle`() {
        // PURCHASE_SUCCESS fires when money settles. The app cannot see the bundle
        // arrive, so it may say the payment went through and may say the bundle is
        // COMING — but never that it is already here.
        val arrived = listOf(
            "bundle is ready", "bundle has arrived", "bundle just arrived",
            "bundle landed", "back online", "enjoy your bundle", "you're good to go"
        )
        for (template in DefaultNotificationTemplates.forCategory(NotificationCategory.PURCHASE_SUCCESS)) {
            val text = (template.title + " " + template.body).lowercase()
            for (claim in arrived) {
                assertTrue(
                    "purchase template '${template.id}' claims the bundle arrived ('$claim')",
                    !text.contains(claim)
                )
            }
        }
    }

    @Test
    fun `bundle-arrival copy never credits My Bingwa for the delivery`() {
        // BUNDLE_RECEIVED only ever fires from a real Safaricom SMS, so it may say
        // the bundle arrived — but My Bingwa must never take credit for delivering
        // it, and some of the copy should name the actual source.
        val templates = DefaultNotificationTemplates.forCategory(NotificationCategory.BUNDLE_RECEIVED)
        for (template in templates) {
            val text = (template.title + " " + template.body).lowercase()
            assertTrue(
                "template '${template.id}' claims My Bingwa delivered the bundle",
                !text.contains("we delivered") && !text.contains("my bingwa delivered")
            )
        }
        assertTrue(
            "at least two BUNDLE_RECEIVED templates should credit Safaricom",
            templates.count { it.body.contains("Safaricom") } >= 2
        )
    }

    // ----- Structural integrity ---------------------------------------------

    @Test
    fun `template ids are unique`() {
        val ids = DefaultNotificationTemplates.SEED.templates.map { it.id }
        assertEquals(ids.size, ids.toSet().size)
    }

    @Test
    fun `every seed template names a category this build knows`() {
        for (template in DefaultNotificationTemplates.SEED.templates) {
            assertTrue(
                "template '${template.id}' has unknown category '${template.category}'",
                NotificationCategory.fromName(template.category) != null
            )
            assertTrue("template '${template.id}' has no body", template.body.isNotBlank())
            assertTrue("template '${template.id}' has no title", template.title.isNotBlank())
            assertTrue("template '${template.id}' has no id", template.id.isNotBlank())
        }
    }

    @Test
    fun `seed version is the documented one`() {
        assertEquals(DefaultNotificationTemplates.SEED_VERSION, DefaultNotificationTemplates.SEED.version)
    }
}
