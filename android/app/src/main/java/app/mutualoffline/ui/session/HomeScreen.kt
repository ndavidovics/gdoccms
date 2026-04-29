package app.mutualoffline.ui.session

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import app.mutualoffline.auth.AuthRepository
import kotlinx.coroutines.launch

@Composable
fun HomeScreen(
    authRepository: AuthRepository,
    onCreate: () -> Unit,
    onScan: () -> Unit,
    onProfile: () -> Unit,
    onFeed: () -> Unit,
    onFriends: () -> Unit,
    onFindFriends: () -> Unit,
    onPrivacy: () -> Unit,
    onPermissions: () -> Unit,
    onLogout: () -> Unit,
) {
    val user by authRepository.currentUser.collectAsState()
    val scope = rememberCoroutineScope()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Hello ${user?.name ?: ""}")
        Button(onClick = onCreate) { Text("Create session") }
        Button(onClick = onScan) { Text("Scan partner's QR") }
        Button(onClick = onProfile) { Text("Profile / stats") }
        Button(onClick = onFeed) { Text("Feed") }
        Button(onClick = onFriends) { Text("Friends") }
        Button(onClick = onFindFriends) { Text("Find friends") }
        Button(onClick = onPrivacy) { Text("Privacy") }
        Button(onClick = onPermissions) { Text("Permissions") }
        TextButton(onClick = {
            scope.launch {
                authRepository.logout()
                onLogout()
            }
        }) { Text("Sign out") }
    }
}
