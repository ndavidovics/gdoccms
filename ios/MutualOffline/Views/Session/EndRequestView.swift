import SwiftUI

struct EndRequestView: View {
    @EnvironmentObject var sessionService: SessionService
    @State private var submitting = false

    var body: some View {
        VStack(spacing: 24) {
            Image(systemName: "hand.raised.fill")
                .font(.system(size: 60))
                .foregroundStyle(.orange)
            Text("Your partner requested to end the session.")
                .font(.headline)
                .multilineTextAlignment(.center)
            Text("Confirm to mark the session as a success. If you're not ready, do nothing — the session continues.")
                .font(.callout)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)

            Button {
                Task {
                    submitting = true
                    await sessionService.confirmEnd()
                    submitting = false
                }
            } label: {
                HStack {
                    if submitting { ProgressView() }
                    Text("Confirm End")
                }
                .frame(maxWidth: .infinity)
                .padding(.vertical, 8)
            }
            .buttonStyle(.borderedProminent)
            .disabled(submitting)
        }
        .padding()
        .navigationTitle("End Requested")
    }
}
