import SwiftUI

struct ProfileStatsView: View {
    @EnvironmentObject var auth: AuthStore

    var body: some View {
        List {
            if let user = auth.currentUser {
                Section("Account") {
                    LabeledContent("Name", value: user.name)
                    if let email = user.email {
                        LabeledContent("Email", value: email)
                    }
                }
            }
            if let stats = auth.stats {
                Section("Stats") {
                    LabeledContent("Total sessions", value: "\(stats.totalSessions)")
                    LabeledContent("Successful", value: "\(stats.successfulSessions)")
                    LabeledContent("Failed", value: "\(stats.failedSessions)")
                    LabeledContent("Total offline time", value: Formatters.duration(seconds: stats.totalOfflineSeconds))
                    LabeledContent("Current streak", value: "\(stats.currentStreak)")
                    LabeledContent("Longest streak", value: "\(stats.longestStreak)")
                    if let last = stats.lastSessionAt {
                        LabeledContent("Last session") {
                            Text(last.formatted(date: .abbreviated, time: .shortened))
                        }
                    }
                }
            }
        }
        .navigationTitle("Profile")
        .task { await auth.refreshStats() }
        .refreshable { await auth.refreshStats() }
    }
}
