import Foundation

enum APIError: Error, LocalizedError {
    case invalidURL
    case transport(Error)
    case decoding(Error)
    case http(status: Int, message: String?)
    case unauthorized
    case unknown

    var errorDescription: String? {
        switch self {
        case .invalidURL: return "Invalid URL"
        case .transport(let e): return e.localizedDescription
        case .decoding(let e): return "Decoding error: \(e.localizedDescription)"
        case .http(_, let msg): return msg ?? "Request failed"
        case .unauthorized: return "Not authorized"
        case .unknown: return "Unknown error"
        }
    }
}

struct EmptyBody: Encodable {}
struct OKResponse: Decodable { let ok: Bool? }

struct AuthResponse: Decodable {
    let user: User
    let token: String
}

struct MeResponse: Decodable {
    let user: User
    let stats: UserStats
}

struct StatsResponse: Decodable {
    let stats: UserStats
}

struct DeviceResponse: Decodable {
    let device: Device
}

struct FriendResponse: Decodable {
    let friendship: Friend
}

struct FeedResponse: Decodable {
    let data: [FeedItem]
    let nextPageUrl: String?

    enum CodingKeys: String, CodingKey {
        case data
        case nextPageUrl = "next_page_url"
    }
}

final class APIClient {
    private let session: URLSession
    private let keychain: KeychainStore
    private let decoder: JSONDecoder
    private let encoder: JSONEncoder

    init(keychain: KeychainStore, session: URLSession = .shared) {
        self.keychain = keychain
        self.session = session

        let dec = JSONDecoder()
        dec.dateDecodingStrategy = .custom { decoder in
            let c = try decoder.singleValueContainer()
            let s = try c.decode(String.self)
            if let d = Formatters.iso8601.date(from: s) { return d }
            if let d = Formatters.iso8601NoFraction.date(from: s) { return d }
            throw DecodingError.dataCorruptedError(in: c, debugDescription: "Invalid date: \(s)")
        }
        self.decoder = dec

        let enc = JSONEncoder()
        enc.dateEncodingStrategy = .iso8601
        self.encoder = enc
    }

    private var token: String? { keychain.get(forKey: KeychainKey.authToken) }

