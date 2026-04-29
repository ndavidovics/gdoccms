package app.mutualoffline.ui.session

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import app.mutualoffline.data.SessionRepository
import app.mutualoffline.protection.ProtectionService
import app.mutualoffline.session.HeartbeatForegroundService
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@Composable
fun ReadyToLockScreen(
    sessionUuid: String,
    sessionRepository: SessionRepository,
    protectionService: ProtectionService,
    onActive: () -> Unit,
    onCancel: () -> Unit,
) {
    val current by sessionRepository.current.collectAsState()
    val scope = rememberCoroutineScope()
    val context = LocalContext.current

    var locking by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(sessionUuid) {
        while (true) {
            delay(2_000L)
            runCatching { sessionRepository.refresh(sessionUuid) }
                .onFailure { error = it.message }
            if (sessionRepository.current.value?.state == "active") {
                protectionService.startProtection(sessionUuid)
                HeartbeatForegroundService.start(context, sessionUuid)
                onActive()
                break
            }
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("Ready to lock")
        Text("State: ${current?.state ?: "..."}")
        Button(
            enabled = !locking,
            onClick = {
                locking = true
                scope.launch {
                    runCatching { sessionRepository.confirmLock(sessionUuid) }
                        .onFailure { error = it.message ?: "Lock failed" }
                    locking = false
                }
            },
        ) { Text(if (locking) "Confirming..." else "Confirm lock") }
        error?.let { Text("Error: $it") }
        TextButton(onClick = onCancel) { Text("Cancel") }
    }
}
