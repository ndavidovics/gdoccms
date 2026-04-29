import SwiftUI

struct PermissionSetupView: View {
    @EnvironmentObject var deviceRegistration: DeviceRegistrationService
    @Environment(\.protectionService) var protection
    @State private var requesting = false
    @State private var error: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            Text("Set up JustYouTime")
                .font(.title.bold())

            VStack(alignment: .leading, spacing: 12) {
                Label("Install VPN profile", systemImage: "network")
                    .font(.headline)
                Text("JustYouTime uses an on-device VPN profile to block network traffic during a session. iOS will prompt you to allow it.")
                    .foregroundStyle(.secondary)
            }

            VStack(alignment: .leading, spacing: 12) {
                Label("Notification limitations", systemImage: "bell.slash")
                    .font(.headline)
                Text("iOS does not allow third-party apps to silence other apps' notifications or read your Focus state. We can only suppress our own notifications. Use Focus mode manually for stronger blocking.")
                    .foregroundStyle(.secondary)
            }

            if let error {
                Text(error).foregroundStyle(.red)
            }

            Spacer()

            Button {
                Task {
                    requesting = true
                    error = nil
                    do {
                        try await protection.requestPermissions()
                    } catch {
                        self.error = error.localizedDescription
                    }
                    await deviceRegistration.ensureRegistered()
                    requesting = false
                }
            } label: {
                HStack {
                    Spacer()
                    if requesting { ProgressView() } else { Text("Continue") }
                    Spacer()
                }
                .padding(.vertical, 8)
            }
            .buttonStyle(.borderedProminent)
            .disabled(requesting)
        }
        .padding()
    }
}
