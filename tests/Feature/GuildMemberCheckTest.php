<?php

namespace Tests\Feature;

use App\Models\LinkAttendee;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class GuildMemberCheckTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
        $this->player = User::factory()->create();
        config(['services.albion.guild_id' => 'GUILD123']);
    }

    private function fakeMembers(): void
    {
        Http::fake(['gameinfo-ams.albiononline.com/api/gameinfo/guilds/GUILD123/members' => Http::response([
            ['Name' => 'Zero420', 'Id' => 'a'], ['Name' => 'LuhBussy', 'Id' => 'b'],
        ])]);
    }

    public function test_alliance_ctas_accept_members_of_the_other_alliance_guilds(): void
    {
        $this->fakeMembers();
        Http::fake([
            'gameinfo-ams.albiononline.com/api/gameinfo/guilds/GUILD123' => Http::response(['AllianceId' => 'ALLY1', 'AllianceTag' => 'N5FW']),
            'gameinfo-ams.albiononline.com/api/gameinfo/alliances/ALLY1' => Http::response(['Guilds' => [['Id' => 'GUILD123', 'Name' => 'Road Block'], ['Id' => 'G2', 'Name' => 'Other']]]),
            'gameinfo-ams.albiononline.com/api/gameinfo/guilds/G2/members' => Http::response([['Name' => 'AllyPlayer']]),
        ]);

        $guildOnly = SharedLink::factory()->content()->create();
        $this->actingAs($this->player)->post(route('party.join', $guildOnly), ['in_game_name' => 'AllyPlayer', 'main_role' => 'Bow'])
            ->assertSessionHasErrors('in_game_name');

        $allianceCta = SharedLink::factory()->content()->create();
        $allianceCta->forceFill(['regear_include_alliance' => true])->save();
        $this->actingAs($this->player)->post(route('party.join', $allianceCta), ['in_game_name' => 'allyplayer', 'main_role' => 'Bow'])
            ->assertSessionHasNoErrors();
        $this->assertSame('AllyPlayer', $allianceCta->attendees()->first()->in_game_name);

        $other = SharedLink::factory()->content()->create();
        $other->forceFill(['regear_include_alliance' => true])->save();
        $this->actingAs(User::factory()->create())->post(route('party.join', $other), ['in_game_name' => 'Nobody99', 'main_role' => 'Bow'])
            ->assertSessionHasErrors(['in_game_name' => 'No guild or alliance member named "Nobody99" was found. Use your exact in-game name (needed for regear), or tick "This is my exact name" if you joined the guild recently.']);
    }

    public function test_party_creation_can_enable_alliance_mode(): void
    {
        $caller = User::factory()->creator()->create();
        $this->actingAs($caller)->post(route('dashboard.create'), ['title' => 'Alliance CTA', 'type' => SharedLink::TYPE_CTA, 'include_alliance' => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue(SharedLink::where('title', 'Alliance CTA')->first()->regear_include_alliance);
    }

    public function test_unknown_names_are_rejected_with_a_suggestion(): void
    {
        $this->fakeMembers();
        $link = SharedLink::factory()->content()->create();

        $this->actingAs($this->player)->post(route('party.join', $link), ['in_game_name' => 'Zer0420', 'main_role' => 'Bow'])
            ->assertSessionHasErrors(['in_game_name' => 'No guild member named "Zer0420" was found. Did you mean "Zero420"? Use your exact in-game name (needed for regear), or tick "This is my exact name" if you joined the guild recently.']);
        $this->assertDatabaseCount('link_attendees', 0);
    }

    public function test_known_names_are_stored_with_the_in_game_spelling(): void
    {
        $this->fakeMembers();
        $link = SharedLink::factory()->content()->create();

        $this->actingAs($this->player)->post(route('party.join', $link), ['in_game_name' => 'zero420', 'main_role' => 'Bow'])->assertSessionHasNoErrors();

        $this->assertSame('Zero420', LinkAttendee::sole()->in_game_name);
    }

    public function test_players_can_confirm_a_name_that_is_not_listed_yet(): void
    {
        $this->fakeMembers();
        $link = SharedLink::factory()->content()->create();

        $this->actingAs($this->player)->post(route('party.join', $link), ['in_game_name' => 'NewRecruit', 'main_role' => 'Bow', 'ign_confirmed' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('NewRecruit', LinkAttendee::sole()->in_game_name);
    }

    public function test_claims_check_the_name_too(): void
    {
        $this->fakeMembers();
        $link = SharedLink::factory()->create();

        $this->actingAs($this->player)->post(route('party.claim', $link), ['slot' => 1, 'in_game_name' => 'Luhbusy'])
            ->assertSessionHasErrors('in_game_name');
        $this->assertDatabaseCount('link_attendees', 0);

        $this->actingAs($this->player)->post(route('party.claim', $link), ['slot' => 1, 'in_game_name' => 'luhbussy'])->assertSessionHas('success');
        $this->assertSame('LuhBussy', LinkAttendee::sole()->in_game_name);
    }

    public function test_callers_see_which_registered_names_are_not_in_the_guild(): void
    {
        $this->fakeMembers();
        $link = SharedLink::factory()->create();
        $this->registerAttendee($link, ['Bow', 'Locus'])->forceFill(['in_game_name' => 'Typo420'])->save();

        $this->actingAs($link->creator)->get(route('party.board', $link))->assertSee('Not in the guild member list');
        $this->actingAs($this->player)->get(route('party.board', $link))->assertDontSee('Not in the guild member list');
    }

    public function test_a_character_name_can_only_be_registered_once_per_party(): void
    {
        $link = SharedLink::factory()->content()->create();
        $this->registerAttendee($link, ['Bow'])->forceFill(['in_game_name' => 'Zero420'])->save();

        $this->actingAs($this->player)->post(route('party.join', $link), ['in_game_name' => 'zero420', 'main_role' => 'Bow'])
            ->assertSessionHasErrors(['in_game_name' => 'This character is already registered in this party by another player.']);
        $this->actingAs($this->player)->post(route('party.claim', $link), ['slot' => 1, 'in_game_name' => 'ZERO420'])
            ->assertSessionHasErrors('in_game_name');
        $this->assertSame(1, $link->attendees()->count());
    }

    public function test_callers_see_names_used_by_another_discord_account(): void
    {
        $earlier = SharedLink::factory()->create();
        $this->registerAttendee($earlier, ['Bow'])->forceFill(['in_game_name' => 'Zero420'])->save(); // someone else, last week

        $link = SharedLink::factory()->create();
        $this->registerAttendee($link, ['Bow', 'Locus'], $this->player)->forceFill(['in_game_name' => 'Zero420'])->save();

        $this->actingAs($link->creator)->get(route('party.board', $link))->assertSee('also used by another Discord account');
        $this->actingAs($this->player)->get(route('party.board', $link))->assertDontSee('also used by another Discord account');
    }

    public function test_nobody_is_blocked_when_the_albion_api_is_down(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $link = SharedLink::factory()->content()->create();

        $this->actingAs($this->player)->post(route('party.join', $link), ['in_game_name' => 'Anyone', 'main_role' => 'Bow'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('link_attendees', 1);
    }
}
