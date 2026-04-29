import Foundation

final class HeartbeatService {
    private let api: APIClient
    private let protection: ProtectionService
    private let deviceRegistration: DeviceRegistrationService
    private var task: Task<Void, Never>?

    init(api: APIClient, protection: ProtectionService, deviceRegistration: DeviceRegistrationService) {
        self.api = api
        self.protection = protection
        self.deviceRegistration = deviceRegistration
    }

    func start(
        sessionUUID: String,
        onUpdate: @escaping (OfflineSession) -> Void,
        onError: @escaping (Error) -> Void
    ) {
        stop()
        task = Task { [weak self] in
            guard let self else { return }
            while !Task.isCancelled {
                do {
                    let payload = await self.makePayload()
                    let res = try await self.api.sendHeartbeat(uuid: sessionUUID, payload: payload)
                    onUpdate(res.session)
                    if res.session.state.isTerminal { return }
                } catch {
                    onError(error)
                }
                try? await Task.sleep(nanoseconds: UInt64(APIConfig.heartbeatInterval * 1_000_000_000))
            }
        }
    }

    func stop() {
        task?.cancel()
        task = nil
    }

    @MainActor
    private func makePayload() -> HeartbeatPayload {
        let state = protection.currentIntegrityState()
        return HeartbeatPayload(
            device_uuid: deviceRegistration.currentDeviceUUID,
            vpn_active: state.vpnActive,
            dnd_active: state.dndActive,
            notification_suppression_active: state.notificationSuppressionActive,
            network_block_active: state.networkBlockActive,
            app_version: APIConfig.appVersion,
            platform: APIConfig.platform,
            client_timestamp: Formatters.currentISOTimestamp()
        )
    }
}
