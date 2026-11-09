<?php

namespace Tests\Feature;

use App\Models\SharedLink;
use App\Models\User;
use App\Services\PartyNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class PartyMoveTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
    }

    private function move(User $user, SharedLink $link, int $attendeeId, int $slot)
    {
        return $this->actingAs($user)->postJson(route('party.move', $link), ['attendee_id' => $attendeeId, 'target_slot' => $slot]);
    }

    public function test_caller_places_player_and_role_is_assigned(): void
    {
        $this->mock(PartyNotifier::class)->shouldReceive('refresh')->once();
        $link = SharedLink::factory()->withTemplate($this->template([1 => ['Hallowfall', 'heal']]))->create();
        $attendee = $this->registerAttendee($link, ['Fallen Staff', 'Hallowfall']);

        $this->move($link->creator, $link, $attendee->id, 1)->assertOk();

        $attendee->refresh();
        $this->assertSame(1, $attendee->slot_index);
        $this->assertSame('Hallowfall', $attendee->assigned_role);
        $this->assertFalse($attendee->is_forced);
    }

    public function test_caller_dropping_on_occupied_slot_moves_occupant_to_waitlist(): void
    {
        $link = SharedLink::factory()->create();
        $occupant = $this->registerAttendee($link, ['Bow', 'Locus'], slot: 1);
        $newcomer = $this->registerAttendee($link, ['Crossbow', 'Locus']);

        $this->move(User::factory()->admin()->create(), $link, $newcomer->id, 1)->assertOk();

        $this->assertNull($occupant->refresh()->slot_index);
        $this->assertSame(1, $newcomer->refresh()->slot_index);
    }

    public function test_regular_member_cannot_arrange_a_cta(): void
    {
        $member = User::factory()->create();
        $link = SharedLink::factory()->create();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus'], $member);

        $this->move($member, $link, $attendee->id, 1)
            ->assertForbidden()
            ->assertJson(['message' => 'Yetkisiz işlem: CTA modunda sadece Caller yerleşim yapabilir.']);

        $this->assertNull($attendee->refresh()->slot_index);
    }

    public function test_content_party_members_can_only_move_themselves_into_free_slots(): void
    {
        $member = User::factory()->create();
        $link = SharedLink::factory()->content()->create();
        $mine = $this->registerAttendee($link, ['Bow'], $member);
        $other = $this->registerAttendee($link, ['Locus'], slot: 2);

        $this->move($member, $link, $mine->id, 1)->assertOk();
        $this->move($member, $link, $other->id, 3)->assertForbidden();
        $this->move($member, $link, $mine->id, 2)->assertStatus(409);

        $this->assertSame(1, $mine->refresh()->slot_index);
        $this->assertSame(2, $other->refresh()->slot_index);
    }

    public function test_slot_must_exist_and_attendee_must_belong_to_party(): void
    {
        $caller = User::factory()->admin()->create();
        $link = SharedLink::factory()->create(); // 20 slots, no extra
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus']);
        $foreign = $this->registerAttendee(SharedLink::factory()->create(), ['Bow', 'Locus']);

        $this->move($caller, $link, $attendee->id, 21)->assertUnprocessable()->assertJsonValidationErrors('target_slot');
        $this->move($caller, $link, $attendee->id, -1)->assertUnprocessable();
        $this->move($caller, $link, $foreign->id, 1)->assertNotFound();
    }

    public function test_moves_are_blocked_once_the_event_is_completed(): void
    {
        $link = SharedLink::factory()->completed()->create();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus']);

        $this->move(User::factory()->admin()->create(), $link, $attendee->id, 1)->assertForbidden();
    }

    public function test_moving_back_to_waitlist_clears_assignment(): void
    {
        $link = SharedLink::factory()->create();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus'], slot: 4);

        $this->move($link->creator, $link, $attendee->id, 0)->assertOk();

        $attendee->refresh();
        $this->assertNull($attendee->slot_index);
        $this->assertNull($attendee->assigned_role);
    }

    public function test_a_broadcast_failure_does_not_break_the_move(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.options.host' => '127.0.0.1', 'broadcasting.connections.reverb.options.port' => 1]);
        $link = SharedLink::factory()->create();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus']);

        $this->move($link->creator, $link, $attendee->id, 1)->assertOk();
        $this->assertSame(1, $attendee->refresh()->slot_index);
    }
}
