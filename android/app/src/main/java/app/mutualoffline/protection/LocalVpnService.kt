package app.mutualoffline.protection

/*
 * V1 STUB.
 *
 * This VpnService subclass establishes a real VPN tunnel so that
 * `isVpnActive()` reflects reality: when the user accepts the system
 * VPN consent dialog and we call Builder().establish(), Android marks
 * a VPN as active and routes traffic into our tunnel file descriptor.
 *
 * In V1 we simply read every packet from the tunnel and discard it,
 * which has the effect of dropping all outbound IP traffic.
 *
 * Full per-app or per-domain filtering (allow phone calls but block
 * social apps; allow specific safe-list domains; etc.) is FUTURE WORK
 * and intentionally not implemented here. We rely on the dropped-tunnel
 * behaviour as a coarse "network off" switch and surface the state via
 * the heartbeat to the partner.
 */

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.net.VpnService
import android.os.Build
import android.os.ParcelFileDescriptor
import androidx.core.app.NotificationCompat
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import java.io.FileInputStream
import java.nio.ByteBuffer
import java.util.concurrent.atomic.AtomicBoolean

class LocalVpnService : VpnService() {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var tunnelInterface: ParcelFileDescriptor? = null
    private var readerJob: Job? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            stopVpn()
            stopSelf()
            return START_NOT_STICKY
        }
        if (running.compareAndSet(false, true)) {
            startVpn()
        }
        return START_STICKY
    }

    private fun startVpn() {
        val builder = Builder()
            .setSession(SESSION_NAME)
            .addAddress(LOCAL_TUNNEL_ADDRESS, 32)
            .addRoute("0.0.0.0", 0)
            .setMtu(1500)

        val pfd = builder.establish()
        if (pfd == null) {
            running.set(false)
            stopSelf()
            return
        }
        tunnelInterface = pfd
        startForegroundIfNeeded()

        readerJob = scope.launch {
            val input = FileInputStream(pfd.fileDescriptor)
            val buffer = ByteBuffer.allocate(32 * 1024)
            try {
                while (isActive) {
                    val read = input.read(buffer.array())
                    if (read <= 0) {
                        // Either no traffic or interface closed; loop and retry.
                        continue
                    }
                    // V1: we deliberately drop everything we read.
                    buffer.clear()
                }
            } catch (_: Throwable) {
                // Interface closed during teardown.
            }
        }
    }

    private fun stopVpn() {
        readerJob?.cancel()
        readerJob = null
        runCatching { tunnelInterface?.close() }
        tunnelInterface = null
        running.set(false)
        stopForeground(STOP_FOREGROUND_REMOVE)
    }

    private fun startForegroundIfNeeded() {
        val nm = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Mutual Offline VPN",
                NotificationManager.IMPORTANCE_LOW,
            )
            nm.createNotificationChannel(channel)
        }
        val pi = PendingIntent.getActivity(
            this,
            0,
            packageManager.getLaunchIntentForPackage(packageName),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )
        val notification: Notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.stat_sys_vpn_ic)
            .setContentTitle("MutualOffline session active")
            .setContentText("Network is being tunneled and dropped.")
            .setOngoing(true)
            .setContentIntent(pi)
            .build()
        startForeground(NOTIFICATION_ID, notification)
    }

    override fun onRevoke() {
        stopVpn()
        super.onRevoke()
    }

    override fun onDestroy() {
        stopVpn()
        scope.cancel()
        super.onDestroy()
    }

    companion object {
        private const val SESSION_NAME = "MutualOffline"
        private const val LOCAL_TUNNEL_ADDRESS = "10.0.0.2"
        private const val CHANNEL_ID = "mutual_offline_vpn"
        private const val NOTIFICATION_ID = 1011

        const val ACTION_STOP = "app.mutualoffline.protection.STOP_VPN"

        // Process-wide flag so callers can check "is the tunnel up?" cheaply.
        private val running = AtomicBoolean(false)

        fun isRunning(): Boolean = running.get()
    }
}
