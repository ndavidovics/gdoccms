import Foundation

enum SessionState: String, Codable {
    case created
    case pendingSecondUser = "pending_second_user"
    case readyToLock = "ready_to_lock"
    case active
    case success
    case failed
    case cancelled

    var isTerminal: Bool {
        switch self {
        case .success, .failed, .cancelled: return true
        default: return false
        }
    }
}

enum FailureReason: String, Codable {
    case protectionDisabled = "protection_disabled"
    case heartbeatTimeout = "heartbeat_timeout"
    case deviceMismatch = "device_mismatch"
    case emergencyExit = "emergency_exit"
    case permissionRevoked = "permission_revoked"
    case cancelledBeforeStart = "cancelled_before_start"
}

struct OfflineSession: Codable, Identifiable, Hashable {
    let uuid: String
    let hostUserId: Int
    let guestUserId: Int?
    let state: SessionState
    let createdAt: Date?
    let startedAt: Date?
    let endedAt: Date?
    let failureReason: FailureReason?
    let participants: [SessionParticipant]?
    let recentEvents: [SessionEvent]?

    var id: String { uuid }

    enum CodingKeys: String, CodingKey {
        case uuid
        case hostUserId = "host_user_id"
        case guestUserId = "guest_user_id"
        case state
        case createdAt = "created_at"
        case startedAt = "started_at"
        case endedAt = "ended_at"
        case failureReason = "failure_reason"
        case participants
        case recentEvents = "recent_events"
    }
}

struct SessionEvent: Codable, Hashable {
    let id: Int?
    let type: String
    let payload: [String: AnyCodable]?
    let createdAt: Date?

    enum CodingKeys: String, CodingKey {
        case id, type, payload
        case createdAt = "created_at"
    }
}

struct AnyCodable: Codable, Hashable {
    let value: String

    init(from decoder: Decoder) throws {
        let c = try decoder.singleValueContainer()
        if let s = try? c.decode(String.self) { value = s }
        else if let i = try? c.decode(Int.self) { value = String(i) }
        else if let d = try? c.decode(Double.self) { value = String(d) }
        else if let b = try? c.decode(Bool.self) { value = String(b) }
        else { value = "" }
    }

    func encode(to encoder: Encoder) throws {
        var c = encoder.singleValueContainer()
        try c.encode(value)
    }
}

struct CreateSessionResponse: Codable {
    let session: OfflineSession
    let joinToken: String
    let joinQrPayload: String

    enum CodingKeys: String, CodingKey {
        case session
        case joinToken = "join_token"
        case joinQrPayload = "join_qr_payload"
    }
}

struct SessionEnvelope: Codable {
    let session: OfflineSession
}
