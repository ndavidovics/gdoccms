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
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import app.mutualoffline.data.SessionRepository
import kotlinx.coroutines.launch

@Composable
fun CreateSessionScreen(
    sessionRepository: SessionRepository,
    onSessionCreated: (String) -> Unit,
    onBack: () -> Unit,
) {
    val scope = rememberCoroutineScope()
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        if (loading) return@LaunchedEffect
        loading = true
        runCatching { sessionRepository.create() }
            .onSuccess { onSessionCreated(it.session.uuid) }
            .onFailure { error = it.message ?: "Could not create session" }
        loading = false
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Creating session...")
        error?.let {
            Text("Error: $it")
            Button(onClick = {
                scope.launch {
                    loading = true
                    runCatching { sessionRepository.create() }
                        .onSuccess { resp -> onSessionCreated(resp.session.uuid) }
                        .onFailure { e -> error = e.message ?: "Failed" }
                    loading = false
                }
            }) { Text("Retry") }
        }
        TextButton(onClick = onBack) { Text("Cancel") }
    }
}
