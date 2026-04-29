package app.mutualoffline.ui.permissions

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

@Composable
fun PermissionSetupScreen(
    onRequestVpnPermission: () -> Unit,
    onRequestDndPermission: () -> Unit,
    onContinue: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("Set up protections")
        Text(
            "JustYouTime uses a local VPN to drop network traffic during a " +
                "session and Do Not Disturb to silence notifications. None of " +
                "this is impossible to bypass — your partner sees the state via " +
                "heartbeats."
        )
        Button(onClick = onRequestVpnPermission) { Text("Grant VPN consent") }
        Button(onClick = onRequestDndPermission) { Text("Grant Do Not Disturb access") }
        Button(onClick = onContinue) { Text("Continue") }
    }
}
