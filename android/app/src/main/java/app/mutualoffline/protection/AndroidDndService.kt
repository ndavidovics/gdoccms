package app.mutualoffline.protection

import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.provider.Settings
import app.mutualoffline.auth.SecurePrefs

/**
 * Wraps NotificationManager.setInterruptionFilter so we can flip the device
 * into Do-Not-Disturb during a session and restore the prior state when the
 * session ends.
 */
class AndroidDndService(
    private val context: Context,
    private val prefs: SecurePrefs,
) {

    private val nm: NotificationManager
        get() = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

    fun isPolicyAccessGranted(): Boolean = nm.isNotificationPolicyAccessGranted

    fun isEnabled(): Boolean {
        if (!isPolicyAccessGranted()) return false
        return nm.currentInterruptionFilter == NotificationManager.INTERRUPTION_FILTER_NONE ||
            nm.currentInterruptionFilter == NotificationManager.INTERRUPTION_FILTER_PRIORITY ||
            nm.currentInterruptionFilter == NotificationManager.INTERRUPTION_FILTER_ALARMS
    }

    /** Opens the system DND policy access settings page. */
    fun requestPolicyAccess(ctx: Context = context) {
        val intent = Intent(Settings.ACTION_NOTIFICATION_POLICY_ACCESS_SETTINGS)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        ctx.startActivity(intent)
    }

    /** Snapshots the current filter, then sets the device to "no interruptions". */
    fun enable() {
        if (!isPolicyAccessGranted()) return
        val current = nm.currentInterruptionFilter
        if (prefs.priorInterruptionFilter == -1) {
            prefs.priorInterruptionFilter = current
        }
        nm.setInterruptionFilter(NotificationManager.INTERRUPTION_FILTER_NONE)
    }

    /** Restores whatever filter was active before [enable]. */
    fun restore() {
        if (!isPolicyAccessGranted()) return
        val prior = prefs.priorInterruptionFilter
        if (prior >= 0) {
            nm.setInterruptionFilter(prior)
            prefs.priorInterruptionFilter = -1
        } else {
            nm.setInterruptionFilter(NotificationManager.INTERRUPTION_FILTER_ALL)
        }
    }
}
