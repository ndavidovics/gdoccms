import SwiftUI

struct FriendsListView: View {
    @EnvironmentObject var social: SocialService

    var body: some View {
        List {
            if !social.friends.incoming.isEmpty {
                Section("Incoming requests") {
                    ForEach(social.friends.incoming) { f in
                        HStack {
                            Text(f.user?.name ?? "User #\(f.requesterId)")
                            Spacer()
                            Button("Accept") { Task { await social.accept(friendshipId: f.id) } }
                                .buttonStyle(.borderedProminent)
                            Button("Reject") { Task { await social.reject(friendshipId: f.id) } }
                                .buttonStyle(.bordered)
                        }
                    }
                }
            }

            if !social.friends.outgoing.isEmpty {
                Section("Sent requests") {
                    ForEach(social.friends.outgoing) { f in
                        HStack {
                            Text(f.user?.name ?? "User #\(f.recipientId)")
                            Spacer()
                            Text("Pending").font(.caption).foregroundStyle(.secondary)
                        }
                    }
                }
            }

            Section("Friends") {
                if social.friends.accepted.isEmpty {
                    Text("No friends yet").foregroundStyle(.secondary)
                } else {
                    ForEach(social.friends.accepted) { f in
                        HStack {
                            Text(f.user?.name ?? "User")
                            Spacer()
                            Button("Remove", role: .destructive) {
                                Task { await social.remove(friendshipId: f.id) }
                            }
                        }
                    }
                }
            }
        }
        .navigationTitle("Friends")
        .task { await social.refreshFriends() }
        .refreshable { await social.refreshFriends() }
    }
}
