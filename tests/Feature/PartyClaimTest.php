<?php

namespace Tests\Feature;

use App\Models\LinkAttendee;
use App\Models\SavedBuild;
use App\Models\SharedLink;
use App\Models\User;
use App\Services\PartyNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class PartyClaimTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
        $this->player = User::factory()->create();
    }

    private function party(string $type = SharedLink::TYPE_CTA): SharedLink
    {
        $build = new SavedBuild(['name' => 'Mace Engage / Purge', 'role_category' => 'Tank', 'weapon_id' => $this->roles['1h Mace']->id]);
        $build->save();

        return SharedLink::factory()->withTemplate($this->template([
            1 => ['Mace Engage', 'tank', $build->id],
            2 => ['Hallowfall', 'heal'],
            3 => ['', 'any'],
        ], size: 3))->create(['type' => $type]);
    }

    private function claim(SharedLink $link, int $slot, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->player)->from(route('party.show', $link))
            ->post(route('party.claim', $link), ['slot' => $slot] + $extra);
    }

    public function test_unregistered_player_claims_a_cta_slot_and_waits_for_the_caller(): void
    {
        $this->mock(PartyNotifier::class)->shouldReceive('refresh')->once();
        $link = $this->party();

        $this->claim($link, 1, ['in_game_name' => 'Tanky', 'note' => 'can swap'])->assertSessionHas('success');

        $attendee = LinkAttendee::sole();
        $this->assertSame([1, LinkAttendee::CLAIM_CLAIMED, '1h Mace', '1h Mace', $this->roles['1h Mace']->id, 'can swap'], [
            $attendee->slot_index, $attendee->claim_status, $attendee->assigned_role, $attendee->main_role, $attendee->main_role_id, $attendee->note,
        ]);
        $this->assertFalse($attendee->is_forced);
    }

    public function test_ign_is_required_for_new_players_and_slot_must_exist(): void
    {
        $link = $this->party();

        $this->claim($link, 1)->assertSessionHasErrors('in_game_name');
        $this->claim($link, 4, ['in_game_name' => 'Tanky'])->assertSessionHasErrors('slot');
        $this->assertDatabaseCount('link_attendees', 0);
    }

    public function test_content_claims_are_confirmed_immediately(): void
    {
        $link = $this->party(SharedLink::TYPE_CONTENT);

        $this->claim($link, 2, ['in_game_name' => 'Healz'])->assertSessionHas('success');

        $this->assertSame(LinkAttendee::CLAIM_CONFIRMED, LinkAttendee::sole()->claim_status);
        $this->assertSame('Hallowfall', LinkAttendee::sole()->assigned_role);
    }

    public function test_double_clicking_your_own_claim_is_harmless(): void
    {
        $link = $this->party();

        $this->claim($link, 1, ['in_game_name' => 'Tanky'])->assertSessionHas('success');
        $this->claim($link, 1)->assertSessionHas('success');

        $this->assertSame([1, 1], [LinkAttendee::count(), LinkAttendee::sole()->slot_index]);
    }

    public function test_taken_slots_cannot_be_claimed(): void
    {
        $link = $this->party();
        $this->registerAttendee($link, ['Bow', 'Locus'], slot: 2);

        $this->claim($link, 2, ['in_game_name' => 'Late'])->assertSessionHas('error', 'Bu slot az önce alındı, başka bir slot seç.');
        $this->assertDatabaseCount('link_attendees', 1);
    }

    public function test_waitlisted_player_claims_with_one_click_and_can_switch_pending_claims(): void
    {
        $link = $this->party();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus'], $this->player);

        $this->claim($link, 3)->assertSessionHas('success');
        $this->assertSame([3, 'claimed', 'Bow'], [$attendee->refresh()->slot_index, $attendee->claim_status, $attendee->assigned_role]);

        $this->claim($link, 2)->assertSessionHas('success');
        $this->assertSame(2, $attendee->refresh()->slot_index);
        $this->assertDatabaseCount('link_attendees', 1);
    }

    public function test_caller_placement_is_locked_until_the_player_releases(): void
    {
        $link = $this->party();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus'], $this->player);
        $this->actingAs($link->creator)->postJson(route('party.move', $link), ['attendee_id' => $attendee->id, 'target_slot' => 3])->assertOk();
        $this->assertSame(LinkAttendee::CLAIM_CONFIRMED, $attendee->refresh()->claim_status);

        $this->claim($link, 2)->assertSessionHas('error');
        $this->assertSame(3, $attendee->refresh()->slot_index);

        $this->actingAs($this->player)->post(route('party.release', $link))->assertSessionHas('success');
        $this->assertNull($attendee->refresh()->slot_index);
        $this->assertNull($attendee->claim_status);

        $this->claim($link, 2)->assertSessionHas('success');
    }

    public function test_caller_confirms_or_rejects_claims(): void
    {
        $link = $this->party();
        $this->claim($link, 1, ['in_game_name' => 'Tanky']);
        $attendee = LinkAttendee::sole();

        $this->actingAs(User::factory()->create())->post(route('party.claims.confirm', [$link, $attendee]))->assertForbidden();

        $this->actingAs($link->creator)->post(route('party.claims.confirm', [$link, $attendee]))->assertSessionHas('success');
        $this->assertSame(LinkAttendee::CLAIM_CONFIRMED, $attendee->refresh()->claim_status);

        $this->actingAs(User::factory()->admin()->create())->post(route('party.claims.reject', [$link, $attendee]))->assertSessionHas('success');
        $this->assertNull($attendee->refresh()->slot_index);
        $this->assertNull($attendee->claim_status);
    }

    public function test_caller_benches_a_confirmed_player_who_then_cannot_self_claim(): void
    {
        $link = $this->party();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus'], $this->player);
        $this->actingAs($link->creator)->postJson(route('party.move', $link), ['attendee_id' => $attendee->id, 'target_slot' => 3])->assertOk();

        $this->actingAs($link->creator)->post(route('party.attendees.bench', [$link, $attendee]))->assertSessionHas('success');
        $attendee->refresh();
        $this->assertSame([null, true], [$attendee->slot_index, $attendee->claim_locked]);

        $this->claim($link, 2)->assertSessionHas('error');
        $this->assertNull($attendee->refresh()->slot_index);
        $this->actingAs($this->player)->get(route('party.board', $link))->assertSee('Benched by the caller')->assertDontSee('+ Claim');

        // Placing them again lifts the lock.
        $this->actingAs($link->creator)->postJson(route('party.move', $link), ['attendee_id' => $attendee->id, 'target_slot' => 2])->assertOk();
        $this->assertFalse($attendee->refresh()->claim_locked);
    }

    public function test_rejected_claims_are_locked_and_content_self_moves_respect_the_lock(): void
    {
        $link = $this->party(SharedLink::TYPE_CONTENT);
        $this->claim($link, 1, ['in_game_name' => 'Tanky']);
        $attendee = LinkAttendee::sole();

        $this->actingAs($link->creator)->post(route('party.attendees.bench', [$link, $attendee]))->assertSessionHas('success');

        $this->actingAs($this->player)->postJson(route('party.move', $link), ['attendee_id' => $attendee->id, 'target_slot' => 2])
            ->assertForbidden()->assertJson(['message' => 'Caller seni yedeğe aldı; seni tekrar yerleştirmesini bekle.']);

        // A pending CTA claim rejected by the caller is locked the same way.
        $cta = $this->party();
        $this->claim($cta, 2, ['in_game_name' => 'Tanky']);
        $pending = $cta->attendees()->sole();
        $this->actingAs($cta->creator)->post(route('party.claims.reject', [$cta, $pending]));
        $this->assertTrue($pending->refresh()->claim_locked);
        $this->claim($cta, 3)->assertSessionHas('error');
    }

    public function test_releasing_your_own_slot_does_not_lock_you(): void
    {
        $link = $this->party();
        $this->claim($link, 1, ['in_game_name' => 'Tanky']);
        $this->actingAs($this->player)->post(route('party.release', $link));

        $this->assertFalse(LinkAttendee::sole()->claim_locked);
        $this->claim($link, 2)->assertSessionHas('success');
    }

    public function test_caller_kicks_a_player_who_cannot_sign_up_again(): void
    {
        $link = $this->party();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus'], $this->player);

        $this->actingAs(User::factory()->create())->post(route('party.attendees.kick', [$link, $attendee]))->assertForbidden();
        $this->actingAs($link->creator)->get(route('party.board', $link))->assertSee(route('party.attendees.kick', [$link, $attendee]), false);

        $this->actingAs($link->creator)->post(route('party.attendees.kick', [$link, $attendee]))->assertSessionHas('success');
        $this->assertDatabaseCount('link_attendees', 0);

        $this->claim($link, 1, ['in_game_name' => 'Again'])->assertSessionHas('error', 'Caller seni bu partiden çıkardı; tekrar kaydolamazsın.');
        $this->actingAs($this->player)->post(route('party.join', $link), ['in_game_name' => 'Again', 'main_role' => 'Fill'])
            ->assertSessionHas('error', 'Caller seni bu partiden çıkardı; tekrar kaydolamazsın.');
        $this->actingAs($this->player)->get(route('party.board', $link))->assertSee('You were removed from this party');
        $this->assertDatabaseCount('link_attendees', 0);
    }

    public function test_callers_see_bench_on_placed_players_only(): void
    {
        $link = $this->party();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus'], slot: 3);
        $attendee->forceFill(['claim_status' => LinkAttendee::CLAIM_CONFIRMED])->save();

        $this->actingAs($link->creator)->get(route('party.board', $link))->assertSee(route('party.attendees.bench', [$link, $attendee]), false);
        $this->actingAs($this->player)->get(route('party.board', $link))->assertDontSee(route('party.attendees.bench', [$link, $attendee]), false);
    }

    public function test_claims_need_open_registration(): void
    {
        $link = $this->party();
        $link->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->claim($link, 1, ['in_game_name' => 'Tanky'])->assertSessionHas('error', 'Bu etkinliğin kayıt süresi doldu.');
        $this->assertDatabaseCount('link_attendees', 0);
    }

    public function test_board_shows_claim_buttons_and_pending_state(): void
    {
        $link = $this->party();

        $this->actingAs($this->player)->get(route('party.board', $link))
            ->assertSee('data-claim-slot="1"', false)
            ->assertSee('+ Claim');

        $this->claim($link, 1, ['in_game_name' => 'Tanky']);

        $this->actingAs($link->creator)->get(route('party.board', $link))
            ->assertSee('Waiting for caller')
            ->assertSee(route('party.claims.confirm', [$link, LinkAttendee::sole()]), false);

        $this->actingAs($this->player)->get(route('party.board', $link))
            ->assertSee('Release')
            ->assertSee('name="slot" value="2"', false); // registered now: one-click claim forms
    }
}
