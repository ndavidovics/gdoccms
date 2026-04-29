<?php

use App\Models\Session;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('sessions.{uuid}', function ($user, string $uuid) {
    $session = Session::where('uuid', $uuid)->with('participants')->first();
    if (! $session) {
        return false;
    }

    return $session->participants->contains(fn ($p) => $p->user_id === $user->id);
});
