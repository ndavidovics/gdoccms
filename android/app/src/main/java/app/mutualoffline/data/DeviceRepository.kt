package app.mutualoffline.data

import android.content.Context
import android.os.Build
import app.mutualoffline.BuildConfig
import app.mutualoffline.api.ApiService
import app.mutualoffline.api.Device
import app.mutualoffline.api.DeviceRegisterRequest
import app.mutualoffline.auth.SecurePrefs
import java.util.UUID

class DeviceRepository(
    private val context: Context,
    private val api: ApiService,
    private val prefs: SecurePrefs,
) {

    /** Returns the persistent client-side UUID, generating + storing one on first launch. */
    fun ensureDeviceUuid(): String {
        val existing = prefs.deviceUuid
        if (!existing.isNullOrBlank()) return existing
        val fresh = UUID.randomUUID().toString()
        prefs.deviceUuid = fresh
        return fresh
    }

    suspend fun registerCurrentDevice(): Device {
        val uuid = ensureDeviceUuid()
        val deviceName = "${Build.MANUFACTURER} ${Build.MODEL}".trim()
        val response = api.registerDevice(
            DeviceRegisterRequest(
                deviceUuid = uuid,
                platform = "android",
                deviceName = deviceName,
                appVersion = BuildConfig::class.java
                    .runCatching { getDeclaredField("VERSION_NAME").get(null) as String }
                    .getOrDefault("1.0.0"),
                pushToken = null,
            )
        )
        return response.device
    }

    suspend fun fetchCurrent(): Device = api.currentDevice().device

    suspend fun revoke() {
        api.revokeDevice()
    }
}
