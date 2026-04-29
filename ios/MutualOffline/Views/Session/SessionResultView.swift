import SwiftUI

struct SessionResultView: View {
    @EnvironmentObject var sessionService: SessionService
    @EnvironmentObject var auth: AuthStore

    var body: some View {
        VStack(spacing: 20) {
            if let session = sessionService.session {
                Image(systemName: icon(for: session.state))
                    .font(.system(size: 80))
                    .foregroundStyle(color(for: session.state))
                Text(title(for: session.state))
                    .font(.title.bold())
                if let reason = session.failureReason {
                    Text(label(for: reason))
                        .foregroundStyle(.secondary)
                }
                if let started = session.startedAt, let ended = session.endedAt {
                    Text("Duration: \(Formatters.duration(seconds: Int(ended.timeIntervalSince(started))))")
                        .font(.headline)
                }
            }

            Spacer()

            Button("Done") {
                sessionService.clear()
                Task { await auth.refreshStats() }
            }
            .buttonStyle(.borderedProminent)
        }
        .padding()
        .navigationTitle("Result")
        .navigationBarBackButtonHidden(true)
    }

    private func icon(for state: SessionState) -> String {
        switch state {
        case .success: return "checkmark.circle.fill"
        case .failed: return "xmark.octagon.fill"
        case .cancelled: return "xmark.circle"
        default: return "questionmark.circle"
        }
    }

    private func color(for state: SessionState) -> Color {
        switch state {
        case .success: return .green
        case .failed: return .red
        case .cancelled: return .secondary
        default: return .secondary
        }
    }

    private func title(for state: SessionState) -> String {
        switch state {
        case .success: return "Success"
        case .failed: return "Failed"
        case .cancelled: return "Cancelled"
        default: return state.rawValue.capitalized
        }
    }

    private func label(for reason: FailureReason) -> String {
        switch reason {
        case .protectionDisabled: return "Protection was disabled."
        case .heartbeatTimeout: return "Heartbeat timed out."
        case .deviceMismatch: return "Device did not match registered device."
        case .emergencyExit: return "Emergency exit was used."
        case .permissionRevoked: return "A required permission was revoked."
        case .cancelledBeforeStart: return "Session was cancelled before it started."
        }
    }
}
