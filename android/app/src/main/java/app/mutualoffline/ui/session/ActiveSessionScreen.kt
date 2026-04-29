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
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import app.mutualoffline.data.SessionRepository
import app.mutualoffline.util.Formatters
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.time.Instant

@Composable
fun ActiveSessionScreen(
    sessionUuid: String,
    sessionRepository: SessionRepository,
    onRequestEnd: () -> Unit,
    onResult: () -> Unit,
) {
    val session by sessionRepository.current.collectAsState()
    val scope = rememberCoroutineScope()
    var nowSeconds by remember { mutableLongStateOf(Instant.now().epochSecond) }

    LaunchedEffect(sessionUuid) {
        while (true) {
            nowSeconds = Instant.now().epochSecond
            delay(1_000L)
            runCatching { sessionRepository.refresh(sessionUuid) }
            val state = sessionRepository.current.value?.state
            if (state == "success" || state == "failed" || state == "cancelled") {
                onResult()
                break
            }
        }
    }

    val startedAt = Formatters.parseIsoToEpochSeconds(session?.startedAt)
    val elapsed = if (startedAt != null) nowSeconds - startedAt else 0L

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("Session active")
        Text("Elapsed: ${Formatters.durationHms(elapsed)}")
        Text("State: ${session?.state ?: "..."}")
        Button(onClick = {
            scope.launch {
                runCatching { sessionRepository.requestEnd(sessionUuid) }
                onRequestEnd()
            }
        }) { Text("Request end") }
        Button(onClick = {
            scope.launch {
                runCatching { sessionRepository.emergencyExit(sessionUuid) }
                onResult()
            }
        }) { Text("Emergency exit") }
    }
}
