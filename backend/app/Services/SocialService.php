<?php

namespace App\Services;

use App\Events\FeedItemCreated;
use App\Events\FriendRequestAccepted;
use App\Events\FriendRequestReceived;
use App\Models\ContactsImport;
use App\Models\FeedItem;
use App\Models\Friendship;
use App\Models\Session;
use App\Models\User;
use App\Models\UserContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialService
{
    /**
     * Store a batch of hashed contact identifiers and find existing users.
     *
     * @param  string[]  $hashes  Hex-encoded SHA-256 hashes of normalized phone numbers / emails.
     * @return array{import_id:int, matches:array<int, array{user_id:int, name:string, matched_hash:string}>}
     */
    public function syncContacts(User $user, array $hashes): array
    {
        $hashes = collect($hashes)
            ->map(fn ($h) => strtolower((string) $h))
            ->filter(fn ($h) => preg_match('/^[a-f0-9]{64}$/', $h) === 1)
            ->unique()
            ->values()
            ->all();

        return DB::transaction(function () use ($user, $hashes) {
            $import = ContactsImport::create([
                'user_id' => $user->id,
                'hash_count' => count($hashes),
                'created_at' => now(),
            ]);

            // Match against existing users' phone_hash / email_hash.
            $matchedUsers = User::query()
                ->where('discoverable_by_contacts', true)
                ->where(function ($q) use ($hashes) {
                    $q->whereIn('phone_hash', $hashes)->orWhereIn('email_hash', $hashes);
                })
                ->where('id', '!=', $user->id)
                ->get(['id', 'name', 'phone_hash', 'email_hash']);

            $matches = [];
            $rows = [];
            foreach ($hashes as $hash) {
                $matched = $matchedUsers->first(fn ($u) => $u->phone_hash === $hash || $u->email_hash === $hash);
                $rows[] = [
                    'user_id' => $user->id,
                    'contacts_import_id' => $import->id,
                    'hash' => $hash,
                    'matched_user_id' => $matched?->id,
                    'created_at' => now(),
                ];
                if ($matched) {
                    $matches[] = [
                        'user_id' => $matched->id,
                        'name' => $matched->name,
                        'matched_hash' => $hash,
                    ];
                }
            }

            // Upsert (one row per (user, hash)).
            foreach (array_chunk($rows, 500) as $chunk) {
                UserContact::upsert($chunk, ['user_id', 'hash'], ['matched_user_id', 'contacts_import_id']);
            }
            $import->update(['match_count' => count($matches)]);

            return ['import_id' => $import->id, 'matches' => $matches];
        });
    }

    public function deleteContacts(User $user): int
    {
        return UserContact::where('user_id', $user->id)->delete()
            + ContactsImport::where('user_id', $user->id)->delete();
    }

    /**
     * @return array{matches:array<int,array<string,mixed>>}
     */
    public function latestMatches(User $user): array
    {
        $latestImport = ContactsImport::where('user_id', $user->id)->latest('id')->first();
        if (! $latestImport) {
            return ['matches' => []];
        }
        $matches = UserContact::where('contacts_import_id', $latestImport->id)
            ->whereNotNull('matched_user_id')
            ->with('matchedUser:id,name,username')
            ->get()
            ->map(fn ($c) => [
                'user_id' => $c->matched_user_id,
                'name' => $c->matchedUser?->name,
                'username' => $c->matchedUser?->username,
            ])
            ->all();

        return ['matches' => $matches];
    }

    public function requestFriendship(User $requester, int $recipientUserId): Friendship
    {
        abort_if($recipientUserId === $requester->id, 422, 'Cannot friend yourself.');
        $recipient = User::findOrFail($recipientUserId);

        return DB::transaction(function () use ($requester, $recipient) {
            // Look for any existing row in either direction.
            $existing = Friendship::query()
                ->where(function ($q) use ($requester, $recipient) {
                    $q->where('requester_user_id', $requester->id)
                        ->where('recipient_user_id', $recipient->id);
                })
                ->orWhere(function ($q) use ($requester, $recipient) {
                    $q->where('requester_user_id', $recipient->id)
                        ->where('recipient_user_id', $requester->id);
                })
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $friendship = Friendship::create([
                'requester_user_id' => $requester->id,
                'recipient_user_id' => $recipient->id,
                'status' => Friendship::STATUS_PENDING,
            ]);

            event(new FriendRequestReceived($friendship));

            return $friendship;
        });
    }

    public function acceptFriendship(User $user, int $friendshipId): Friendship
    {
        return DB::transaction(function () use ($user, $friendshipId) {
            $f = Friendship::lockForUpdate()->findOrFail($friendshipId);
            abort_unless($f->recipient_user_id === $user->id, 403);
            abort_unless($f->status === Friendship::STATUS_PENDING, 409, 'Friendship not pending.');

            $f->fill(['status' => Friendship::STATUS_ACCEPTED, 'responded_at' => now()])->save();

            // Friend joined feed item for both directions.
            foreach ([$f->requester_user_id, $f->recipient_user_id] as $uid) {
                $other = $uid === $f->requester_user_id ? $f->recipient_user_id : $f->requester_user_id;
                $item = FeedItem::create([
                    'user_id' => $uid,
                    'type' => FeedItem::TYPE_FRIEND_JOINED,
                    'visibility' => FeedItem::VISIBILITY_FRIENDS,
                    'payload' => ['friend_user_id' => $other],
                    'created_at' => now(),
                ]);
                event(new FeedItemCreated($item));
            }
            event(new FriendRequestAccepted($f));

            return $f;
        });
    }

    public function rejectFriendship(User $user, int $friendshipId): Friendship
    {
        $f = Friendship::findOrFail($friendshipId);
        abort_unless($f->recipient_user_id === $user->id, 403);
        $f->fill(['status' => Friendship::STATUS_REJECTED, 'responded_at' => now()])->save();

        return $f;
    }

    public function removeFriendship(User $user, int $friendshipId): void
    {
        $f = Friendship::findOrFail($friendshipId);
        abort_unless(in_array($user->id, [$f->requester_user_id, $f->recipient_user_id], true), 403);
        $f->delete();
    }

    /**
     * @return int[]  IDs of users with whom $user has an accepted friendship.
     */
    public function friendIdsOf(User $user): array
    {
        $a = Friendship::where('status', Friendship::STATUS_ACCEPTED)
            ->where('requester_user_id', $user->id)
            ->pluck('recipient_user_id');
        $b = Friendship::where('status', Friendship::STATUS_ACCEPTED)
            ->where('recipient_user_id', $user->id)
            ->pluck('requester_user_id');

        return $a->merge($b)->unique()->values()->all();
    }

    public function onSessionSucceeded(Session $session): void
    {
        $session->loadMissing('participants.user');
        $names = $session->participants->map(fn ($p) => $p->user->name)->all();
        foreach ($session->participants as $p) {
            $user = $p->user;
            if (! $user->share_successes) {
                continue;
            }
            $item = FeedItem::create([
                'user_id' => $user->id,
                'session_id' => $session->id,
                'type' => FeedItem::TYPE_SESSION_SUCCESS,
                'visibility' => FeedItem::VISIBILITY_FRIENDS,
                'payload' => [
                    'duration_seconds' => $session->durationSeconds(),
                    'partner_name' => collect($names)->reject(fn ($n) => $n === $user->name)->first(),
                ],
                'created_at' => now(),
            ]);
            event(new FeedItemCreated($item));
        }
    }

    public function onSessionFailed(Session $session): void
    {
        foreach ($session->participants as $p) {
            $user = $p->user;
            $visibility = $user->share_failures ? FeedItem::VISIBILITY_FRIENDS : FeedItem::VISIBILITY_PRIVATE;
            $item = FeedItem::create([
                'user_id' => $user->id,
                'session_id' => $session->id,
                'type' => FeedItem::TYPE_SESSION_FAILED,
                'visibility' => $visibility,
                'payload' => [
                    'reason' => $session->failure_reason,
                    'duration_seconds' => $session->durationSeconds(),
                ],
                'created_at' => now(),
            ]);
            if ($visibility === FeedItem::VISIBILITY_FRIENDS) {
                event(new FeedItemCreated($item));
            }
        }
    }

    public function feedFor(User $user, int $limit = 50): \Illuminate\Support\Collection
    {
        $friendIds = $this->friendIdsOf($user);
        $authorIds = collect($friendIds)->push($user->id)->unique()->all();

        return FeedItem::query()
            ->whereIn('user_id', $authorIds)
            ->where(function ($q) use ($user) {
                $q->where('visibility', FeedItem::VISIBILITY_FRIENDS)
                    ->orWhere('user_id', $user->id);
            })
            ->latest('created_at')
            ->limit($limit)
            ->with('user:id,name,username')
            ->get();
    }
}
