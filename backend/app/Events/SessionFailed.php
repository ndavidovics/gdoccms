<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Session $session, public string $reason) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sessions.'.$this->session->uuid)];
    }

    public function broadcastWith(): array
    {
        return [
            'state' => $this->session->state,
            'uuid' => $this->session->uuid,
            'reason' => $this->reason,
            'failed_at' => optional($this->session->failed_at)->toIso8601String(),
        ];
    }
}
