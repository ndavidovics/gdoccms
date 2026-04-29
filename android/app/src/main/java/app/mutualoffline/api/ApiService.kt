package app.mutualoffline.api

import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

interface ApiService {

    // ----- Auth -----

    @POST("api/register")
    suspend fun register(@Body body: RegisterRequest): AuthResponse

    @POST("api/login")
    suspend fun login(@Body body: LoginRequest): AuthResponse

    @POST("api/logout")
    suspend fun logout(): OkResponse

    @GET("api/me")
    suspend fun me(): MeResponse

    // ----- Devices -----

    @POST("api/devices/register")
    suspend fun registerDevice(@Body body: DeviceRegisterRequest): DeviceResponse

    @GET("api/devices/current")
    suspend fun currentDevice(): DeviceResponse

    @POST("api/devices/revoke")
    suspend fun revokeDevice(): OkResponse

    // ----- Sessions -----

    @POST("api/sessions")
    suspend fun createSession(): SessionResponse

    @POST("api/sessions/join")
    suspend fun joinSession(@Body body: JoinSessionRequest): SessionResponse

    @POST("api/sessions/{uuid}/confirm-lock")
    suspend fun confirmLock(@Path("uuid") uuid: String): SessionResponse

    @POST("api/sessions/{uuid}/heartbeat")
    suspend fun heartbeat(
        @Path("uuid") uuid: String,
        @Body body: HeartbeatRequest,
    ): SessionResponse

    @POST("api/sessions/{uuid}/request-end")
    suspend fun requestEnd(@Path("uuid") uuid: String): SessionResponse

    @POST("api/sessions/{uuid}/confirm-end")
    suspend fun confirmEnd(@Path("uuid") uuid: String): SessionResponse

    @POST("api/sessions/{uuid}/emergency-exit")
    suspend fun emergencyExit(@Path("uuid") uuid: String): SessionResponse

    @GET("api/sessions/{uuid}")
    suspend fun getSession(@Path("uuid") uuid: String): SessionResponse

    @GET("api/profile/stats")
    suspend fun profileStats(): StatsResponse

    // ----- Social -----

    @POST("api/contacts/sync")
    suspend fun syncContacts(@Body body: ContactsSyncRequest): ContactsSyncResponse

    @GET("api/contacts/matches")
    suspend fun contactMatches(): ContactMatchesResponse

    @POST("api/friends/request")
    suspend fun requestFriend(@Body body: FriendRequestBody): FriendshipResponse

    @POST("api/friends/{id}/accept")
    suspend fun acceptFriend(@Path("id") id: Long): FriendshipResponse

    @POST("api/friends/{id}/reject")
    suspend fun rejectFriend(@Path("id") id: Long): FriendshipResponse

    @DELETE("api/friends/{id}")
    suspend fun removeFriend(@Path("id") id: Long): OkResponse

    @GET("api/friends")
    suspend fun listFriends(): FriendsListResponse

    @GET("api/feed")
    suspend fun feed(@Query("cursor") cursor: String? = null): FeedResponse

    @POST("api/profile/privacy")
    suspend fun updatePrivacy(@Body body: PrivacyRequest): OkResponse
}
