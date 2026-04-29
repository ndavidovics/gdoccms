package app.mutualoffline.auth

import app.mutualoffline.api.ApiService
import app.mutualoffline.api.LoginRequest
import app.mutualoffline.api.RegisterRequest
import app.mutualoffline.api.User
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

class AuthRepository(
    private val api: ApiService,
    private val prefs: SecurePrefs,
) {

    private val _currentUser = MutableStateFlow<User?>(restoreUserFromPrefs())
    val currentUser: StateFlow<User?> = _currentUser

    fun isAuthenticated(): Boolean = !prefs.token.isNullOrBlank()

    suspend fun register(name: String, email: String, password: String): User {
        val response = api.register(
            RegisterRequest(
                name = name,
                email = email,
                password = password,
                passwordConfirmation = password,
            )
        )
        persist(response.user, response.token)
        return response.user
    }

    suspend fun login(email: String, password: String): User {
        val response = api.login(LoginRequest(email = email, password = password))
        persist(response.user, response.token)
        return response.user
    }

    suspend fun logout() {
        runCatching { api.logout() }
        prefs.clearAuth()
        _currentUser.value = null
    }

    suspend fun refreshMe(): User {
        val me = api.me()
        prefs.userId = me.user.id
        prefs.userName = me.user.name
        _currentUser.value = me.user
        return me.user
    }

    private fun persist(user: User, token: String) {
        prefs.token = token
        prefs.userId = user.id
        prefs.userName = user.name
        _currentUser.value = user
    }

    private fun restoreUserFromPrefs(): User? {
        val id = prefs.userId
        val name = prefs.userName
        if (id == 0L || name.isNullOrBlank() || prefs.token.isNullOrBlank()) return null
        return User(id = id, name = name, email = "")
    }
}
