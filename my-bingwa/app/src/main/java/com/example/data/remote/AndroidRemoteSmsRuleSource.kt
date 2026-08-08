package com.example.data.remote

import com.example.core.sms.RemoteSmsRuleSource
import com.example.core.sms.SmsRule
import com.example.core.sms.SmsRuleSet
import com.squareup.moshi.Json
import retrofit2.http.GET

/**
 * Retrofit implementation of [RemoteSmsRuleSource] backed by `get_sms_rules.php`.
 *
 * SMS recognition rules let the owner fix Safaricom message matching (a changed sender
 * ID, a reworded confirmation) WITHOUT shipping an app update. The rules are evaluated
 * entirely on-device: the server never sees a message, a number or a balance.
 *
 * Any failure returns null, so the app keeps whatever rule set it already has — its
 * bundled seed on a fresh install, or the last synced set thereafter. The engine never
 * ends up with no rules because a request failed.
 */
class AndroidRemoteSmsRuleSource(
    baseUrl: String,
    appKey: String,
    enableLogging: Boolean = false
) : RemoteSmsRuleSource {

    private val api: SmsRulesApi? =
        RemoteHttp.createApi(baseUrl, appKey, enableLogging, SmsRulesApi::class.java)

    override suspend fun fetch(): SmsRuleSet? {
        val response = try {
            api?.getSmsRules() ?: return null
        } catch (t: Throwable) {
            return null
        }
        return response.toRuleSet()
    }
}

interface SmsRulesApi {
    @GET("get_sms_rules.php")
    suspend fun getSmsRules(): SmsRuleSetDto
}

data class SmsRuleSetDto(
    @Json(name = "version") val version: Int? = null,
    @Json(name = "updatedAt") val updatedAt: Long? = null,
    @Json(name = "rules") val rules: List<SmsRuleDto?>? = null
) {
    /**
     * An EMPTY published set is returned as an empty rule set, not as null: "the owner
     * published no rules" is a legitimate answer that differs from "the request failed".
     * Rules missing an id or a pattern are dropped — a rule with no pattern could never
     * match anything and would only cost parser time.
     */
    fun toRuleSet(): SmsRuleSet = SmsRuleSet(
        version = version ?: 0,
        updatedAt = updatedAt ?: 0L,
        rules = rules.orEmpty().mapNotNull { it?.toRule() }
    )
}

data class SmsRuleDto(
    @Json(name = "id") val id: String? = null,
    @Json(name = "name") val name: String? = null,
    @Json(name = "senderId") val senderId: String? = null,
    @Json(name = "pattern") val pattern: String? = null,
    @Json(name = "matchType") val matchType: String? = null,
    @Json(name = "eventTypes") val eventTypes: List<String?>? = null,
    @Json(name = "priority") val priority: Int? = null,
    @Json(name = "enabled") val enabled: Boolean? = null,
    @Json(name = "description") val description: String? = null
) {
    fun toRule(): SmsRule? {
        val safeId = id?.takeIf { it.isNotBlank() } ?: return null
        val safePattern = pattern?.takeIf { it.isNotBlank() } ?: return null
        return SmsRule(
            id = safeId,
            name = name.orEmpty(),
            senderId = senderId.orEmpty(),
            pattern = safePattern,
            // Normalised to upper case but NOT validated against a known set: an
            // unrecognised match type must be ignored by the parser, never crash it.
            matchType = matchType?.trim()?.uppercase()?.takeIf { it.isNotEmpty() } ?: "REGEX",
            eventTypes = eventTypes.orEmpty()
                .mapNotNull { it?.trim()?.uppercase() }
                .filter { it.isNotEmpty() },
            priority = priority ?: 100,
            enabled = enabled ?: true,
            description = description.orEmpty()
        )
    }
}
