import SwiftUI

struct FindFriendsView: View {
    @EnvironmentObject var social: SocialService
    @State private var optedIn = false
    @State private var syncing = false

    var body: some View {
        List {
            Section {
                Text("We hash your contacts on this device using SHA-256 before they ever leave it. Only the hashes are uploaded.")
                    .font(.callout)
                    .foregroundStyle(.secondary)
                Toggle("I agree to upload hashed contacts", isOn: $optedIn)
                Button {
                    Task {
                        syncing = true
                        await social.syncContacts()
                        syncing = false
                    }
                } label: {
                    HStack {
                        if syncing { ProgressView() }
                        Text("Sync contacts")
                    }
                }
                .disabled(!optedIn || syncing)
            }

            if !social.contactMatches.isEmpty {
                Section("Matches") {
                    ForEach(social.contactMatches) { m in
                        HStack {
                            Text(m.name)
                            Spacer()
                            Button("Add") {
                                Task { await social.sendFriendRequest(userId: m.userId) }
                            }
                            .buttonStyle(.borderedProminent)
                        }
                    }
                }
            }

            if let err = social.lastError {
                Section { Text(err).foregroundStyle(.red) }
            }
        }
        .navigationTitle("Find Friends")
    }
}
