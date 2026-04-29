package app.mutualoffline.ui.session

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import app.mutualoffline.data.SessionRepository
import app.mutualoffline.protection.ProtectionService
import app.mutualoffline.session.HeartbeatForegroundService

@Composable
fun SessionResultScreen(
    sessionUuid: String,
    sessionRepository: SessionRepository,
    protectionService: ProtectionService,
    onHome: () -> Unit,
) {
    val session by sessionRepository.current.collectAsState()
    val context = LocalContext.current

    LaunchedEffect(sessionUuid) {
        runCatching { sessionRepository.refresh(sessionUuid) }
        protectionService.stopProtection(sessionUuid)
        HeartbeatForegroundService.stop(context)
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("Session ${session?.state ?: "ended"}")
        session?.failureReason?.let { Text("Reason: $it") }
        session?.startedAt?.let { Text("Started: $it") }
        session?.endedAt?.let { Text("Ended: $it") }
        Button(onClick = {
            sessionRepository.clear()
            onHome()
        }) { Text("Back to home") }
    }
}
