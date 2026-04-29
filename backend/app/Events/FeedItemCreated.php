<?php

namespace App\Events;

use App\Models\FeedItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedItemCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FeedItem $item) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->item->user_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->item->id,
            'type' => $this->item->type,
            'visibility' => $this->item->visibility,
            'created_at' => optional($this->item->created_at)->toIso8601String(),
        ];
    }
}
