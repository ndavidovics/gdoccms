package app.mutualoffline.ui.session

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import app.mutualoffline.api.UserStats
import app.mutualoffline.data.SocialRepository
import app.mutualoffline.util.Formatters

@Composable
fun ProfileStatsScreen(
    socialRepository: SocialRepository,
    onBack: () -> Unit,
) {
    var stats by remember { mutableStateOf<UserStats?>(null) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        runCatching { socialRepository.fetchStats() }
            .onSuccess { stats = it }
            .onFailure { error = it.message ?: "Could not load stats" }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Text("Profile / stats")
        stats?.let { s ->
            Text("Total sessions: ${s.totalSessions}")
            Text("Successful: ${s.successfulSessions}")
            Text("Failed: ${s.failedSessions}")
            Text("Time offline: ${Formatters.durationHms(s.totalOfflineSeconds)}")
            Text("Current streak: ${s.currentStreak}")
            Text("Longest streak: ${s.longestStreak}")
        }
        error?.let { Text("Error: $it") }
        TextButton(onClick = onBack) { Text("Back") }
    }
}
