import Foundation

public final class MockProtectionService: ProtectionService {
    public init() {}
    public func requestPermissions() async throws {}
    public func startProtection(sessionId: String) async throws {}
    public func stopProtection(sessionId: String) async throws {}
    public func currentIntegrityState() -> IntegrityState {
        IntegrityState(vpnActive: true, dndActive: true, notificationSuppressionActive: true, networkBlockActive: true)
    }
}
