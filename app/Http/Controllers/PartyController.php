<?php

namespace App\Http\Controllers;

use App\Enums\Situation;
use App\Http\Requests\JoinPartyRequest;
use App\Http\Requests\MovePartyMemberRequest;
use App\Jobs\PostDiscordMessage;
use App\Models\GameRole;
use App\Models\LinkAttendee;
use App\Models\PartyTemplate;
use App\Models\SharedLink;
use App\Services\Discord\DiscordClient;
use App\Services\PartyNotifier;
use App\Services\SlotAssigner;
use App\Support\PartyBoard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PartyController extends Controller
{
    public function __construct(private PartyNotifier $notifier) {}

    public function show(Request $request, SharedLink $link, DiscordClient $discord): View
    {
        $board = new PartyBoard($link, $request->user());

        return view('party.show', [
            'link' => $link,
            'board' => $board,
            'availableRoles' => $this->availableRoles($link),
            'lastIgn' => $request->user()->attendees()->latest('id')->value('in_game_name'),
            'canPing' => $board->canManage && $discord->announcementsConfigured() && $link->acceptsChanges(),
            'reverb' => $this->reverbClientConfig(),
        ]);
    }

    /** Board fragment used by the live refresh. */
    public function board(Request $request, SharedLink $link, DiscordClient $discord): View
    {
        $board = new PartyBoard($link, $request->user());

        return view('party._board', [
            'link' => $link,
            'board' => $board,
            'canPing' => $board->canManage && $discord->announcementsConfigured() && $link->acceptsChanges(),
        ]);
    }

    public function join(JoinPartyRequest $request, SharedLink $link): RedirectResponse
    {
        if (! $link->acceptsRegistrations()) {
            return back()->with('error', $link->isCompleted()
                ? 'Bu etkinlik tamamlandı (Arşivlendi). Değişiklik yapılamaz.'
                : 'Bu etkinliğin kayıt süresi doldu.');
        }

        if ($link->isBlocked($request->user())) {
            return back()->with('error', 'Caller seni bu partiden çıkardı; tekrar kaydolamazsın.');
        }

        try {
            $link->attendees()->create($request->attendeeAttributes() + ['user_id' => $request->user()->id]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', 'Bu partiye zaten kayıtlısınız.');
        }

        $this->notifier->refresh($link);

        return back()->with('success', 'Partiye katıldınız!');
    }

    public function leave(Request $request, SharedLink $link): RedirectResponse
    {
        if (! $link->acceptsChanges()) {
            return back()->with('error', 'Bu etkinlik tamamlandı (Arşivlendi). Değişiklik yapılamaz.');
        }

        $deleted = $link->attendees()->where('user_id', $request->user()->id)->delete();

        if (! $deleted) {
            return back()->with('error', 'Zaten partide değilsiniz.');
        }

        $this->notifier->refresh($link);

        return back()->with('success', 'Partiden ayrıldınız.');
    }

    public function move(MovePartyMemberRequest $request, SharedLink $link, SlotAssigner $assigner): JsonResponse
    {
        $targetSlot = (int) $request->validated('target_slot');
        $attendeeId = (int) $request->validated('attendee_id');
        $user = $request->user();

        try {
            $result = DB::transaction(function () use ($link, $attendeeId, $targetSlot, $user, $assigner) {
                // Serialise all moves of this party so two callers can't race for one slot.
                $locked = SharedLink::whereKey($link->id)->lockForUpdate()->firstOrFail();

                if (! $locked->acceptsChanges()) {
                    return [403, 'Bu etkinlik tamamlandı. Değişiklik yapılamaz.'];
                }

                $attendee = $locked->attendees()->whereKey($attendeeId)->first();
                if (! $attendee) {
                    return [404, 'Oyuncu bu partide bulunamadı.'];
                }

                $occupant = $targetSlot > 0
                    ? $locked->attendees()->where('slot_index', $targetSlot)->whereKeyNot($attendee->id)->first()
                    : null;

                if (! $user->can('arrange', $locked)) {
                    if (! $locked->isContent()) {
                        return [403, 'Yetkisiz işlem: CTA modunda sadece Caller yerleşim yapabilir.'];
                    }
                    if ($attendee->user_id !== $user->id) {
                        return [403, 'Sadece kendi yerini değiştirebilirsin.'];
                    }
                    if ($attendee->claim_locked && $targetSlot > 0) {
                        return [403, 'Caller seni yedeğe aldı; seni tekrar yerleştirmesini bekle.'];
                    }
                    if ($occupant) {
                        return [409, 'Bu slot dolu!'];
                    }
                }

                // A caller dropping onto an occupied slot sends that player back to the waitlist.
                $occupant?->forceFill(['slot_index' => null, 'assigned_role' => null, 'is_forced' => false, 'claim_status' => null])->save();

                $placement = $assigner->assign($attendee, $locked, $targetSlot);
                $placement['claim_status'] = $targetSlot > 0 ? LinkAttendee::CLAIM_CONFIRMED : null;
                if ($user->can('arrange', $locked)) {
                    // Placed by a caller => any bench lock is lifted; benched by a caller => locked.
                    $placement['claim_locked'] = $targetSlot === 0 && $attendee->user_id !== $user->id;
                }
                $attendee->forceFill($placement)->save();

                return [200, null];
            });
        } catch (UniqueConstraintViolationException) {
            $result = [409, 'Bu slot az önce doldu, tekrar deneyin.'];
        }

        [$status, $message] = $result;

        if ($status !== 200) {
            return response()->json(['message' => $message], $status);
        }

        $this->notifier->refresh($link, toOthers: true);

        return response()->json(['success' => true]);
    }

    public function updateExtraSlots(Request $request, SharedLink $link): RedirectResponse
    {
        Gate::authorize('manage', $link);

        if (! $link->acceptsChanges()) {
            return back()->with('error', 'Bu etkinlik tamamlandı (Arşivlendi). Değişiklik yapılamaz.');
        }

        $data = $request->validate([
            'action' => ['required', Rule::in(['add', 'remove'])],
            'amount' => ['nullable', 'integer', Rule::in([1, SharedLink::PARTY_SIZE])],
        ]);
        $amount = (int) ($data['amount'] ?? 1);

        $capped = DB::transaction(function () use ($link, $data, $amount) {
            $locked = SharedLink::whereKey($link->id)->lockForUpdate()->firstOrFail();
            $maxExtra = max(0, SharedLink::MAX_TOTAL_SLOTS - $locked->templateSlotCount());

            if ($data['action'] === 'add') {
                $wanted = $locked->extra_slots + $amount;
                $locked->extra_slots = min($maxExtra, $wanted);
            } else {
                $wanted = $locked->extra_slots - $amount;
                $locked->extra_slots = max(0, $wanted);
            }
            $locked->save();

            // Players sitting in a slot that no longer exists go back to the waitlist.
            $locked->attendees()->where('slot_index', '>', $locked->maxSlots())
                ->update(['slot_index' => null, 'assigned_role' => null, 'is_forced' => false, 'claim_status' => null]);

            return $data['action'] === 'add' && $wanted > $locked->extra_slots;
        });

        $this->notifier->refresh($link);

        if ($capped) {
            return back()->with('error', 'En fazla '.SharedLink::MAX_TOTAL_SLOTS.' slot olabilir ('.(SharedLink::MAX_TOTAL_SLOTS / SharedLink::PARTY_SIZE).' parti).');
        }

        return back()->with('success', 'Kapasite güncellendi: '.$link->refresh()->maxSlots().' slot.');
    }

    /**
     * Caller announces the enemy situation (kite, monkey...); every screen highlights
     * the swaps defined for it. Empty value = back to the normal setup.
     */
    public function setSituation(Request $request, SharedLink $link): RedirectResponse
    {
        Gate::authorize('arrange', $link);

        if (! $link->acceptsChanges()) {
            return back()->with('error', 'Bu etkinlik tamamlandı (Arşivlendi). Değişiklik yapılamaz.');
        }

        $situation = $request->validate(['situation' => ['nullable', Rule::enum(Situation::class)]])['situation'] ?? null;

        $link->forceFill(['situation' => $situation ?: null])->save();
        $this->notifier->refresh($link);

        $label = Situation::tryFrom((string) $situation);

        return back()->with('success', $label ? "Durum: {$label->label()} — swap'lar herkese gösteriliyor." : 'Normal kuruluma dönüldü.');
    }

    /** Mentions every placed player in the configured Discord channel. */
    public function pingRoster(SharedLink $link, DiscordClient $discord): RedirectResponse
    {
        Gate::authorize('manage', $link);

        if (! $discord->announcementsConfigured()) {
            return back()->with('error', 'Discord duyuru kanalı yapılandırılmamış (DISCORD_ANNOUNCE_CHANNEL_ID).');
        }

        $placed = $link->attendees()->with('user')->whereNotNull('slot_index')->orderBy('slot_index')->get()
            ->filter(fn (LinkAttendee $a) => $a->user && ! $a->user->isDevAccount());

        if ($placed->isEmpty()) {
            return back()->with('error', 'Partide yerleştirilmiş oyuncu yok.');
        }

        $lines = ["📣 **{$link->title}** — kadro hazır! ".route('party.show', $link)];
        foreach ($placed as $attendee) {
            $lines[] = "`#{$attendee->slot_index}` <@{$attendee->user->discord_id}> — ".($attendee->assigned_role ?? $attendee->main_role);
        }

        $channel = (string) config('services.discord.announce_channel_id');
        foreach (PostDiscordMessage::chunk(implode("\n", $lines)) as $chunk) {
            preg_match_all('/<@(\d+)>/', $chunk, $m);
            PostDiscordMessage::dispatch($channel, $chunk, $m[1]);
        }

        return back()->with('success', 'Kadro Discord kanalında pinglendi.');
    }

    /** Turns the current roster into a reusable template. */
    public function saveAsTemplate(Request $request, SharedLink $link): RedirectResponse
    {
        Gate::authorize('saveAsTemplate', $link);

        $link->load('attendees');
        $bySlot = $link->attendees->whereNotNull('slot_index')->keyBy('slot_index');
        $structure = [];

        for ($i = 1; $i <= $link->maxSlots(); $i++) {
            $definition = $link->slotDefinition($i);
            $role = $definition['is_extra'] ? '' : $definition['role'];
            $placedRole = $bySlot->get($i)?->assigned_role;

            if (SharedLink::isWildcardRole($role) && $placedRole && ! SharedLink::isWildcardRole($placedRole)) {
                $role = $placedRole;
            }

            $structure[$i] = [
                'role' => $role,
                'note' => $definition['is_extra'] ? 'Flex' : $definition['note'],
                'type' => $definition['is_extra'] ? 'dps' : $definition['type'],
                'build_id' => $definition['build_id'],
            ];
        }

        $template = PartyTemplate::create([
            'name' => mb_substr(($link->title ?: 'Party').' (roster '.now()->format('d.m.Y').')', 0, 255),
            'size' => count($structure),
            'structure' => $structure,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('templates.edit', $template)->with('success', 'Kadro template olarak kaydedildi.');
    }

    /** Post-event check-in: mark who actually attended. */
    public function toggleAttendance(SharedLink $link, LinkAttendee $attendee): RedirectResponse
    {
        Gate::authorize('manage', $link);
        abort_unless($attendee->shared_link_id === $link->id, 404);

        if (! $link->isCompleted()) {
            return back()->with('error', 'Katılım işaretlemesi etkinlik bittikten sonra yapılır.');
        }

        $attendee->forceFill(['attended' => ! $attendee->attended])->save();

        return back()->with('success', $attendee->displayName().' katılımı güncellendi.');
    }

    /**
     * Weapon suggestions for the join form: weapons used by the template, otherwise all weapons.
     */
    private function availableRoles(SharedLink $link)
    {
        $names = [];
        $buildIds = [];
        foreach ($link->templateSlots() as $slot) {
            if (! empty($slot['build_id'])) {
                $buildIds[] = (int) $slot['build_id'];
            } elseif (! SharedLink::isWildcardRole($slot['role'] ?? '')) {
                $names[] = mb_strtolower(SlotAssigner::cleanRoleName((string) $slot['role']));
            }
        }

        $query = GameRole::query()->weapons()->orderBy('category')->orderBy('name');

        if ($names || $buildIds) {
            $restricted = (clone $query)->where(function ($q) use ($names, $buildIds) {
                if ($names) {
                    $q->whereIn(DB::raw('LOWER(name)'), $names);
                }
                if ($buildIds) {
                    $q->orWhereIn('id', DB::table('saved_builds')->whereIn('id', $buildIds)->select('weapon_id'));
                }
            })->get();

            if ($restricted->isNotEmpty()) {
                return $restricted;
            }
        }

        return $query->get();
    }

    /**
     * @return array{key: string|null, host: string|null, port: string|null, scheme: string|null}|null
     */
    private function reverbClientConfig(): ?array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return null;
        }

        return [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.frontend.host'),
            'port' => config('broadcasting.connections.reverb.frontend.port'),
            'scheme' => config('broadcasting.connections.reverb.frontend.scheme'),
        ];
    }
}
