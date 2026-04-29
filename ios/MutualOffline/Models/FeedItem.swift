import Foundation

enum FeedItemType: String, Codable {
    case sessionSuccess = "session_success"
    case sessionFailure = "session_failure"
    case streakMilestone = "streak_milestone"
    case friendJoined = "friend_joined"
    case unknown

    init(from decoder: Decoder) throws {
        let c = try decoder.singleValueContainer()
        let raw = try c.decode(String.self)
        self = FeedItemType(rawValue: raw) ?? .unknown
    }

    func encode(to encoder: Encoder) throws {
        var c = encoder.singleValueContainer()
        try c.encode(rawValue)
    }
}

enum FeedVisibility: String, Codable {
    case privateScope = "private"
    case friends
    case publicScope = "public"
}

struct FeedItem: Codable, Identifiable, Hashable {
    let id: Int
    let userId: Int
    let type: FeedItemType
    let visibility: FeedVisibility
    let sessionUuid: String?
    let durationSeconds: Int?
    let streakValue: Int?
    let friendUserId: Int?
    let createdAt: Date?
    let user: User?

    enum CodingKeys: String, CodingKey {
        case id
        case userId = "user_id"
        case type
        case visibility
        case sessionUuid = "session_uuid"
        case durationSeconds = "duration_seconds"
        case streakValue = "streak_value"
        case friendUserId = "friend_user_id"
        case createdAt = "created_at"
        case user
    }
}
