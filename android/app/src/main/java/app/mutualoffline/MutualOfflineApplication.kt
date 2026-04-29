package app.mutualoffline

import android.app.Application
import app.mutualoffline.api.ApiClient
import app.mutualoffline.api.ApiService
import app.mutualoffline.auth.AuthRepository
import app.mutualoffline.auth.SecurePrefs
import app.mutualoffline.data.DeviceRepository
import app.mutualoffline.data.SessionRepository
import app.mutualoffline.data.SocialRepository
import app.mutualoffline.protection.AndroidDndService
import app.mutualoffline.protection.AndroidVpnProtectionService
import app.mutualoffline.protection.ProtectionService

/**
 * Tiny manual DI container. Real wiring would use Hilt or Koin; for V1 we
 * keep things explicit and allocator-cheap.
 */
class MutualOfflineApplication : Application() {

    lateinit var securePrefs: SecurePrefs
        private set
    lateinit var api: ApiService
        private set
    lateinit var authRepository: AuthRepository
        private set
    lateinit var deviceRepository: DeviceRepository
        private set
    lateinit var sessionRepository: SessionRepository
        private set
    lateinit var socialRepository: SocialRepository
        private set
    lateinit var dndService: AndroidDndService
        private set
    lateinit var protectionService: ProtectionService
        private set

    override fun onCreate() {
        super.onCreate()
        securePrefs = SecurePrefs(this)
        api = ApiClient.get(securePrefs)
        authRepository = AuthRepository(api, securePrefs)
        deviceRepository = DeviceRepository(this, api, securePrefs)
        sessionRepository = SessionRepository(api)
        socialRepository = SocialRepository(api)
        dndService = AndroidDndService(this, securePrefs)
        protectionService = AndroidVpnProtectionService(this, dndService)
    }
}
