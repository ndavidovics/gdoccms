package app.mutualoffline.ui.social

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import app.mutualoffline.api.FriendsListResponse
import app.mutualoffline.data.SocialRepository
import kotlinx.coroutines.launch

@Composable
fun FriendsListScreen(
    socialRepository: SocialRepository,
    onBack: () -> Unit,
) {
    var data by remember { mutableStateOf<FriendsListResponse?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    suspend fun reload() {
        runCatching { socialRepository.listFriends() }
            .onSuccess { data = it }
            .onFailure { error = it.message ?: "Failed" }
    }

    LaunchedEffect(Unit) { reload() }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Text("Friends")
        error?.let { Text("Error: $it") }
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            item { Text("Accepted") }
            items(data?.accepted ?: emptyList()) { f ->
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("#${f.id} ${f.requester?.name ?: f.requesterId} <-> ${f.recipient?.name ?: f.recipientId}")
                    Button(onClick = {
                        scope.launch {
                            runCatching { socialRepository.removeFriend(f.id) }
                            reload()
                        }
                    }) { Text("Remove") }
                }
            }
            item { Text("Pending incoming") }
            items(data?.pendingIncoming ?: emptyList()) { f ->
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("#${f.id} from ${f.requester?.name ?: f.requesterId}")
                    Button(onClick = {
                        scope.launch {
                            runCatching { socialRepository.acceptFriend(f.id) }
                            reload()
                        }
                    }) { Text("Accept") }
                    Button(onClick = {
                        scope.launch {
                            runCatching { socialRepository.rejectFriend(f.id) }
                            reload()
                        }
                    }) { Text("Reject") }
                }
            }
            item { Text("Pending outgoing") }
            items(data?.pendingOutgoing ?: emptyList()) { f ->
                Text("#${f.id} -> ${f.recipient?.name ?: f.recipientId}")
            }
        }
        TextButton(onClick = onBack) { Text("Back") }
    }
}
