import Foundation

enum Formatters {
    static func duration(seconds: Int) -> String {
        let s = max(seconds, 0)
        let h = s / 3600
        let m = (s % 3600) / 60
        let sec = s % 60
        return String(format: "%02d:%02d:%02d", h, m, sec)
    }

    static func duration(_ interval: TimeInterval) -> String {
        duration(seconds: Int(interval))
    }

    static let iso8601: ISO8601DateFormatter = {
        let f = ISO8601DateFormatter()
        f.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return f
    }()

    static let iso8601NoFraction: ISO8601DateFormatter = {
        let f = ISO8601DateFormatter()
        f.formatOptions = [.withInternetDateTime]
        return f
    }()

    static func currentISOTimestamp() -> String {
        iso8601NoFraction.string(from: Date())
    }
}
