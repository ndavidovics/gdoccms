import Foundation

@MainActor
final class SessionService: ObservableObject {
    @Published private(set) var session: OfflineSession?
    @Published private(set) var joinToken: String?
    @Published private(set) var joinQRPayload: String?
    @Published var lastError: String?

    private let api: APIClient
    private let protection: ProtectionService
    private let deviceRegistration: DeviceRegistrationService

    private var heartbeat: HeartbeatService?
    private var pollTask: Task<Void, Never>?

    init(api: APIClient, protection: ProtectionService, deviceRegistration: DeviceRegistrationService) {
        self.api = api
        self.protection = protection
        self.deviceRegistration = deviceRegistration
    }

    func create() async {
        lastError = nil
        do {
            let res = try await api.createSession()
            session = res.session
            joinToken = res.joinToken
            joinQRPayload = res.joinQrPayload
            startPolling()
        } catch {
            lastError = error.localizedDescription
        }
    }

    func join(token: String) async {
        lastError = nil
        do {
            let res = try await api.joinSession(token: token)
            session = res.session
            joinToken = nil
            joinQRPayload = nil
            startPolling()
        } catch {
            lastError = error.localizedDescription
        }
    }

    func confirmLock() async {
        guard let uuid = session?.uuid else { return }
        do {
            let res = try await api.confirmLock(uuid: uuid)
            await applyState(res.session)
        } catch {
            lastError = error.localizedDescription
        }
    }

    func requestEnd() async {
        guard let uuid = session?.uuid else { return }
        do {
            let res = try await api.requestEnd(uuid: uuid)
            await applyState(res.session)
        } catch {
            lastError = error.localizedDescription
        }
    }

    func confirmEnd() async {
        guard let uuid = session?.uuid else { return }
        do {
            let res = try await api.confirmEnd(uuid: uuid)
            await applyState(res.session)
        } catch {
            lastError = error.localizedDescription
        }
    }

    func emergencyExit() async {
        guard let uuid = session?.uuid else { return }
        do {
            let res = try await api.emergencyExit(uuid: uuid)
            await applyState(res.session)
        } catch {
            lastError = error.localizedDescription
        }
    }

    func refresh() async {
        guard let uuid = session?.uuid else { return }
        do {
            let res = try await api.getSession(uuid: uuid)
            await applyState(res.session)
        } catch {
            lastError = error.localizedDescription
        }
    }

    func clear() {
        stopHeartbeat()
        stopPolling()
        session = nil
        joinToken = nil
        joinQRPayload = nil
    }

    private func applyState(_ next: OfflineSession) async {
        let previousState = session?.state
        session = next

        if next.state == .active && previousState != .active {
            await startProtectionAndHeartbeat()
        }

        if next.state.isTerminal {
            stopHeartbeat()
            stopPolling()
            try? await protection.stopProtection(sessionId: next.uuid)
        }
    }

    private func startProtectionAndHeartbeat() async {
        guard let uuid = session?.uuid else { return }
        do {
            try await protection.startProtection(sessionId: uuid)
        } catch {
            lastError = error.localizedDescription
        }
        let hb = HeartbeatService(api: api, protection: protection, deviceRegistration: deviceRegistration)
        hb.start(sessionUUID: uuid) { [weak self] updated in
            Task { @MainActor in
                guard let self else { return }
                await self.applyState(updated)
            }
        } onError: { [weak self] err in
            Task { @MainActor in self?.lastError = err.localizedDescription }
        }
        self.heartbeat = hb
        stopPolling()
    }

    private func stopHeartbeat() {
        heartbeat?.stop()
        heartbeat = nil
    }

    // TODO: replace polling with Reverb/Pusher subscription on `private-sessions.{uuid}`.
    private func startPolling() {
        stopPolling()
        guard let uuid = session?.uuid else { return }
        pollTask = Task { [weak self] in
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: UInt64(APIConfig.sessionPollInterval * 1_000_000_000))
                guard let self else { return }
                let current = await MainActor.run { self.session }
                guard let s = current, s.uuid == uuid, !s.state.isTerminal, s.state != .active else { return }
                do {
                    let res = try await self.api.getSession(uuid: uuid)
                    await self.applyState(res.session)
                } catch {
                    // Swallow transient errors during polling.
                }
            }
        }
    }

    private func stopPolling() {
        pollTask?.cancel()
        pollTask = nil
    }
}
