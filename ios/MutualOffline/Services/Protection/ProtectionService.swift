import Foundation

public protocol ProtectionService: AnyObject {
    func requestPermissions() async throws
    func startProtection(sessionId: String) async throws
    func stopProtection(sessionId: String) async throws
    func currentIntegrityState() -> IntegrityState
}
