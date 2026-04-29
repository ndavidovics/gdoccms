package app.mutualoffline

import android.content.Intent
import android.net.VpnService
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import app.mutualoffline.ui.MutualOfflineApp
import app.mutualoffline.ui.theme.MutualOfflineTheme

class MainActivity : ComponentActivity() {

    private val vpnPrepareLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { /* result is delivered via the standard flow; nothing to do here. */ }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MutualOfflineTheme {
                MutualOfflineApp(
                    onRequestVpnPermission = { intent: Intent? ->
                        if (intent != null) vpnPrepareLauncher.launch(intent)
                    },
                    prepareVpn = { VpnService.prepare(this) },
                )
            }
        }
    }
}
