import Foundation
import Contacts

@MainActor
final class SocialService: ObservableObject {
    @Published private(set) var friends: FriendsListResponse = FriendsListResponse(accepted: [], incoming: [], outgoing: [])
    @Published private(set) var feed: [FeedItem] = []
    @Published private(set) var contactMatches: [ContactMatch] = []
    @Published var lastError: String?

    private let api: APIClient

    init(api: APIClient) {
        self.api = api
    }

    func refreshFriends() async {
        do {
            friends = try await api.friendsList()
        } catch {
            lastError = error.localizedDescription
        }
    }

    func refreshFeed() async {
        do {
            feed = try await api.feed().data
        } catch {
            lastError = error.localizedDescription
        }
    }

    func sendFriendRequest(userId: Int) async {
        do {
            _ = try await api.friendRequest(userId: userId)
            await refreshFriends()
        } catch {
            lastError = error.localizedDescription
        }
    }

    func accept(friendshipId: Int) async {
        do {
            _ = try await api.acceptFriend(id: friendshipId)
            await refreshFriends()
        } catch {
            lastError = error.localizedDescription
        }
    }

    func reject(friendshipId: Int) async {
        do {
            _ = try await api.rejectFriend(id: friendshipId)
            await refreshFriends()
        } catch {
            lastError = error.localizedDescription
        }
    }

    func remove(friendshipId: Int) async {
        do {
            try await api.deleteFriend(id: friendshipId)
            await refreshFriends()
        } catch {
            lastError = error.localizedDescription
        }
    }

    func updatePrivacy(shareSuccesses: Bool, shareFailures: Bool, shareStreaks: Bool, discoverableByContacts: Bool) async {
        do {
            try await api.updatePrivacy(
                shareSuccesses: shareSuccesses,
                shareFailures: shareFailures,
                shareStreaks: shareStreaks,
                discoverableByContacts: discoverableByContacts
            )
        } catch {
            lastError = error.localizedDescription
        }
    }

    /// Hashes phone/email identifiers locally; only hex strings ever leave the device.
    func syncContacts() async {
        do {
            try await ensureContactsAuthorization()
            let identifiers = try await fetchContactIdentifiers()
            let hashes = identifiers.map { Hashing.sha256Hex($0) }
            let res = try await api.syncContacts(hashes: hashes)
            self.contactMatches = res.matches
        } catch {
            lastError = error.localizedDescription
        }
    }

    private func ensureContactsAuthorization() async throws {
        let store = CNContactStore()
        let status = CNContactStore.authorizationStatus(for: .contacts)
        switch status {
        case .authorized: return
        case .notDetermined:
            let granted: Bool = try await withCheckedThrowingContinuation { cont in
                store.requestAccess(for: .contacts) { ok, err in
                    if let err { cont.resume(throwing: err) } else { cont.resume(returning: ok) }
                }
            }
            if !granted { throw NSError(domain: "SocialService", code: 1, userInfo: [NSLocalizedDescriptionKey: "Contacts permission denied"]) }
        default:
            throw NSError(domain: "SocialService", code: 1, userInfo: [NSLocalizedDescriptionKey: "Contacts permission denied"])
        }
    }

    private func fetchContactIdentifiers() async throws -> [String] {
        let store = CNContactStore()
        let keys: [CNKeyDescriptor] = [
            CNContactPhoneNumbersKey as CNKeyDescriptor,
            CNContactEmailAddressesKey as CNKeyDescriptor
        ]
        let request = CNContactFetchRequest(keysToFetch: keys)
        var ids: [String] = []
        try store.enumerateContacts(with: request) { contact, _ in
            for phone in contact.phoneNumbers {
                ids.append(Self.normalizePhone(phone.value.stringValue))
            }
            for email in contact.emailAddresses {
                ids.append(Self.normalizeEmail(email.value as String))
            }
        }
        return ids.filter { !$0.isEmpty }
    }

    static func normalizePhone(_ raw: String) -> String {
        let digits = raw.unicodeScalars.filter { CharacterSet.decimalDigits.contains($0) }
        return String(String.UnicodeScalarView(digits))
    }

    static func normalizeEmail(_ raw: String) -> String {
        raw.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
    }
}
