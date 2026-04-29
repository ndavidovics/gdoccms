<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Services\SocialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    public function __construct(private readonly SocialService $social) {}

    public function syncContacts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hashes' => ['required', 'array', 'min:1', 'max:5000'],
            'hashes.*' => ['string', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ]);
        $result = $this->social->syncContacts($request->user(), $data['hashes']);

        return response()->json([
            'imported' => count($data['hashes']),
            'matches' => $result['matches'],
        ]);
    }

    public function deleteContacts(Request $request): JsonResponse
    {
        $deleted = $this->social->deleteContacts($request->user());

        return response()->json(['deleted_rows' => $deleted]);
    }

    public function matches(Request $request): JsonResponse
    {
        return response()->json($this->social->latestMatches($request->user()));
    }

    public function requestFriend(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $f = $this->social->requestFriendship($request->user(), $data['user_id']);

        return response()->json(['friendship' => $f]);
    }

    public function acceptFriend(Request $request, int $id): JsonResponse
    {
        $f = $this->social->acceptFriendship($request->user(), $id);

        return response()->json(['friendship' => $f]);
    }

    public function rejectFriend(Request $request, int $id): JsonResponse
    {
        $f = $this->social->rejectFriendship($request->user(), $id);

        return response()->json(['friendship' => $f]);
    }

    public function removeFriend(Request $request, int $id): JsonResponse
    {
        $this->social->removeFriendship($request->user(), $id);

        return response()->json(['ok' => true]);
    }

    public function listFriends(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $accepted = Friendship::query()
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->where(fn ($q) => $q->where('requester_user_id', $userId)->orWhere('recipient_user_id', $userId))
            ->with(['requester:id,name,username', 'recipient:id,name,username'])
            ->get()
            ->map(function ($f) use ($userId) {
                $other = $f->requester_user_id === $userId ? $f->recipient : $f->requester;

                return ['id' => $f->id, 'user' => $other];
            });

        $pendingIncoming = Friendship::query()
            ->where('status', Friendship::STATUS_PENDING)
            ->where('recipient_user_id', $userId)
            ->with('requester:id,name,username')
            ->get()
            ->map(fn ($f) => ['id' => $f->id, 'user' => $f->requester]);

        $pendingOutgoing = Friendship::query()
            ->where('status', Friendship::STATUS_PENDING)
            ->where('requester_user_id', $userId)
            ->with('recipient:id,name,username')
            ->get()
            ->map(fn ($f) => ['id' => $f->id, 'user' => $f->recipient]);

        return response()->json([
            'accepted' => $accepted,
            'pending_incoming' => $pendingIncoming,
            'pending_outgoing' => $pendingOutgoing,
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $items = $this->social->feedFor($request->user(), (int) $request->input('limit', 50));

        return response()->json([
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'author' => $i->user ? ['id' => $i->user->id, 'name' => $i->user->name, 'username' => $i->user->username] : null,
                'type' => $i->type,
                'visibility' => $i->visibility,
                'payload' => $i->payload,
                'created_at' => optional($i->created_at)->toIso8601String(),
            ])->all(),
        ]);
    }
}
