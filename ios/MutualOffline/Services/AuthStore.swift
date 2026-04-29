import Foundation

@MainActor
final class AuthStore: ObservableObject {
    @Published private(set) var currentUser: User?
    @Published private(set) var token: String?
    @Published private(set) var stats: UserStats?
    @Published var lastError: String?

    var isAuthenticated: Bool { token != nil && currentUser != nil }

    private let api: APIClient
    private let keychain: KeychainStore

    init(api: APIClient, keychain: KeychainStore) {
        self.api = api
        self.keychain = keychain
        self.token = keychain.get(forKey: KeychainKey.authToken)
    }

    func bootstrap() async {
        guard token != nil else { return }
        do {
            let me = try await api.me()
            self.currentUser = me.user
            self.stats = me.stats
            AuthSnapshot.currentUserId = me.user.id
        } catch APIError.unauthorized {
            await logoutLocal()
        } catch {
            self.lastError = error.localizedDescription
        }
    }

    func login(email: String, password: String) async {
        lastError = nil
        do {
            let res = try await api.login(email: email, password: password)
            persist(token: res.token, user: res.user)
        } catch {
            lastError = error.localizedDescription
        }
    }

    func register(name: String, email: String, password: String) async {
        lastError = nil
        do {
            let res = try await api.register(name: name, email: email, password: password)
            persist(token: res.token, user: res.user)
        } catch {
            lastError = error.localizedDescription
        }
    }

    func logout() async {
        do { try await api.logout() } catch { /* ignore — clear local anyway */ }
        await logoutLocal()
    }

    func refreshStats() async {
        do {
            let res = try await api.profileStats()
            self.stats = res.stats
        } catch {
            lastError = error.localizedDescription
        }
    }

    private func persist(token: String, user: User) {
        keychain.set(token, forKey: KeychainKey.authToken)
        self.token = token
        self.currentUser = user
        AuthSnapshot.currentUserId = user.id
        Task { await refreshStats() }
    }

    private func logoutLocal() async {
        keychain.delete(forKey: KeychainKey.authToken)
        token = nil
        currentUser = nil
        stats = nil
        AuthSnapshot.currentUserId = nil
    }
}
