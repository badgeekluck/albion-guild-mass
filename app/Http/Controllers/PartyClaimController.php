<?php

namespace App\Http\Controllers;

use App\Models\LinkAttendee;
use App\Models\SharedLink;
use App\Services\PartyNotifier;
use App\Services\SlotAssigner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Self-service slots: players claim an open slot instead of waiting to be dragged in.
 * CTA claims wait for the caller ("claimed"); PvP content claims are placed directly.
 */
class PartyClaimController extends Controller
{
    public function __construct(private PartyNotifier $notifier, private SlotAssigner $assigner) {}

    public function claim(Request $request, SharedLink $link): RedirectResponse
    {
        $user = $request->user();
        $mine = $link->attendees()->where('user_id', $user->id)->first();

        $data = $request->validate([
            'slot' => ['required', 'integer', 'min:1', 'max:'.$link->maxSlots()],
            'in_game_name' => [$mine ? 'nullable' : 'required', 'string', 'min:2', 'max:20', 'alpha_num:ascii'],
            'note' => ['nullable', 'string', 'max:100'],
        ], ['in_game_name.alpha_num' => 'Oyun içi isim sadece harf ve rakam içerebilir.']);

        if (! $link->acceptsRegistrations()) {
            return back()->with('error', $link->isCompleted() ? 'Bu etkinlik tamamlandı.' : 'Bu etkinliğin kayıt süresi doldu.');
        }
        if ($link->isBlocked($user)) {
            return back()->with('error', 'Caller seni bu partiden çıkardı; tekrar kaydolamazsın.');
        }
        if ($mine?->claim_locked) {
            return back()->with('error', 'Caller seni yedeğe aldı. Kendin slot seçemezsin; caller seni yerleştirene kadar bekle.');
        }

        $slot = (int) $data['slot'];

        try {
            $error = DB::transaction(function () use ($link, $user, $slot, $data) {
                $locked = SharedLink::whereKey($link->id)->lockForUpdate()->firstOrFail();
                $mine = $locked->attendees()->where('user_id', $user->id)->first();

                // Someone else got here first. (A double click on your own claim is just a no-op.)
                if ($locked->attendees()->where('slot_index', $slot)->where('user_id', '!=', $user->id)->exists()) {
                    return 'Bu slot az önce alındı, başka bir slot seç.';
                }

                // In a CTA the caller's arrangement wins: release your slot first.
                if ($mine && ! $locked->isContent() && $mine->slot_index && $mine->claim_status === LinkAttendee::CLAIM_CONFIRMED) {
                    return "Caller seni {$mine->slot_index}. slota yerleştirdi. Değiştirmek için önce slotunu bırak (Release).";
                }

                $role = $this->assigner->roleForSlot($locked, $slot);

                if (! $mine) {
                    $mine = $locked->attendees()->create([
                        'user_id' => $user->id,
                        'in_game_name' => $data['in_game_name'],
                        'note' => $data['note'] ?? null,
                        'main_role' => $role['name'] ?? LinkAttendee::ROLE_FILL,
                        'main_role_id' => $role['id'],
                    ]);
                } elseif (! empty($data['note'])) {
                    $mine->note = $data['note'];
                }

                $mine->forceFill([
                    'slot_index' => $slot,
                    'assigned_role' => $role['name'] ?? $mine->main_role,
                    'is_forced' => false,
                    'claim_status' => $locked->isContent() ? LinkAttendee::CLAIM_CONFIRMED : LinkAttendee::CLAIM_CLAIMED,
                ])->save();

                return null;
            });
        } catch (UniqueConstraintViolationException) {
            $error = 'Bu slot az önce alındı, başka bir slot seç.';
        }

        if ($error) {
            return back()->with('error', $error);
        }

        $this->notifier->refresh($link);

        return back()->with('success', $link->isContent()
            ? "{$slot}. slot senin!"
            : "{$slot}. slotu aldın — caller onaylayınca ✓ Confirmed olacak.");
    }

    /** Give up your slot but stay registered (waitlist). */
    public function release(Request $request, SharedLink $link): RedirectResponse
    {
        if (! $link->acceptsChanges()) {
            return back()->with('error', 'Bu etkinlik tamamlandı.');
        }

        $released = $link->attendees()->where('user_id', $request->user()->id)->whereNotNull('slot_index')
            ->update(['slot_index' => null, 'assigned_role' => null, 'is_forced' => false, 'claim_status' => null]);

        if (! $released) {
            return back()->with('error', 'Bir slotun yok.');
        }

        $this->notifier->refresh($link);

        return back()->with('success', 'Slotu bıraktın, waitlist\'tesin.');
    }

    public function confirm(SharedLink $link, LinkAttendee $attendee): RedirectResponse
    {
        $this->authorizeDecision($link, $attendee);

        $attendee->forceFill(['claim_status' => LinkAttendee::CLAIM_CONFIRMED])->save();
        $this->notifier->refresh($link);

        return back()->with('success', $attendee->displayName().' onaylandı.');
    }

    public function reject(SharedLink $link, LinkAttendee $attendee): RedirectResponse
    {
        $this->authorizeDecision($link, $attendee);

        return $this->benchAttendee($link, $attendee);
    }

    /** Caller takes a placed player (claimed or confirmed) out of their slot. Works without drag & drop (mobile). */
    public function bench(SharedLink $link, LinkAttendee $attendee): RedirectResponse
    {
        $this->authorizeDecision($link, $attendee);

        return $this->benchAttendee($link, $attendee);
    }

    /** Caller removes a player from the party; they can't sign up to it again. */
    public function kick(Request $request, SharedLink $link, LinkAttendee $attendee): RedirectResponse
    {
        Gate::authorize('arrange', $link);
        abort_unless($attendee->shared_link_id === $link->id, 404);
        abort_unless($link->acceptsChanges(), 403, 'Bu etkinlik tamamlandı.');

        if ($attendee->user_id === $request->user()->id) {
            return back()->with('error', 'Kendini kick edemezsin; partiden çıkmak için Leave kullan.');
        }

        $name = $attendee->displayName();
        $link->forceFill(['blocked_user_ids' => array_values(array_unique([...(array) $link->blocked_user_ids, $attendee->user_id]))])->save();
        $attendee->delete();
        $this->notifier->refresh($link);

        return back()->with('success', "{$name} partiden çıkarıldı.");
    }

    private function benchAttendee(SharedLink $link, LinkAttendee $attendee): RedirectResponse
    {
        $attendee->forceFill([
            'slot_index' => null, 'assigned_role' => null, 'is_forced' => false, 'claim_status' => null,
            'claim_locked' => true, // until a caller places them again
        ])->save();
        $this->notifier->refresh($link);

        return back()->with('success', $attendee->displayName().' yedeğe alındı; caller yerleştirene kadar kendi slot seçemez.');
    }

    private function authorizeDecision(SharedLink $link, LinkAttendee $attendee): void
    {
        Gate::authorize('arrange', $link);
        abort_unless($attendee->shared_link_id === $link->id && $attendee->slot_index !== null, 404);
        abort_unless($link->acceptsChanges(), 403, 'Bu etkinlik tamamlandı.');
    }
}
