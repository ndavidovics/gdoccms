import Foundation

enum ParticipantRole: String, Codable {
    case host
    case guest
}

struct SessionParticipant: Codable, Identifiable, Hashable {
    let id: Int
    let sessionId: Int?
    let userId: Int
    let role: ParticipantRole
    let lockConfirmedAt: Date?
    let endRequestedAt: Date?
    let endConfirmedAt: Date?
    let deviceUuid: String?
    let lastHeartbeatAt: Date?
    let user: User?

    enum CodingKeys: String, CodingKey {
        case id
        case sessionId = "session_id"
        case userId = "user_id"
        case role
        case lockConfirmedAt = "lock_confirmed_at"
        case endRequestedAt = "end_requested_at"
        case endConfirmedAt = "end_confirmed_at"
        case deviceUuid = "device_uuid"
        case lastHeartbeatAt = "last_heartbeat_at"
        case user
    }
}
