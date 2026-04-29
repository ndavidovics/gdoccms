package app.mutualoffline.data

import app.mutualoffline.api.ApiService
import app.mutualoffline.api.ContactsSyncRequest
import app.mutualoffline.api.ContactsSyncResponse
import app.mutualoffline.api.FeedItem
import app.mutualoffline.api.FriendRequestBody
import app.mutualoffline.api.Friendship
import app.mutualoffline.api.FriendsListResponse
import app.mutualoffline.api.PrivacyRequest
import app.mutualoffline.api.UserStats

class SocialRepository(
    private val api: ApiService,
) {

    suspend fun syncContacts(hashes: List<String>): ContactsSyncResponse =
        api.syncContacts(ContactsSyncRequest(hashes))

    suspend fun fetchMatches() = api.contactMatches().matches

    suspend fun requestFriend(userId: Long): Friendship =
        api.requestFriend(FriendRequestBody(userId)).friendship

    suspend fun acceptFriend(id: Long): Friendship =
        api.acceptFriend(id).friendship

    suspend fun rejectFriend(id: Long): Friendship =
        api.rejectFriend(id).friendship

    suspend fun removeFriend(id: Long) {
        api.removeFriend(id)
    }

    suspend fun listFriends(): FriendsListResponse = api.listFriends()

    suspend fun fetchFeed(cursor: String? = null): Pair<List<FeedItem>, String?> {
        val response = api.feed(cursor)
        return response.data to response.nextCursor
    }

    suspend fun updatePrivacy(
        shareSuccesses: Boolean,
        shareFailures: Boolean,
        shareStreaks: Boolean,
        discoverableByContacts: Boolean,
    ) {
        api.updatePrivacy(
            PrivacyRequest(
                shareSuccesses = shareSuccesses,
                shareFailures = shareFailures,
                shareStreaks = shareStreaks,
                discoverableByContacts = discoverableByContacts,
            )
        )
    }

    suspend fun fetchStats(): UserStats = api.profileStats().stats
}
