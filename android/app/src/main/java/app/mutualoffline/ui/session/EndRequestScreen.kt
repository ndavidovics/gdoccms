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
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import app.mutualoffline.data.SessionRepository
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@Composable
fun EndRequestScreen(
    sessionUuid: String,
    sessionRepository: SessionRepository,
    onResult: () -> Unit,
) {
    val session by sessionRepository.current.collectAsState()
    val scope = rememberCoroutineScope()
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(sessionUuid) {
        while (true) {
            delay(1_500L)
            runCatching { sessionRepository.refresh(sessionUuid) }
                .onFailure { error = it.message }
            val state = sessionRepository.current.value?.state
            if (state == "success" || state == "failed" || state == "cancelled") {
                onResult()
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
        Text("End requested — waiting for partner to confirm.")
        Text("State: ${session?.state ?: "..."}")
        Button(onClick = {
            scope.launch {
                runCatching { sessionRepository.confirmEnd(sessionUuid) }
            }
        }) { Text("Confirm end") }
        error?.let { Text("Error: $it") }
    }
}
