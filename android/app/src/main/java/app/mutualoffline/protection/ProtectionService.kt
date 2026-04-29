package app.mutualoffline.protection

import androidx.activity.ComponentActivity
import app.mutualoffline.api.IntegrityState

/**
 * Coordinates the platform protections that make a "mutual offline" session
 * meaningful: blocking network traffic via a local VPN, suppressing
 * notifications via DND, etc.
 *
 * NOTE: None of this is impossible to bypass. A determined user can disable
 * the VPN, force-stop the app, swap SIMs, or use another device. The point
 * of these protections is to add friction and make accidental drift visible
 * to the partner via heartbeat integrity reports.
 */
interface ProtectionService {

    /** Triggers any one-time consent prompts (VPN, DND access). */
    suspend fun requestPermissions(activity: ComponentActivity)

    /** Starts the local VPN tunnel + DND for the given session uuid. */
    suspend fun startProtection(sessionId: String)

    /** Stops the local VPN tunnel and restores prior DND state. */
    suspend fun stopProtection(sessionId: String)

    /** Returns the current integrity snapshot for inclusion in a heartbeat. */
    fun currentIntegrityState(): IntegrityState
}
