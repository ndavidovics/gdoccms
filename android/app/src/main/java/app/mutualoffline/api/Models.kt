package app.mutualoffline.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class User(
    val id: Long,
    val name: String,
    val email: String,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
)

@Serializable
data class UserStats(
    @SerialName("user_id") val userId: Long,
    @SerialName("total_sessions") val totalSessions: Int = 0,
    @SerialName("successful_sessions") val successfulSessions: Int = 0,
    @SerialName("failed_sessions") val failedSessions: Int = 0,
    @SerialName("total_offline_seconds") val totalOfflineSeconds: Long = 0L,
    @SerialName("current_streak") val currentStreak: Int = 0,
    @SerialName("longest_streak") val longestStreak: Int = 0,
    @SerialName("last_session_at") val lastSessionAt: String? = null,
)

@Serializable
data class Device(
    val id: Long,
    @SerialName("user_id") val userId: Long,
    @SerialName("device_uuid") val deviceUuid: String,
    val platform: String,
    @SerialName("device_name") val deviceName: String? = null,
    @SerialName("app_version") val appVersion: String? = null,
    @SerialName("push_token") val pushToken: String? = null,
    @SerialName("is_active") val isActive: Boolean = true,
    @SerialName("last_seen_at") val lastSeenAt: String? = null,
)

@Serializable
data class SessionParticipant(
    val id: Long,
    @SerialName("session_id") val sessionId: Long,
    @SerialName("user_id") val userId: Long,
    val role: String,
    @SerialName("device_uuid") val deviceUuid: String? = null,
    @SerialName("lock_confirmed_at") val lockConfirmedAt: String? = null,
    @SerialName("end_requested_at") val endRequestedAt: String? = null,
    @SerialName("end_confirmed_at") val endConfirmedAt: String? = null,
    val user: User? = null,
)

@Serializable
data class OfflineSession(
    val id: Long,
    val uuid: String,
    val state: String,
    @SerialName("host_user_id") val hostUserId: Long,
    @SerialName("started_at") val startedAt: String? = null,
    @SerialName("ended_at") val endedAt: String? = null,
    @SerialName("planned_duration_seconds") val plannedDurationSeconds: Long? = null,
    @SerialName("failure_reason") val failureReason: String? = null,
    @SerialName("failed_by_user_id") val failedByUserId: Long? = null,
    val participants: List<SessionParticipant> = emptyList(),
)

@Serializable
data class FeedItem(
    val id: Long,
    @SerialName("user_id") val userId: Long,
    @SerialName("session_id") val sessionId: Long? = null,
    val kind: String,
    val visibility: String,
    val payload: Map<String, String> = emptyMap(),
    @SerialName("created_at") val createdAt: String? = null,
    val user: User? = null,
)

@Serializable
data class Friendship(
    val id: Long,
    @SerialName("requester_id") val requesterId: Long,
    @SerialName("recipient_id") val recipientId: Long,
    val state: String,
    @SerialName("created_at") val createdAt: String? = null,
    val requester: User? = null,
    val recipient: User? = null,
)

@Serializable
data class IntegrityState(
    @SerialName("vpn_active") val vpnActive: Boolean,
    @SerialName("dnd_active") val dndActive: Boolean,
    @SerialName("notification_suppression_active") val notificationSuppressionActive: Boolean,
    @SerialName("network_block_active") val networkBlockActive: Boolean,
)

// ----- Request bodies -----

@Serializable
data class RegisterRequest(
    val name: String,
    val email: String,
    val password: String,
    @SerialName("password_confirmation") val passwordConfirmation: String,
)

@Serializable
data class LoginRequest(
    val email: String,
    val password: String,
)

@Serializable
data class DeviceRegisterRequest(
    @SerialName("device_uuid") val deviceUuid: String,
    val platform: String,
    @SerialName("device_name") val deviceName: String,
    @SerialName("app_version") val appVersion: String,
    @SerialName("push_token") val pushToken: String? = null,
)

@Serializable
data class JoinSessionRequest(
    @SerialName("join_token") val joinToken: String,
)

@Serializable
data class HeartbeatRequest(
    @SerialName("device_uuid") val deviceUuid: String,
    @SerialName("vpn_active") val vpnActive: Boolean,
    @SerialName("dnd_active") val dndActive: Boolean,
    @SerialName("notification_suppression_active") val notificationSuppressionActive: Boolean,
    @SerialName("network_block_active") val networkBlockActive: Boolean,
    @SerialName("app_version") val appVersion: String,
    val platform: String = "android",
    @SerialName("client_timestamp") val clientTimestamp: String,
)

@Serializable
data class ContactsSyncRequest(
    val hashes: List<String>,
)

@Serializable
data class FriendRequestBody(
    @SerialName("user_id") val userId: Long,
)

@Serializable
data class PrivacyRequest(
    @SerialName("share_successes") val shareSuccesses: Boolean,
    @SerialName("share_failures") val shareFailures: Boolean,
    @SerialName("share_streaks") val shareStreaks: Boolean,
    @SerialName("discoverable_by_contacts") val discoverableByContacts: Boolean,
)

// ----- Responses -----

@Serializable
data class AuthResponse(
    val user: User,
    val token: String,
)

@Serializable
data class OkResponse(
    val ok: Boolean = true,
)

@Serializable
data class MeResponse(
    val user: User,
    val stats: UserStats,
)

@Serializable
data class DeviceResponse(
    val device: Device,
)

@Serializable
data class SessionResponse(
    val session: OfflineSession,
    @SerialName("join_token") val joinToken: String? = null,
    @SerialName("join_qr_payload") val joinQrPayload: String? = null,
)

@Serializable
data class JoinTokenResponse(
    @SerialName("join_token") val joinToken: String,
    @SerialName("join_qr_payload") val joinQrPayload: String,
)

@Serializable
data class ContactMatch(
    @SerialName("user_id") val userId: Long,
    val name: String,
    @SerialName("matched_hash") val matchedHash: String,
)

@Serializable
data class ContactsSyncResponse(
    val imported: Int,
    val matches: List<ContactMatch>,
)

@Serializable
data class ContactMatchesResponse(
    val matches: List<ContactMatch>,
)

@Serializable
data class FriendsListResponse(
    val accepted: List<Friendship> = emptyList(),
    @SerialName("pending_incoming") val pendingIncoming: List<Friendship> = emptyList(),
    @SerialName("pending_outgoing") val pendingOutgoing: List<Friendship> = emptyList(),
)

@Serializable
data class FriendshipResponse(
    val friendship: Friendship,
)

@Serializable
data class FeedResponse(
    val data: List<FeedItem> = emptyList(),
    @SerialName("next_cursor") val nextCursor: String? = null,
)

@Serializable
data class StatsResponse(
    val stats: UserStats,
)
