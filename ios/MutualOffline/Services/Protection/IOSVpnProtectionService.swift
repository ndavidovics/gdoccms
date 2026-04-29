import Foundation
import NetworkExtension

// V1 stub. A real implementation requires an NEPacketTunnelProvider extension target
// (with the `com.apple.developer.networking.networkextension` entitlement) that performs
// actual packet filtering. This class only manages the configuration profile lifecycle and
// reports a simulated active state.
public final class IOSVpnProtectionService: ProtectionService {
    private var simulatedActive: Bool = false
    private let lock = NSLock()

    public init() {}

    public func requestPermissions() async throws {
        let manager = try await loadOrCreateManager()
        // Saving the preferences triggers iOS to prompt the user to install the VPN profile.
        try await manager.saveToPreferences()
    }

    public func startProtection(sessionId: String) async throws {
        // Real packet filtering would call manager.connection.startVPNTunnel() against a
        // packet-tunnel provider here.
        lock.lock(); simulatedActive = true; lock.unlock()
    }

    public func stopProtection(sessionId: String) async throws {
        lock.lock(); simulatedActive = false; lock.unlock()
    }

    public func currentIntegrityState() -> IntegrityState {
        lock.lock(); let active = simulatedActive; lock.unlock()
        // dnd_active is nil — iOS does not expose Focus/DND state without a Focus filter extension.
        return IntegrityState(
            vpnActive: active,
            dndActive: nil,
            notificationSuppressionActive: active,
            networkBlockActive: active
        )
    }

    private func loadOrCreateManager() async throws -> NETunnelProviderManager {
        let managers = try await NETunnelProviderManager.loadAllFromPreferences()
        if let existing = managers.first { return existing }
        let manager = NETunnelProviderManager()
        manager.localizedDescription = "Mutual Offline"
        let proto = NETunnelProviderProtocol()
        proto.providerBundleIdentifier = "com.mutualoffline.app.tunnel"
        proto.serverAddress = "MutualOffline"
        manager.protocolConfiguration = proto
        manager.isEnabled = true
        return manager
    }
}
