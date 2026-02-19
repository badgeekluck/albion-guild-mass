<?php

namespace App\Events;

use App\Models\SharedLink;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartyUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $slug;

    public function __construct(SharedLink $link)
    {
        $this->slug = $link->slug;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('party.' . $this->slug),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message' => 'refresh'
        ];
    }
}
