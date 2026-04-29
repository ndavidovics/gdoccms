package app.mutualoffline.auth

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * EncryptedSharedPreferences wrapper for the small amount of sensitive
 * client state we need to keep around: the auth token, the device UUID,
 * and a snapshot of prior DND state so we can restore it after a session.
 */
class SecurePrefs(context: Context) {

    private val prefs: SharedPreferences = run {
        val masterKey = MasterKey.Builder(context.applicationContext)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()
        EncryptedSharedPreferences.create(
            context.applicationContext,
            FILE_NAME,
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    }

    var token: String?
        get() = prefs.getString(KEY_TOKEN, null)
        set(value) {
            prefs.edit().apply {
                if (value == null) remove(KEY_TOKEN) else putString(KEY_TOKEN, value)
            }.apply()
        }

    var userId: Long
        get() = prefs.getLong(KEY_USER_ID, 0L)
        set(value) {
            prefs.edit().putLong(KEY_USER_ID, value).apply()
        }

    var userName: String?
        get() = prefs.getString(KEY_USER_NAME, null)
        set(value) {
            prefs.edit().putString(KEY_USER_NAME, value).apply()
        }

    var deviceUuid: String?
        get() = prefs.getString(KEY_DEVICE_UUID, null)
        set(value) {
            prefs.edit().putString(KEY_DEVICE_UUID, value).apply()
        }

    /**
     * Stores the prior NotificationManager interruption filter as an Int
     * so we can restore it after a session ends. -1 means "not stored".
     */
    var priorInterruptionFilter: Int
        get() = prefs.getInt(KEY_PRIOR_DND, -1)
        set(value) {
            prefs.edit().putInt(KEY_PRIOR_DND, value).apply()
        }

    fun clearAuth() {
        prefs.edit()
            .remove(KEY_TOKEN)
            .remove(KEY_USER_ID)
            .remove(KEY_USER_NAME)
            .apply()
    }

    companion object {
        private const val FILE_NAME = "mutual_offline_secure_prefs"
        private const val KEY_TOKEN = "token"
        private const val KEY_USER_ID = "user_id"
        private const val KEY_USER_NAME = "user_name"
        private const val KEY_DEVICE_UUID = "device_uuid"
        private const val KEY_PRIOR_DND = "prior_dnd"
    }
}
