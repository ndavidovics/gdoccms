package app.mutualoffline.protection

import android.content.Context
import android.content.Intent
import android.net.VpnService
import androidx.activity.ComponentActivity
import androidx.activity.result.contract.ActivityResultContracts
import app.mutualoffline.api.IntegrityState
import kotlinx.coroutines.CompletableDeferred

/**
 * Default Android implementation of [ProtectionService]. Coordinates the
 * local VPN service and the DND wrapper. Does NOT promise the user that
 * these protections are unbypassable — they're a friction layer surfaced
 * to the partner via heartbeats.
 */
class AndroidVpnProtectionService(
    private val appContext: Context,
    private val dnd: AndroidDndService,
) : ProtectionService {

    override suspend fun requestPermissions(activity: ComponentActivity) {
        // VPN consent prompt — only shown when the user has not previously
        // approved this app as a VPN provider.
        val intent = VpnService.prepare(activity)
        if (intent != null) {
            val deferred = CompletableDeferred<Unit>()
            val launcher = activity.activityResultRegistry.register(
                "vpn_prepare_${System.currentTimeMillis()}",
                ActivityResultContracts.StartActivityForResult(),
            ) { _ -> deferred.complete(Unit) }
            launcher.launch(intent)
            deferred.await()
            launcher.unregister()
        }
        // DND access cannot be granted programmatically — surface the system page.
        if (!dnd.isPolicyAccessGranted()) {
            dnd.requestPolicyAccess(activity)
        }
    }

    override suspend fun startProtection(sessionId: String) {
        if (VpnService.prepare(appContext) == null) {
            // Already approved — start the local VPN service.
            val intent = Intent(appContext, LocalVpnService::class.java)
            appContext.startService(intent)
        }
        if (dnd.isPolicyAccessGranted()) {
            dnd.enable()
        }
    }

    override suspend fun stopProtection(sessionId: String) {
        val stopVpn = Intent(appContext, LocalVpnService::class.java).apply {
            action = LocalVpnService.ACTION_STOP
        }
        runCatching { appContext.startService(stopVpn) }
        runCatching { dnd.restore() }
    }

    override fun currentIntegrityState(): IntegrityState {
        val vpnUp = LocalVpnService.isRunning()
        val dndOn = dnd.isEnabled()
        // V1: network_block_active is equivalent to vpn_active because the
        // local tunnel drops everything. When future per-domain filtering
        // arrives these will diverge.
        return IntegrityState(
            vpnActive = vpnUp,
            dndActive = dndOn,
            notificationSuppressionActive = dndOn,
            networkBlockActive = vpnUp,
        )
    }
}
