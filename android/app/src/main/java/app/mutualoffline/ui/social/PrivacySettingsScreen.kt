package app.mutualoffline.ui.social

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import app.mutualoffline.data.SocialRepository
import kotlinx.coroutines.launch

@Composable
fun PrivacySettingsScreen(
    socialRepository: SocialRepository,
    onBack: () -> Unit,
) {
    var shareSuccesses by remember { mutableStateOf(true) }
    var shareFailures by remember { mutableStateOf(false) }
    var shareStreaks by remember { mutableStateOf(true) }
    var discoverableByContacts by remember { mutableStateOf(true) }
    var status by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Privacy")
        ToggleRow("Share successes", shareSuccesses) { shareSuccesses = it }
        ToggleRow("Share failures", shareFailures) { shareFailures = it }
        ToggleRow("Share streaks", shareStreaks) { shareStreaks = it }
        ToggleRow("Discoverable by contacts", discoverableByContacts) {
            discoverableByContacts = it
        }
        Button(onClick = {
            scope.launch {
                runCatching {
                    socialRepository.updatePrivacy(
                        shareSuccesses = shareSuccesses,
                        shareFailures = shareFailures,
                        shareStreaks = shareStreaks,
                        discoverableByContacts = discoverableByContacts,
                    )
                }.onSuccess { status = "Saved" }
                    .onFailure { status = it.message ?: "Failed" }
            }
        }) { Text("Save") }
        status?.let { Text(it) }
        TextButton(onClick = onBack) { Text("Back") }
    }
}

@Composable
private fun ToggleRow(label: String, value: Boolean, onChange: (Boolean) -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(label)
        Switch(checked = value, onCheckedChange = onChange)
    }
}
