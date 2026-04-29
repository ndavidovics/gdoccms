import SwiftUI

struct ReadyToLockView: View {
    @EnvironmentObject var sessionService: SessionService
    @State private var submitting = false

    var body: some View {
        VStack(spacing: 20) {
            Text("Both joined")
                .font(.title2.bold())

            if let session = sessionService.session, let participants = session.participants {
                ForEach(participants) { p in
                    ParticipantRow(participant: p)
                }
            }

            Text("When you both press Confirm Lock, your devices will block network access until both confirm end.")
                .font(.callout)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)

            Button {
                Task {
                    submitting = true
                    await sessionService.confirmLock()
                    submitting = false
                }
            } label: {
                HStack {
                    if submitting { ProgressView() }
                    Text("Confirm Lock")
                }
                .frame(maxWidth: .infinity)
                .padding(.vertical, 8)
            }
            .buttonStyle(.borderedProminent)
            .disabled(submitting)

            Button("Cancel", role: .destructive) {
                Task {
                    await sessionService.emergencyExit()
                }
            }
        }
        .padding()
        .navigationTitle("Ready")
        .task { await sessionService.refresh() }
    }
}

struct ParticipantRow: View {
    let participant: SessionParticipant
    var body: some View {
        HStack {
            VStack(alignment: .leading) {
                Text(participant.user?.name ?? "User #\(participant.userId)").font(.headline)
                Text(participant.role.rawValue.capitalized).font(.caption).foregroundStyle(.secondary)
            }
            Spacer()
            Image(systemName: participant.lockConfirmedAt != nil ? "checkmark.circle.fill" : "circle")
                .foregroundStyle(participant.lockConfirmedAt != nil ? .green : .secondary)
        }
    }
}
