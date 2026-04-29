import SwiftUI

@main
struct MutualOfflineApp: App {
    @StateObject private var auth: AuthStore
    @StateObject private var deviceRegistration: DeviceRegistrationService
    @StateObject private var sessionService: SessionService
    @StateObject private var socialService: SocialService

    private let api: APIClient
    private let protection: ProtectionService

    init() {
        let keychain = KeychainStore()
        let api = APIClient(keychain: keychain)
        let protection = IOSVpnProtectionService()
        let auth = AuthStore(api: api, keychain: keychain)
        let deviceReg = DeviceRegistrationService(api: api, keychain: keychain)
        let sessionService = SessionService(api: api, protection: protection, deviceRegistration: deviceReg)
        let socialService = SocialService(api: api)

        self.api = api
        self.protection = protection
        _auth = StateObject(wrappedValue: auth)
        _deviceRegistration = StateObject(wrappedValue: deviceReg)
        _sessionService = StateObject(wrappedValue: sessionService)
        _socialService = StateObject(wrappedValue: socialService)
    }

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(auth)
                .environmentObject(deviceRegistration)
                .environmentObject(sessionService)
                .environmentObject(socialService)
                .environment(\.protectionService, protection)
        }
    }
}

private struct ProtectionServiceKey: EnvironmentKey {
    static let defaultValue: ProtectionService = MockProtectionService()
}

extension EnvironmentValues {
    var protectionService: ProtectionService {
        get { self[ProtectionServiceKey.self] }
        set { self[ProtectionServiceKey.self] = newValue }
    }
}
