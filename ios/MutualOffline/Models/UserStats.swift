import Foundation

struct UserStats: Codable, Hashable {
    let userId: Int
    let totalSessions: Int
    let successfulSessions: Int
    let failedSessions: Int
    let totalOfflineSeconds: Int
    let currentStreak: Int
    let longestStreak: Int
    let lastSessionAt: Date?

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case totalSessions = "total_sessions"
        case successfulSessions = "successful_sessions"
        case failedSessions = "failed_sessions"
        case totalOfflineSeconds = "total_offline_seconds"
        case currentStreak = "current_streak"
        case longestStreak = "longest_streak"
        case lastSessionAt = "last_session_at"
    }
}
