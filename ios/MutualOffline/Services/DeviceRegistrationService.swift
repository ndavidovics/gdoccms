import Foundation
import UIKit

@MainActor
final class DeviceRegistrationService: ObservableObject {
    @Published private(set) var device: Device?
    @Published private(set) var isRegistered: Bool = false
    @Published var lastError: String?

    let currentDeviceUUID: String

    private let api: APIClient
    private let keychain: KeychainStore

    init(api: APIClient, keychain: KeychainStore) {
        self.api = api
        self.keychain = keychain
        if let existing = keychain.get(forKey: KeychainKey.deviceUUID) {
            self.currentDeviceUUID = existing
        } else {
            let new = UUID().uuidString
            keychain.set(new, forKey: KeychainKey.deviceUUID)
            self.currentDeviceUUID = new
        }
    }

    func ensureRegistered() async {
        if let _ = device { return }
        await register()
    }

    func register() async {
        lastError = nil
        let name = UIDevice.current.name
        do {
            let res = try await api.registerDevice(deviceUUID: currentDeviceUUID, deviceName: name, pushToken: nil)
            self.device = res.device
            self.isRegistered = true
        } catch {
            lastError = error.localizedDescription
        }
    }

    func revoke() async {
        do { try await api.revokeDevice() } catch { /* ignore */ }
        device = nil
        isRegistered = false
    }

    func reset() {
        device = nil
        isRegistered = false
    }
}
