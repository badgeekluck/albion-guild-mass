<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartyRequest;
use App\Jobs\PostDiscordMessage;
use App\Models\PartyTemplate;
use App\Models\SharedLink;
use App\Models\User;
use App\Services\Discord\DiscordClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DiscordClient $discord): View
    {
        return view('dashboard', [
            'templates' => PartyTemplate::orderBy('name')->get(['id', 'name', 'size']),
            'staffMembers' => User::whereIn('role', User::STAFF_ROLES)->orderBy('name')->get(),
            'activeLinks' => SharedLink::with('creator')
                ->withCount(['attendees', 'attendees as placed_count' => fn ($q) => $q->whereNotNull('slot_index')])
                ->where('status', SharedLink::STATUS_ACTIVE)
                ->latest()
                ->get(),
            'archivedLinks' => SharedLink::with(['creator', 'archiver'])
                ->withCount(['attendees', 'attendees as attended_count' => fn ($q) => $q->where('attended', true)])
                ->where('status', SharedLink::STATUS_COMPLETED)
                ->latest('updated_at')
                ->take(20)
                ->get(),
            'registrationHours' => StorePartyRequest::REGISTRATION_HOURS,
            'canAnnounce' => $discord->announcementsConfigured(),
        ]);
    }

    public function store(StorePartyRequest $request, DiscordClient $discord): RedirectResponse
    {
        $data = $request->validated();
        $template = isset($data['template_id']) ? PartyTemplate::find($data['template_id']) : null;

        $startsAt = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at'])->utc() : null;

        $link = SharedLink::create([
            'creator_id' => $request->user()->id,
            'title' => $data['title'],
            'type' => $data['type'],
            'template_snapshot' => $template?->structure,
            'briefing' => $template?->briefing,
            'expires_at' => now()->addHours((int) ($data['registration_hours'] ?? SharedLink::DEFAULT_REGISTRATION_HOURS)),
        ]);

        if ($startsAt) {
            $link->forceFill([
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes((int) round(60 * (float) ($data['duration_hours'] ?? 2))),
            ])->save();
        }

        if ($request->boolean('announce') && $discord->announcementsConfigured()) {
            $type = $link->isContent() ? '⚔️ PvP Content' : '📢 CTA (Mass)';
            PostDiscordMessage::dispatch(
                (string) config('services.discord.announce_channel_id'),
                "{$type} **{$link->title}** — kayıtlar açık! ({$link->maxSlots()} slot)\n".route('party.show', $link)
                    ."\nKayıt kapanışı: <t:{$link->expires_at->timestamp}:R>",
            );
        }

        return back()->with('success', 'Yeni parti linki oluşturuldu!');
    }

    public function archive(Request $request, SharedLink $link): RedirectResponse
    {
        Gate::authorize('manage', $link);

        if ($link->isCompleted()) {
            return back()->with('error', 'Bu etkinlik zaten arşivde.');
        }

        DB::transaction(function () use ($link, $request) {
            $link->forceFill([
                'status' => SharedLink::STATUS_COMPLETED,
                'archived_by' => $request->user()->id,
            ])->save();

            // Default check-in: players placed in a slot attended; staff can adjust afterwards.
            $link->attendees()->update(['attended' => DB::raw('CASE WHEN slot_index IS NULL THEN 0 ELSE 1 END')]);
        });

        return back()->with('success', 'Etkinlik tamamlandı ve arşive taşındı! 🏁 Katılımı parti ekranından düzenleyebilirsiniz.');
    }

    public function destroy(SharedLink $link): RedirectResponse
    {
        Gate::authorize('manage', $link);

        $link->delete();

        return back()->with('success', 'Link başarıyla silindi.');
    }
}