    private func request<T: Decodable>(
        _ path: String,
        method: String = "GET",
        body: Encodable? = nil,
        authenticated: Bool = true
    ) async throws -> T {
        guard var components = URLComponents(url: APIConfig.apiRoot.appendingPathComponent(path), resolvingAgainstBaseURL: false) else {
            throw APIError.invalidURL
        }
        components.path = APIConfig.apiRoot.path + "/" + path.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        guard let url = components.url else { throw APIError.invalidURL }

        var req = URLRequest(url: url)
        req.httpMethod = method
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        req.setValue("application/json", forHTTPHeaderField: "Content-Type")
        if authenticated, let token {
            req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        if let body {
            req.httpBody = try encoder.encode(AnyEncodable(body))
        }

        let data: Data
        let response: URLResponse
        do {
            (data, response) = try await session.data(for: req)
        } catch {
            throw APIError.transport(error)
        }

        guard let http = response as? HTTPURLResponse else { throw APIError.unknown }
        if http.statusCode == 401 { throw APIError.unauthorized }
        guard (200..<300).contains(http.statusCode) else {
            let msg = (try? JSONDecoder().decode(ErrorPayload.self, from: data))?.message
            throw APIError.http(status: http.statusCode, message: msg)
        }

        if T.self == EmptyResponse.self {
            return EmptyResponse() as! T
        }

        do {
            return try decoder.decode(T.self, from: data)
        } catch {
            throw APIError.decoding(error)
        }
    }

    private struct ErrorPayload: Decodable { let message: String? }

    // MARK: - Auth

    func register(name: String, email: String, password: String) async throws -> AuthResponse {
        struct Body: Encodable {
            let name: String
            let email: String
            let password: String
            let password_confirmation: String
        }
        return try await request("register", method: "POST", body: Body(name: name, email: email, password: password, password_confirmation: password), authenticated: false)
    }

    func login(email: String, password: String) async throws -> AuthResponse {
        struct Body: Encodable { let email: String; let password: String }
        return try await request("login", method: "POST", body: Body(email: email, password: password), authenticated: false)
    }

    func logout() async throws {
        let _: OKResponse = try await request("logout", method: "POST", body: EmptyBody())
    }

    func me() async throws -> MeResponse {
        try await request("me")
    }

    // MARK: - Devices

    func registerDevice(deviceUUID: String, deviceName: String?, pushToken: String?) async throws -> DeviceResponse {
        struct Body: Encodable {
            let device_uuid: String
            let platform: String
            let device_name: String?
            let app_version: String
            let push_token: String?
        }
        return try await request(
            "devices/register",
            method: "POST",
            body: Body(
                device_uuid: deviceUUID,
                platform: APIConfig.platform,
                device_name: deviceName,
                app_version: APIConfig.appVersion,
                push_token: pushToken
            )
        )
    }

    func currentDevice() async throws -> DeviceResponse {
        try await request("devices/current")
    }

    func revokeDevice() async throws {
        let _: OKResponse = try await request("devices/revoke", method: "POST", body: EmptyBody())
    }

    // MARK: - Sessions

    func createSession() async throws -> CreateSessionResponse {
        try await request("sessions", method: "POST", body: EmptyBody())
    }

    func joinSession(token: String) async throws -> SessionEnvelope {
        struct Body: Encodable { let join_token: String }
        return try await request("sessions/join", method: "POST", body: Body(join_token: token))
    }

    func confirmLock(uuid: String) async throws -> SessionEnvelope {
        try await request("sessions/\(uuid)/confirm-lock", method: "POST", body: EmptyBody())
    }

    func sendHeartbeat(uuid: String, payload: HeartbeatPayload) async throws -> SessionEnvelope {
        try await request("sessions/\(uuid)/heartbeat", method: "POST", body: payload)
    }

    func requestEnd(uuid: String) async throws -> SessionEnvelope {
        try await request("sessions/\(uuid)/request-end", method: "POST", body: EmptyBody())
    }

    func confirmEnd(uuid: String) async throws -> SessionEnvelope {
        try await request("sessions/\(uuid)/confirm-end", method: "POST", body: EmptyBody())
    }

    func emergencyExit(uuid: String) async throws -> SessionEnvelope {
        try await request("sessions/\(uuid)/emergency-exit", method: "POST", body: EmptyBody())
    }

    func getSession(uuid: String) async throws -> SessionEnvelope {
        try await request("sessions/\(uuid)")
    }

    func profileStats() async throws -> StatsResponse {
        try await request("profile/stats")
    }

    // MARK: - Social

    func syncContacts(hashes: [String]) async throws -> ContactsSyncResponse {
        struct Body: Encodable { let hashes: [String] }
        return try await request("contacts/sync", method: "POST", body: Body(hashes: hashes))
    }

    func contactMatches() async throws -> ContactsSyncResponse {
        try await request("contacts/matches")
    }

    func friendRequest(userId: Int) async throws -> FriendResponse {
        struct Body: Encodable { let user_id: Int }
        return try await request("friends/request", method: "POST", body: Body(user_id: userId))
    }

    func acceptFriend(id: Int) async throws -> FriendResponse {
        try await request("friends/\(id)/accept", method: "POST", body: EmptyBody())
    }

    func rejectFriend(id: Int) async throws -> FriendResponse {
        try await request("friends/\(id)/reject", method: "POST", body: EmptyBody())
    }

    func deleteFriend(id: Int) async throws {
        let _: OKResponse = try await request("friends/\(id)", method: "DELETE")
    }

    func friendsList() async throws -> FriendsListResponse {
        try await request("friends")
    }

    func feed() async throws -> FeedResponse {
        try await request("feed")
    }

    func updatePrivacy(shareSuccesses: Bool, shareFailures: Bool, shareStreaks: Bool, discoverableByContacts: Bool) async throws {
        struct Body: Encodable {
            let share_successes: Bool
            let share_failures: Bool
            let share_streaks: Bool
            let discoverable_by_contacts: Bool
        }
        let _: OKResponse = try await request(
            "profile/privacy",
            method: "POST",
            body: Body(
                share_successes: shareSuccesses,
                share_failures: shareFailures,
                share_streaks: shareStreaks,
                discoverable_by_contacts: discoverableByContacts
            )
        )
    }
}

struct EmptyResponse: Decodable {}

struct HeartbeatPayload: Encodable {
    let device_uuid: String
    let vpn_active: Bool
    let dnd_active: Bool?
    let notification_suppression_active: Bool
    let network_block_active: Bool
    let app_version: String
    let platform: String
    let client_timestamp: String
}

private struct AnyEncodable: Encodable {
    let value: Encodable
    init(_ value: Encodable) { self.value = value }
    func encode(to encoder: Encoder) throws { try value.encode(to: encoder) }
}
