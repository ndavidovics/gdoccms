import Foundation

enum FriendshipStatus: String, Codable {
    case pending
    case accepted
    case rejected
    case blocked
}

struct Friend: Codable, Identifiable, Hashable {
    let id: Int
    let requesterId: Int
    let recipientId: Int
    let status: FriendshipStatus
    let createdAt: Date?
    let acceptedAt: Date?
    let user: User?

    enum CodingKeys: String, CodingKey {
        case id
        case requesterId = "requester_id"
        case recipientId = "recipient_id"
        case status
        case createdAt = "created_at"
        case acceptedAt = "accepted_at"
        case user
    }
}

struct FriendsListResponse: Codable {
    let accepted: [Friend]
    let incoming: [Friend]
    let outgoing: [Friend]
}

struct ContactMatch: Codable, Identifiable, Hashable {
    let userId: Int
    let name: String
    let matchedHash: String

    var id: Int { userId }

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case name
        case matchedHash = "matched_hash"
    }
}

struct ContactsSyncResponse: Codable {
    let imported: Int
    let matches: [ContactMatch]
}
