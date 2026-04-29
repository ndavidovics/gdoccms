import SwiftUI

struct ActiveSessionView: View {
    @EnvironmentObject var sessionService: SessionService
    @Environment(\.protectionService) var protection
    @State private var now = Date()
    @State private var showEmergencyConfirm = false

    private let ticker = Timer.publish(every: 1, on: .main, in: .common).autoconnect()

    var elapsed: TimeInterval {
        guard let started = sessionService.session?.startedAt else { return 0 }
        return now.timeIntervalSince(started)
    }

    var body: some View {
        VStack(spacing: 24) {
            Text(Formatters.duration(elapsed))
                .font(.system(size: 56, weight: .semibold, design: .rounded))
                .monospacedDigit()

            HeartbeatIndicator(state: protection.currentIntegrityState())

            Spacer()

            if endRequestedByOther {
                NavigationLink("Peer requested end - tap to confirm") {
                    EndRequestView()
                }
                .buttonStyle(.borderedProminent)
            }

            Button("Request End") {
                Task { await sessionService.requestEnd() }
            }
            .buttonStyle(.bordered)
            .disabled(endRequestedByMe)

            Button("Emergency exit", role: .destructive) {
                showEmergencyConfirm = true
            }
        }
        .padding()
        .navigationTitle("Active")
        .navigationBarBackButtonHidden(true)
        .onReceive(ticker) { now = $0 }
        .alert("End immediately?", isPresented: $showEmergencyConfirm) {
            Button("Confirm exit", role: .destructive) {
                Task { await sessionService.emergencyExit() }
            }
            Button("Cancel", role: .cancel) {}
        } message: {
            Text("This will mark the session as failed.")
        }
    }

    private var myUserId: Int? { sessionService.session?.participants?.first(where: { $0.userId == AuthSnapshot.currentUserId })?.userId }

    private var endRequestedByMe: Bool {
        guard let id = AuthSnapshot.currentUserId,
              let participants = sessionService.session?.participants else { return false }
        return participants.contains(where: { $0.userId == id && $0.endRequestedAt != nil })
    }

    private var endRequestedByOther: Bool {
        guard let id = AuthSnapshot.currentUserId,
              let participants = sessionService.session?.participants else { return false }
        return participants.contains(where: { $0.userId != id && $0.endRequestedAt != nil })
    }
}

/// Lightweight snapshot of the current user id so views can read it without binding to AuthStore.
enum AuthSnapshot {
    static var currentUserId: Int?
}

struct HeartbeatIndicator: View {
    let state: IntegrityState
    var body: some View {
        HStack(spacing: 16) {
            BadgeView(label: "VPN", on: state.vpnActive)
            BadgeView(label: "Net", on: state.networkBlockActive)
            BadgeView(label: "Notif", on: state.notificationSuppressionActive)
        }
    }
}

struct BadgeView: View {
    let label: String
    let on: Bool
    var body: some View {
        VStack {
            Image(systemName: on ? "checkmark.shield.fill" : "exclamationmark.shield")
                .foregroundStyle(on ? .green : .orange)
            Text(label).font(.caption2)
        }
    }
}
