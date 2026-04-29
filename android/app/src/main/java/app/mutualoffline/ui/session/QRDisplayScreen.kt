package app.mutualoffline.ui.session

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.unit.dp
import app.mutualoffline.data.SessionRepository
import com.google.zxing.BarcodeFormat
import com.journeyapps.barcodescanner.BarcodeEncoder
import kotlinx.coroutines.delay

@Composable
fun QRDisplayScreen(
    sessionUuid: String,
    sessionRepository: SessionRepository,
    onPartnerJoined: () -> Unit,
    onCancel: () -> Unit,
) {
    val payload by sessionRepository.lastJoinPayload.collectAsState()
    val current by sessionRepository.current.collectAsState()
    val bitmap = remember(payload) {
        payload?.let {
            val encoder = BarcodeEncoder()
            encoder.encodeBitmap(it, BarcodeFormat.QR_CODE, 720, 720)
        }
    }

    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(sessionUuid) {
        // Poll session state until partner joins (state moves to ready_to_lock).
        while (true) {
            delay(2_000L)
            runCatching { sessionRepository.refresh(sessionUuid) }
                .onFailure { error = it.message }
            val state = sessionRepository.current.value?.state
            if (state == "ready_to_lock" || state == "active") {
                onPartnerJoined()
                break
            }
            if (state == "cancelled" || state == "failed") break
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text("Show this QR to your partner")
        bitmap?.let {
            Image(
                bitmap = it.asImageBitmap(),
                contentDescription = "Join QR",
                modifier = Modifier.size(280.dp),
            )
        } ?: Text("Generating QR...")
        Text("State: ${current?.state ?: "..."}")
        error?.let { Text("Error: $it") }
        TextButton(onClick = onCancel) { Text("Cancel") }
    }
}
