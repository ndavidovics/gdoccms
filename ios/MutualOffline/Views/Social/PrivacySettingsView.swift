import SwiftUI

struct PrivacySettingsView: View {
    @EnvironmentObject var social: SocialService

    @State private var shareSuccesses = true
    @State private var shareFailures = false
    @State private var shareStreaks = true
    @State private var discoverableByContacts = true
    @State private var saving = false

    var body: some View {
        Form {
            Section("Share with friends") {
                Toggle("Share successful sessions", isOn: $shareSuccesses)
                Toggle("Share failed sessions", isOn: $shareFailures)
                Toggle("Share streak milestones", isOn: $shareStreaks)
            }
            Section("Discoverability") {
                Toggle("Discoverable by contacts", isOn: $discoverableByContacts)
            }
            Section {
                Button {
                    Task {
                        saving = true
                        await social.updatePrivacy(
                            shareSuccesses: shareSuccesses,
                            shareFailures: shareFailures,
                            shareStreaks: shareStreaks,
                            discoverableByContacts: discoverableByContacts
                        )
                        saving = false
                    }
                } label: {
                    HStack {
                        if saving { ProgressView() }
                        Text("Save")
                    }
                }
                .disabled(saving)
            }
        }
        .navigationTitle("Privacy")
    }
}
