<?php

namespace App\Jobs;

use App\Models\RegearClaim;
use App\Models\SharedLink;
use App\Services\Discord\DirectMessageRefused;
use App\Services\Discord\DiscordClient;
use App\Services\Regear\RegearNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** One regear DM to one player: their decisions for an event, or "your regear was paid". */
class NotifyRegearPlayer implements ShouldQueue
{
    use Queueable;

    public const DECISION = 'decision';

    public const PAID = 'paid';

    public int $tries = 3;

    public array $backoff = [10, 60];

    /** @param  list<int>  $claimIds */
    public function __construct(public int $linkId, public string $discordUserId, public array $claimIds, public string $kind) {}

    public function handle(DiscordClient $discord): void
    {
        $link = SharedLink::withTrashed()->find($this->linkId);
        $claims = RegearClaim::whereKey($this->claimIds)->get();
        if (! $link || $claims->isEmpty()) {
            return;
        }

        if ($this->kind === self::PAID) {
            $claims = $claims->filter(fn (RegearClaim $c) => $c->paid_at && ! $c->paid_notified_at);
            $message = $claims->isEmpty() ? null : RegearNotifier::paidMessage($link, $claims);
        } else {
            // Report every decided death of this player in the event, not only the changed ones.
            $claims = $link->regearClaims()->whereIn('victim_name', $claims->pluck('victim_name')->unique())->get()
                ->filter(fn (RegearClaim $c) => $c->decisionState() !== null);
            $message = $claims->isEmpty() ? null : RegearNotifier::decisionMessage($link, $claims, $claims->contains(fn ($c) => $c->notified_state !== null));
        }
        if ($message === null) {
            return;
        }

        try {
            $discord->sendDirectMessage($this->discordUserId, $message);
        } catch (DirectMessageRefused) {
            RegearClaim::whereKey($claims->modelKeys())->update(['notify_error' => RegearNotifier::DM_CLOSED]);

            return; // retrying won't help
        }

        foreach ($claims as $claim) {
            $claim->forceFill($this->kind === self::PAID
                ? ['paid_notified_at' => now(), 'notify_error' => null]
                : ['notified_state' => $claim->decisionState(), 'notified_at' => now(), 'notify_error' => null])->save();
        }
    }

    public function failed(?Throwable $e): void
    {
        RegearClaim::whereKey($this->claimIds)->update(['notify_error' => RegearNotifier::FAILED]);
    }
}
