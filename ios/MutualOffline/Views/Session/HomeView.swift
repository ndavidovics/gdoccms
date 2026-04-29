import SwiftUI

struct HomeView: View {
    @EnvironmentObject var auth: AuthStore
    @EnvironmentObject var sessionService: SessionService
    @State private var showScanner = false

    var body: some View {
        List {
            if let user = auth.currentUser {
                Section("Welcome") {
                    Text(user.name).font(.headline)
                    if let stats = auth.stats {
                        StatsRow(stats: stats)
                    }
                }
            }

            Section("Start a session") {
                NavigationLink("Create new session") {
                    CreateSessionView()
                }
                Button("Scan friend's QR") {
                    showScanner = true
                }
            }

            Section {
                NavigationLink("Profile & Stats") { ProfileStatsView() }
                NavigationLink("Friends") { FriendsListView() }
                NavigationLink("Find Friends") { FindFriendsView() }
                NavigationLink("Feed") { FeedView() }
                NavigationLink("Privacy") { PrivacySettingsView() }
            }

            Section {
                Button("Sign out", role: .destructive) {
                    Task { await auth.logout() }
                }
            }
        }
        .navigationTitle("Mutual Offline")
        .sheet(isPresented: $showScanner) {
            NavigationStack {
                QRScannerView { payload in
                    showScanner = false
                    if let token = JoinPayloadParser.token(from: payload) {
                        Task { await sessionService.join(token: token) }
                    }
                }
                .navigationTitle("Scan to join")
            }
        }
        .task { await auth.refreshStats() }
        .navigationDestination(isPresented: Binding(
            get: { sessionService.session != nil },
            set: { if !$0 { sessionService.clear() } }
        )) {
            SessionRouterView()
        }
    }
}

struct StatsRow: View {
    let stats: UserStats
    var body: some View {
        HStack {
            VStack(alignment: .leading) {
                Text("\(stats.successfulSessions) success").font(.subheadline)
                Text("\(stats.failedSessions) failed").font(.subheadline).foregroundStyle(.secondary)
            }
            Spacer()
            VStack(alignment: .trailing) {
                Text("Streak: \(stats.currentStreak)").font(.subheadline)
                Text(Formatters.duration(seconds: stats.totalOfflineSeconds)).font(.caption).foregroundStyle(.secondary)
            }
        }
    }
}

enum JoinPayloadParser {
    static func token(from payload: String) -> String? {
        guard let comps = URLComponents(string: payload),
              comps.scheme == "mos",
              comps.host == "join" else { return nil }
        return comps.queryItems?.first(where: { $0.name == "t" })?.value
    }
}

struct SessionRouterView: View {
    @EnvironmentObject var sessionService: SessionService

    var body: some View {
        Group {
            if let session = sessionService.session {
                switch session.state {
                case .created, .pendingSecondUser:
                    CreateSessionView()
                case .readyToLock:
                    ReadyToLockView()
                case .active:
                    ActiveSessionView()
                case .success, .failed, .cancelled:
                    SessionResultView()
                }
            } else {
                ProgressView()
            }
        }
    }
}
