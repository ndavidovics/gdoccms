import SwiftUI

struct FeedView: View {
    @EnvironmentObject var social: SocialService

    var body: some View {
        List(social.feed) { item in
            VStack(alignment: .leading, spacing: 4) {
                Text(label(for: item))
                    .font(.subheadline)
                if let user = item.user {
                    Text(user.name).font(.caption).foregroundStyle(.secondary)
                }
                if let dt = item.createdAt {
                    Text(dt.formatted(date: .abbreviated, time: .shortened))
                        .font(.caption2)
                        .foregroundStyle(.tertiary)
                }
            }
            .padding(.vertical, 4)
        }
        .navigationTitle("Feed")
        .task { await social.refreshFeed() }
        .refreshable { await social.refreshFeed() }
    }

    private func label(for item: FeedItem) -> String {
        switch item.type {
        case .sessionSuccess:
            let name = item.user?.name ?? "Someone"
            if let secs = item.durationSeconds {
                return "\(name) finished a \(Formatters.duration(seconds: secs)) session."
            }
            return "\(name) finished a session."
        case .sessionFailure:
            return "\(item.user?.name ?? "Someone") had a session interrupted."
        case .streakMilestone:
            if let v = item.streakValue {
                return "\(item.user?.name ?? "Someone") hit a \(v)-day streak."
            }
            return "Streak milestone."
        case .friendJoined:
            return "\(item.user?.name ?? "Someone") joined."
        case .unknown:
            return "Activity"
        }
    }
}
