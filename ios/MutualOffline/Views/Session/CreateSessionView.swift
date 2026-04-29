import SwiftUI

struct CreateSessionView: View {
    @EnvironmentObject var sessionService: SessionService
    @State private var creating = false

    var body: some View {
        VStack(spacing: 20) {
            if let session = sessionService.session, let payload = sessionService.joinQRPayload {
                Text("Show this QR to your partner")
                    .font(.headline)
                QRDisplayView(payload: payload)
                    .frame(width: 240, height: 240)
                Text("State: \(session.state.rawValue)")
                    .font(.caption)
                    .foregroundStyle(.secondary)
                Button("Cancel", role: .destructive) { sessionService.clear() }
            } else {
                Text("Create a session and share the QR with your partner.")
                    .multilineTextAlignment(.center)
                    .padding()
                Button {
                    Task {
                        creating = true
                        await sessionService.create()
                        creating = false
                    }
                } label: {
                    HStack {
                        if creating { ProgressView() }
                        Text("Create session")
                    }
                }
                .buttonStyle(.borderedProminent)
            }
            if let err = sessionService.lastError {
                Text(err).foregroundStyle(.red).font(.caption)
            }
        }
        .padding()
        .navigationTitle("New Session")
    }
}
