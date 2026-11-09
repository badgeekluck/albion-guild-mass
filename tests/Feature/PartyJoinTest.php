<?php

namespace Tests\Feature;

use App\Models\LinkAttendee;
use App\Models\SharedLink;
use App\Models\User;
use App\Services\PartyNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class PartyJoinTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
        $this->player = User::factory()->create();
    }

    private function join(SharedLink $link, array $data)
    {
        return $this->actingAs($this->player)
            ->from(route('party.show', $link))
            ->post(route('party.join', $link), $data + ['in_game_name' => 'MyHero']);
    }

    public function test_cta_join_with_two_roles_stores_role_ids_and_broadcasts(): void
    {
        $this->mock(PartyNotifier::class)->shouldReceive('refresh')->once();
        $link = SharedLink::factory()->create();

        // Datalist format + odd casing are normalised to the canonical role name.
        $this->join($link, ['main_role' => '[Tank] 1H MACE', 'second_role' => 'heavy mace'])
            ->assertRedirect(route('party.show', $link))
            ->assertSessionHas('success');

        $attendee = LinkAttendee::sole();
        $this->assertSame('1h Mace', $attendee->main_role);
        $this->assertSame($this->roles['1h Mace']->id, $attendee->main_role_id);
        $this->assertSame('Heavy Mace', $attendee->second_role);
        $this->assertSame($this->roles['Heavy Mace']->id, $attendee->second_role_id);
        $this->assertNull($attendee->slot_index);
    }

    public function test_cta_requires_two_roles_unless_fill(): void
    {
        $link = SharedLink::factory()->create();

        $this->join($link, ['main_role' => 'Bow'])->assertSessionHasErrors('second_role');
        $this->assertDatabaseCount('link_attendees', 0);

        $this->join($link, ['main_role' => 'Fill Tank'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('link_attendees', 1);
    }

    public function test_content_party_allows_a_single_role(): void
    {
        $link = SharedLink::factory()->content()->create();

        $this->join($link, ['main_role' => 'Bow'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('link_attendees', 1);
    }

    public function test_bombsquad_is_exclusive_and_duplicates_are_rejected(): void
    {
        $link = SharedLink::factory()->create();

        $this->join($link, ['main_role' => 'Bombsquad', 'second_role' => 'Bow'])->assertSessionHasErrors('main_role');
        $this->join($link, ['main_role' => 'Bow', 'second_role' => 'bow'])->assertSessionHasErrors('main_role');
        $this->join($link, ['main_role' => 'Not A Weapon', 'second_role' => 'Bow'])->assertSessionHasErrors('main_role');
        // Armor is not a party role.
        $this->join($link, ['main_role' => 'Guardian Helmet', 'second_role' => 'Bow'])->assertSessionHasErrors('main_role');

        $this->assertDatabaseCount('link_attendees', 0);
    }

    public function test_in_game_name_must_be_alphanumeric(): void
    {
        $link = SharedLink::factory()->content()->create();

        $this->actingAs($this->player)
            ->post(route('party.join', $link), ['in_game_name' => '<svg/onload=x>', 'main_role' => 'Bow'])
            ->assertSessionHasErrors('in_game_name');
    }

    public function test_cannot_join_twice(): void
    {
        $link = SharedLink::factory()->content()->create();

        $this->join($link, ['main_role' => 'Bow'])->assertSessionHas('success');
        $this->join($link, ['main_role' => 'Locus'])->assertSessionHas('error', 'Bu partiye zaten kayıtlısınız.');

        $this->assertDatabaseCount('link_attendees', 1);
    }

    public function test_cannot_join_when_registration_closed_or_completed(): void
    {
        $expired = SharedLink::factory()->content()->expired()->create();
        $completed = SharedLink::factory()->content()->completed()->create();

        $this->join($expired, ['main_role' => 'Bow'])->assertSessionHas('error', 'Bu etkinliğin kayıt süresi doldu.');
        $this->join($completed, ['main_role' => 'Bow'])->assertSessionHas('error');

        $this->assertDatabaseCount('link_attendees', 0);
    }

    public function test_leave_removes_registration_and_broadcasts(): void
    {
        $link = SharedLink::factory()->create();
        $this->registerAttendee($link, ['Bow', 'Locus'], $this->player, slot: 3);
        $this->mock(PartyNotifier::class)->shouldReceive('refresh')->once();

        $this->actingAs($this->player)->post(route('party.leave', $link))->assertSessionHas('success');

        $this->assertDatabaseCount('link_attendees', 0);
    }

    public function test_party_screen_renders_and_escapes_template_text(): void
    {
        $structure = $this->template([1 => ['Hallowfall', 'heal']]);
        $structure[1]['note'] = "');alert(1);//";
        $link = SharedLink::factory()->withTemplate($structure)->create(['title' => '<b>ZvZ</b>']);
        $this->registerAttendee($link, ['Hallowfall', 'Bow'], slot: 1);

        $this->actingAs($this->player)->get(route('party.show', $link))
            ->assertOk()
            ->assertSee('&lt;b&gt;ZvZ&lt;/b&gt;', false)
            ->assertDontSee('<b>ZvZ</b>', false)
            ->assertSee('data-note="&#039;);alert(1);//"', false)
            ->assertSee('Join Party');

        $this->actingAs($this->player)->get(route('party.board', $link))
            ->assertOk()
            ->assertSee('class="main-container"', false)
            ->assertDontSee('<html', false);
    }

    public function test_guests_are_sent_to_the_welcome_page(): void
    {
        $link = SharedLink::factory()->create();

        $this->get(route('party.show', $link))->assertRedirect(route('home'));
        $this->get('/test-link-yarat')->assertNotFound();
    }
}
