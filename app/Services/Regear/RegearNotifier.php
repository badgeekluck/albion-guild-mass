<?php

namespace App\Services\Regear;

use App\Jobs\NotifyRegearPlayer;
use App\Models\LinkAttendee;
use App\Models\RegearClaim;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Tells players their regear result by Discord DM: one message per player per event,
 * only when the caller asks (decisions can still change while reviewing), and again
 * automatically when the regear is marked as paid.
 */
class RegearNotifier
{
    public const NO_DISCORD = 'no_discord';

    public const DM_CLOSED = 'dm_closed';

    public const FAILED = 'failed';

    /** @var array<string, User|null> lowercase IGN => user */
    private array $users = [];

    /**
     * Decided claims whose decision wasn't sent yet, grouped by player (lowercase IGN).
     *
     * @return Collection<string, Collection<int, RegearClaim>>
     */
    public function pendingByPlayer(SharedLink $link): Collection
    {
        return $link->regearClaims()->with('attendee.user')->get()
            ->filter(fn (RegearClaim $c) => $c->needsNotification())
            ->groupBy(fn (RegearClaim $c) => mb_strtolower($c->victim_name));
    }

    /** @return int players a DM was queued for */
    public function sendDecisions(SharedLink $link): int
    {
        $queued = 0;
        foreach ($this->pendingByPlayer($link) as $claims) {
            $queued += $this->queue($link, $claims, NotifyRegearPlayer::DECISION);
        }

        return $queued;
    }

    /** @param  Collection<int, RegearClaim>  $paidClaims */
    public function sendPaid(SharedLink $link, Collection $paidClaims): int
    {
        $queued = 0;
        foreach ($paidClaims->groupBy(fn (RegearClaim $c) => mb_strtolower($c->victim_name)) as $claims) {
            $queued += $this->queue($link, $claims, NotifyRegearPlayer::PAID);
        }

        return $queued;
    }

    /** The player's Discord account: their sign-up in this party, else the latest sign-up with this in-game name. */
    public function recipient(RegearClaim $claim): ?User
    {
        $key = mb_strtolower($claim->victim_name);
        if (! array_key_exists($key, $this->users)) {
            $this->users[$key] = $claim->attendee?->user
                ?? LinkAttendee::with('user')->whereRaw('LOWER(in_game_name) = ?', [$key])->latest('id')->first()?->user;
        }
        $user = $this->users[$key];

        return $user && filled($user->discord_id) && ! $user->isDevAccount() ? $user : null;
    }

    /** @param  Collection<int, RegearClaim>  $claims */
    private function queue(SharedLink $link, Collection $claims, string $kind): int
    {
        $user = $this->recipient($claims->first());
        if (! $user) {
            RegearClaim::whereKey($claims->modelKeys())->update(['notify_error' => self::NO_DISCORD]);

            return 0;
        }

        NotifyRegearPlayer::dispatch($link->id, (string) $user->discord_id, $claims->modelKeys(), $kind);

        return 1;
    }

    /**
     * @param  Collection<int, RegearClaim>  $claims  every claim to report, of one player in one event
     */
    public static function decisionMessage(SharedLink $link, Collection $claims, bool $update): string
    {
        $lines = ["💰 **{$link->title}** regear sonucun".($update ? ' (güncellendi)' : '').':'];
        foreach ($claims->sortBy('died_at') as $claim) {
            $when = '<t:'.$claim->died_at->timestamp.':t> ölümün';
            $lines[] = match ($claim->status) {
                RegearClaim::APPROVED => "✅ {$when}: **".RegearClaim::formatSilver($claim->payable()).'** onaylandı'.($claim->paid_at ? ' · ödendi' : ''),
                RegearClaim::REJECTED => "❌ {$when}: reddedildi".($claim->reject_reason ? " — *{$claim->reject_reason}*" : ''),
                default => "⏳ {$when}: henüz karar verilmedi",
            };
        }

        $total = $claims->sum(fn (RegearClaim $c) => $c->payable());
        $unpaid = $claims->whereNull('paid_at')->sum(fn (RegearClaim $c) => $c->payable());
        if ($total) {
            $lines[] = 'Onaylanan toplam: **'.RegearClaim::formatSilver($total).'**.'.($unpaid ? ' Ödeme yapılınca ayrıca haber vereceğiz.' : '');
        }
        if ($claims->contains('status', RegearClaim::REJECTED)) {
            $lines[] = 'Bir yanlışlık olduğunu düşünüyorsan caller\'a yaz.';
        }

        return implode("\n", $lines);
    }

    /** @param  Collection<int, RegearClaim>  $claims */
    public static function paidMessage(SharedLink $link, Collection $claims): string
    {
        return '💸 **'.$link->title.'** regear\'ın ödendi: **'.RegearClaim::formatSilver($claims->sum(fn (RegearClaim $c) => $c->payable())).'**. İyi oyunlar!';
    }
}
