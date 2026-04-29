import Foundation

struct User: Codable, Identifiable, Hashable {
    let id: Int
    let name: String
    let email: String?
    let createdAt: Date?

    enum CodingKeys: String, CodingKey {
        case id, name, email
        case createdAt = "created_at"
    }
}
