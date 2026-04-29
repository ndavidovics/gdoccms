package app.mutualoffline.data

import app.mutualoffline.api.ApiService
import app.mutualoffline.api.HeartbeatRequest
import app.mutualoffline.api.JoinSessionRequest
import app.mutualoffline.api.OfflineSession
import app.mutualoffline.api.SessionResponse
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

class SessionRepository(
    private val api: ApiService,
) {

    private val _current = MutableStateFlow<OfflineSession?>(null)
    val current: StateFlow<OfflineSession?> = _current

    private val _lastJoinPayload = MutableStateFlow<String?>(null)
    val lastJoinPayload: StateFlow<String?> = _lastJoinPayload

    suspend fun create(): SessionResponse {
        val response = api.createSession()
        _current.value = response.session
        _lastJoinPayload.value = response.joinQrPayload
        return response
    }

    suspend fun join(joinToken: String): OfflineSession {
        val response = api.joinSession(JoinSessionRequest(joinToken = joinToken))
        _current.value = response.session
        return response.session
    }

    suspend fun confirmLock(uuid: String): OfflineSession {
        val response = api.confirmLock(uuid)
        _current.value = response.session
        return response.session
    }

    suspend fun heartbeat(uuid: String, payload: HeartbeatRequest): OfflineSession {
        val response = api.heartbeat(uuid, payload)
        _current.value = response.session
        return response.session
    }

    suspend fun requestEnd(uuid: String): OfflineSession {
        val response = api.requestEnd(uuid)
        _current.value = response.session
        return response.session
    }

    suspend fun confirmEnd(uuid: String): OfflineSession {
        val response = api.confirmEnd(uuid)
        _current.value = response.session
        return response.session
    }

    suspend fun emergencyExit(uuid: String): OfflineSession {
        val response = api.emergencyExit(uuid)
        _current.value = response.session
        return response.session
    }

    suspend fun refresh(uuid: String): OfflineSession {
        val response = api.getSession(uuid)
        _current.value = response.session
        return response.session
    }

    fun clear() {
        _current.value = null
        _lastJoinPayload.value = null
    }
}
