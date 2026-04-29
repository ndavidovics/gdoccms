package app.mutualoffline.ui.social

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import app.mutualoffline.api.FeedItem
import app.mutualoffline.data.SocialRepository

@Composable
fun FeedScreen(
    socialRepository: SocialRepository,
    onBack: () -> Unit,
) {
    var items by remember { mutableStateOf<List<FeedItem>>(emptyList()) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        runCatching { socialRepository.fetchFeed() }
            .onSuccess { items = it.first }
            .onFailure { error = it.message ?: "Failed" }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Text("Feed")
        error?.let { Text("Error: $it") }
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(items) { item ->
                Column {
                    Text("${item.user?.name ?: item.userId} • ${item.kind}")
                    item.payload.forEach { (k, v) -> Text("  $k = $v") }
                }
            }
        }
        TextButton(onClick = onBack) { Text("Back") }
    }
}
