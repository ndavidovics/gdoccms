package app.mutualoffline.session

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import app.mutualoffline.MutualOfflineApplication
import app.mutualoffline.api.HeartbeatRequest
import app.mutualoffline.auth.SecurePrefs
import app.mutualoffline.data.SessionRepository
import app.mutualoffline.protection.ProtectionService
import app.mutualoffline.util.Formatters
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

/**
 * Long-running foreground service that fires a heartbeat once every 10
 * seconds for the duration of an active session. The notification has a
 * stop action that emergency-exits the session.
 */
class HeartbeatForegroundService : Service() {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Default)
    private var heartbeatJob: Job? = null
    private var sessionUuid: String? = null

    private lateinit var sessionRepo: SessionRepository
    private lateinit var protection: ProtectionService
    private lateinit var prefs: SecurePrefs
    private lateinit var appVersion: String

    override fun onCreate() {
        super.onCreate()
        val app = applicationContext as MutualOfflineApplication
        sessionRepo = app.sessionRepository
        protection = app.protectionService
        prefs = app.securePrefs
        appVersion = runCatching {
            packageManager.getPackageInfo(packageName, 0).versionName ?: "1.0.0"
        }.getOrDefault("1.0.0")
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_STOP -> {
                stopHeartbeat()
                stopSelf()
                return START_NOT_STICKY
            }
        }
        val uuid = intent?.getStringExtra(EXTRA_SESSION_UUID)
        if (uuid.isNullOrBlank()) {
            stopSelf()
            return START_NOT_STICKY
        }
        sessionUuid = uuid
        startForeground(NOTIFICATION_ID, buildNotification(uuid))
        startHeartbeat(uuid)
        return START_STICKY
    }

    private fun startHeartbeat(uuid: String) {
        heartbeatJob?.cancel()
        heartbeatJob = scope.launch {
            while (isActive) {
                runCatching {
                    val state = protection.currentIntegrityState()
                    val deviceUuid = prefs.deviceUuid ?: return@runCatching
                    val payload = HeartbeatRequest(
                        deviceUuid = deviceUuid,
                        vpnActive = state.vpnActive,
                        dndActive = state.dndActive,
                        notificationSuppressionActive = state.notificationSuppressionActive,
                        networkBlockActive = state.networkBlockActive,
                        appVersion = appVersion,
                        platform = "android",
                        clientTimestamp = Formatters.isoNow(),
                    )
                    sessionRepo.heartbeat(uuid, payload)
                }
                delay(HEARTBEAT_INTERVAL_MS)
            }
        }
    }

    private fun stopHeartbeat() {
        heartbeatJob?.cancel()
        heartbeatJob = null
        sessionUuid = null
    }

    override fun onDestroy() {
        stopHeartbeat()
        scope.cancel()
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun buildNotification(uuid: String): Notification {
        val nm = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "JustYouTime Heartbeat",
                NotificationManager.IMPORTANCE_LOW,
            )
            nm.createNotificationChannel(channel)
        }

        val openAppPi = PendingIntent.getActivity(
            this,
            0,
            packageManager.getLaunchIntentForPackage(packageName),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )
        val stopIntent = Intent(this, HeartbeatForegroundService::class.java).apply {
            action = ACTION_STOP
        }
        val stopPi = PendingIntent.getService(
            this,
            1,
            stopIntent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_lock_lock)
            .setContentTitle("Offline session in progress")
            .setContentText("Tap to view. Stop will end the session early.")
            .setContentIntent(openAppPi)
            .addAction(
                android.R.drawable.ic_menu_close_clear_cancel,
                "Stop",
                stopPi,
            )
            .setOngoing(true)
            .build()
    }

    companion object {
        const val EXTRA_SESSION_UUID = "session_uuid"
        const val ACTION_STOP = "app.mutualoffline.session.STOP_HEARTBEAT"
        private const val CHANNEL_ID = "mutual_offline_heartbeat"
        private const val NOTIFICATION_ID = 2022
        private const val HEARTBEAT_INTERVAL_MS = 10_000L

        fun start(context: Context, sessionUuid: String) {
            val intent = Intent(context, HeartbeatForegroundService::class.java).apply {
                putExtra(EXTRA_SESSION_UUID, sessionUuid)
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent)
            } else {
                context.startService(intent)
            }
        }

        fun stop(context: Context) {
            val intent = Intent(context, HeartbeatForegroundService::class.java).apply {
                action = ACTION_STOP
            }
            runCatching { context.startService(intent) }
        }
    }
}
