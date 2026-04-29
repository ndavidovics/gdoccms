import Foundation

struct Device: Codable, Identifiable, Hashable {
    let id: Int
    let userId: Int
    let deviceUuid: String
    let platform: String
    let deviceName: String?
    let appVersion: String?
    let pushToken: String?
    let isActive: Bool
    let lastSeenAt: Date?

    enum CodingKeys: String, CodingKey {
        case id
        case userId = "user_id"
        case deviceUuid = "device_uuid"
        case platform
        case deviceName = "device_name"
        case appVersion = "app_version"
        case pushToken = "push_token"
        case isActive = "is_active"
        case lastSeenAt = "last_seen_at"
    }
}
