import Foundation

enum APIConfig {
    static let baseURL: URL = URL(string: "http://localhost:8000")!

    static var apiRoot: URL { baseURL.appendingPathComponent("api") }

    static let appVersion: String = "1.0.0"
    static let platform: String = "ios"

    static let heartbeatInterval: TimeInterval = 10
    static let sessionPollInterval: TimeInterval = 3
}
