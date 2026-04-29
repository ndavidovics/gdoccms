<?php

namespace App\Events;

use App\Models\Friendship;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Friendship $friendship) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->friendship->recipient_user_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'friendship_id' => $this->friendship->id,
            'requester_user_id' => $this->friendship->requester_user_id,
        ];
    }
}
