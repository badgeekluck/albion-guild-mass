<?php

namespace App\Services;

use App\Events\PartyUpdated;
use App\Models\SharedLink;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Throwable;

/**
 * Tells every open party screen to refresh. A broadcasting outage must never
 * break the actual action (join, move...), so failures are only reported.
 */
class PartyNotifier
{
    public function __construct(private BroadcastFactory $broadcaster) {}

    public function refresh(SharedLink $link, bool $toOthers = false): void
    {
        $event = new PartyUpdated($link);
        if ($toOthers) {
            $event->dontBroadcastToCurrentUser();
        }

        try {
            $this->broadcaster->event($event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
