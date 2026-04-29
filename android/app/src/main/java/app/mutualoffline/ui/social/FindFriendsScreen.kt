package app.mutualoffline.ui.social

import android.Manifest
import android.content.pm.PackageManager
import android.provider.ContactsContract
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import app.mutualoffline.api.ContactMatch
import app.mutualoffline.data.SocialRepository
import app.mutualoffline.util.PhoneNormalizer
import app.mutualoffline.util.sha256Hex
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@Composable
fun FindFriendsScreen(
    socialRepository: SocialRepository,
    onBack: () -> Unit,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var matches by remember { mutableStateOf<List<ContactMatch>>(emptyList()) }
    var imported by remember { mutableStateOf(0) }
    var error by remember { mutableStateOf<String?>(null) }
    var loading by remember { mutableStateOf(false) }
    var hasPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.READ_CONTACTS,
            ) == PackageManager.PERMISSION_GRANTED
        )
    }

    val launcher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted -> hasPermission = granted }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Find friends from contacts")
        Text(
            "We hash phone numbers and emails locally with SHA-256 and only " +
                "upload the hashes. Your raw contact list never leaves the device."
        )
        if (!hasPermission) {
            Button(onClick = { launcher.launch(Manifest.permission.READ_CONTACTS) }) {
                Text("Grant contacts access")
            }
        } else {
            Button(
                enabled = !loading,
                onClick = {
                    loading = true
                    error = null
                    scope.launch {
                        val hashes = withContext(Dispatchers.IO) { collectHashes(context) }
                        runCatching { socialRepository.syncContacts(hashes) }
                            .onSuccess {
                                imported = it.imported
                                matches = it.matches
                            }
                            .onFailure { error = it.message ?: "Sync failed" }
                        loading = false
                    }
                },
            ) { Text(if (loading) "Syncing..." else "Sync contacts") }
        }
        if (imported > 0) Text("Uploaded $imported hashes")
        matches.forEach { m ->
            Text("- ${m.name} (id ${m.userId})")
            Button(onClick = {
                scope.launch {
                    runCatching { socialRepository.requestFriend(m.userId) }
                        .onFailure { error = it.message ?: "Request failed" }
                }
            }) { Text("Send friend request") }
        }
        error?.let { Text("Error: $it") }
        TextButton(onClick = onBack) { Text("Back") }
    }
}

private fun collectHashes(context: android.content.Context): List<String> {
    val hashes = mutableSetOf<String>()
    val resolver = context.contentResolver

    // Phones.
    resolver.query(
        ContactsContract.CommonDataKinds.Phone.CONTENT_URI,
        arrayOf(ContactsContract.CommonDataKinds.Phone.NUMBER),
        null,
        null,
        null,
    )?.use { cursor ->
        val idx = cursor.getColumnIndex(ContactsContract.CommonDataKinds.Phone.NUMBER)
        while (cursor.moveToNext()) {
            val raw = cursor.getString(idx) ?: continue
            val normalized = PhoneNormalizer.normalizePhone(raw)
            if (normalized.isNotEmpty()) hashes.add(normalized.sha256Hex())
        }
    }

    // Emails.
    resolver.query(
        ContactsContract.CommonDataKinds.Email.CONTENT_URI,
        arrayOf(ContactsContract.CommonDataKinds.Email.ADDRESS),
        null,
        null,
        null,
    )?.use { cursor ->
        val idx = cursor.getColumnIndex(ContactsContract.CommonDataKinds.Email.ADDRESS)
        while (cursor.moveToNext()) {
            val raw = cursor.getString(idx) ?: continue
            val normalized = PhoneNormalizer.normalizeEmail(raw)
            if (normalized.isNotEmpty()) hashes.add(normalized.sha256Hex())
        }
    }

    return hashes.toList()
}
