<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Models\Friendship;
use App\Models\User;
use App\Models\UserStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name, string $phone): User
    {
        $email = strtolower($name).'@example.com';
        $u = User::create([
            'name' => $name,
            'email' => $email,
            'phone_e164' => $phone,
            'phone_hash' => hash('sha256', $phone),
            'email_hash' => hash('sha256', $email),
            'password' => 'pw-pw-pw-pw',
        ]);
        UserStats::create(['user_id' => $u->id]);

        return $u;
    }

    private function authed(User $u): array
    {
        return ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken, 'Accept' => 'application/json'];
    }

    public function test_contacts_sync_matches_existing_users(): void
    {
        $alex = $this->makeUser('Alex', '+15551110001');
        $jamie = $this->makeUser('Jamie', '+15551110002');

        $resp = $this->postJson('/api/contacts/sync', [
            'hashes' => [
                hash('sha256', '+15551110002'),
                hash('sha256', '+15559999999'), // unknown
            ],
        ], $this->authed($alex))->assertOk();

        $this->assertSame(2, $resp->json('imported'));
        $this->assertCount(1, $resp->json('matches'));
        $this->assertSame($jamie->id, $resp->json('matches.0.user_id'));
    }

    public function test_contacts_sync_skips_users_not_discoverable(): void
    {
        $alex = $this->makeUser('Alex', '+15551110001');
        $jamie = $this->makeUser('Jamie', '+15551110002');
        $jamie->update(['discoverable_by_contacts' => false]);

        $resp = $this->postJson('/api/contacts/sync', [
            'hashes' => [hash('sha256', '+15551110002')],
        ], $this->authed($alex))->assertOk();

        $this->assertCount(0, $resp->json('matches'));
    }

    public function test_friend_request_accept_creates_feed_items(): void
    {
        $alex = $this->makeUser('Alex', '+15551110001');
        $jamie = $this->makeUser('Jamie', '+15551110002');

        $f = $this->postJson('/api/friends/request', ['user_id' => $jamie->id], $this->authed($alex))->json('friendship');
        $this->postJson("/api/friends/{$f['id']}/accept", [], $this->authed($jamie))->assertOk();

        $this->assertSame('accepted', Friendship::find($f['id'])->status);
        $this->assertSame(2, FeedItem::where('type', FeedItem::TYPE_FRIEND_JOINED)->count());
    }

    public function test_feed_only_shows_accepted_friends(): void
    {
        $alex = $this->makeUser('Alex', '+15551110001');
        $jamie = $this->makeUser('Jamie', '+15551110002');
        $stranger = $this->makeUser('Stranger', '+15551110099');

        Friendship::create([
            'requester_user_id' => $alex->id,
            'recipient_user_id' => $jamie->id,
            'status' => 'accepted',
        ]);

        FeedItem::create([
            'user_id' => $jamie->id, 'type' => 'session_success',
            'visibility' => 'friends', 'payload' => ['duration_seconds' => 600],
            'created_at' => now(),
        ]);
        FeedItem::create([
            'user_id' => $stranger->id, 'type' => 'session_success',
            'visibility' => 'friends', 'payload' => ['duration_seconds' => 1200],
            'created_at' => now(),
        ]);

        $items = $this->getJson('/api/feed', $this->authed($alex))->json('items');
        $this->assertCount(1, $items);
        $this->assertSame($jamie->id, $items[0]['author']['id']);
    }

    public function test_failed_session_default_private(): void
    {
        $alex = $this->makeUser('Alex', '+15551110001');
        $jamie = $this->makeUser('Jamie', '+15551110002');

        Friendship::create([
            'requester_user_id' => $alex->id,
            'recipient_user_id' => $jamie->id,
            'status' => 'accepted',
        ]);

        FeedItem::create([
            'user_id' => $jamie->id, 'type' => 'session_failed',
            'visibility' => 'private',
            'payload' => ['reason' => 'protection_disabled'],
            'created_at' => now(),
        ]);

        $items = $this->getJson('/api/feed', $this->authed($alex))->json('items');
        $this->assertCount(0, $items);
    }
}
