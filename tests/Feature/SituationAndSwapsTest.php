<?php

namespace Tests\Feature;

use App\Models\GameRole;
use App\Models\PartyTemplate;
use App\Models\SavedBuild;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class SituationAndSwapsTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
    }

    private function partyWithSwapBuild(): SharedLink
    {
        $build = new SavedBuild([
            'name' => 'Mace Engage', 'role_category' => 'Tank', 'weapon_id' => $this->roles['1h Mace']->id,
            'abilities' => 'Q1',
            'swaps' => [
                ['situation' => 'monkey', 'slot' => 'head', 'item_id' => $this->roles['Guardian Helmet']->id, 'text' => ''],
                ['situation' => null, 'slot' => 'weapon', 'item_id' => $this->roles['Heavy Mace']->id, 'text' => ''],
            ],
        ]);
        $build->save();

        $structure = $this->template([1 => ['1h Mace', 'tank', $build->id]]);

        return SharedLink::factory()->withTemplate($structure)->create(['briefing' => "TAKE ALL SWAPS!\n<b>x</b>"]);
    }

    public function test_caller_announces_situation_and_everyone_sees_the_swap(): void
    {
        $link = $this->partyWithSwapBuild();
        $player = User::factory()->create();
        $this->registerAttendee($link, ['1h Mace', 'Heavy Mace'], $player, slot: 1);

        $this->actingAs($player)->get(route('party.board', $link))
            ->assertSee('TAKE ALL SWAPS!')
            ->assertSee('&lt;b&gt;x&lt;/b&gt;', false)
            ->assertDontSee('ENEMY:');

        $this->actingAs($link->creator)->post(route('party.situation', $link), ['situation' => 'monkey'])->assertSessionHas('success');
        $this->assertSame('monkey', $link->refresh()->situation);

        $this->actingAs($player)->get(route('party.board', $link))
            ->assertSee('ENEMY:')
            ->assertSee('Your swap:')
            ->assertSee('Head → Guardian Helmet')
            ->assertSee('swap-active');

        $this->actingAs($link->creator)->post(route('party.situation', $link), ['situation' => ''])->assertSessionHas('success');
        $this->assertNull($link->refresh()->situation);
    }

    public function test_members_cannot_change_the_situation_and_values_are_validated(): void
    {
        $link = $this->partyWithSwapBuild();

        $this->actingAs(User::factory()->create())->post(route('party.situation', $link), ['situation' => 'kite'])->assertForbidden();
        $this->actingAs($link->creator)->post(route('party.situation', $link), ['situation' => 'zerg'])->assertSessionHasErrors('situation');
        $this->assertNull($link->refresh()->situation);
    }

    public function test_join_note_is_saved_and_shown(): void
    {
        $link = SharedLink::factory()->content()->create();
        $player = User::factory()->create();

        $this->actingAs($player)->post(route('party.join', $link), ['in_game_name' => 'Hero', 'main_role' => 'Bow', 'note' => 'can fill carving'])
            ->assertSessionHasNoErrors();

        $this->assertSame('can fill carving', $link->attendees()->sole()->note);
        $this->actingAs($player)->get(route('party.board', $link))->assertSee('can fill carving');
    }

    public function test_build_form_stores_mount_abilities_and_swaps(): void
    {
        $mount = GameRole::create(['name' => 'Behemoth', 'category' => 'Battle Mount']);
        $caller = User::factory()->creator()->create();

        // Mount-only builds don't need a weapon.
        $this->actingAs($caller)->post(route('builds.store'), [
            'name' => 'Behe', 'role_category' => 'Support', 'mount_id' => $mount->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($caller)->post(route('builds.store'), [
            'name' => 'Mace', 'role_category' => 'Tank', 'weapon_id' => $this->roles['1h Mace']->id, 'abilities' => 'Q1 · W4',
            'swaps' => [
                ['situation' => 'kite', 'slot' => '', 'item' => 'heavy mace'],
                ['situation' => 'monkey', 'slot' => '', 'item' => 'Sticky Potion'],
                ['situation' => '', 'slot' => '', 'item' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $build = SavedBuild::where('name', 'Mace')->sole();
        $this->assertSame('Q1 · W4', $build->abilities);
        $this->assertSame([
            ['situation' => 'kite', 'slot' => 'weapon', 'item_id' => $this->roles['Heavy Mace']->id, 'text' => ''],
            ['situation' => 'monkey', 'slot' => '', 'item_id' => null, 'text' => 'Sticky Potion'],
        ], $build->swaps);

        $this->actingAs($caller)->get(route('builds.edit', $build))->assertOk()->assertSee('Sticky Potion')->assertSee('value="Heavy Mace"', false);
        $this->actingAs($caller)->post(route('builds.store'), ['name' => 'X', 'role_category' => 'Tank'])->assertSessionHasErrors('weapon_id');
    }

    public function test_template_briefing_is_copied_to_new_parties(): void
    {
        $caller = User::factory()->creator()->create();
        $this->actingAs($caller)->put(route('templates.update', $template = PartyTemplate::create([
            'name' => 'T', 'size' => 1, 'structure' => PartyTemplate::emptyStructure(1), 'created_by' => $caller->id,
        ])), ['name' => 'T', 'briefing' => 'WINTER BEAR !', 'slots' => [1 => ['role' => 'Behemoth', 'type' => 'mount']]])->assertSessionHasNoErrors();

        $this->actingAs($caller)->post(route('dashboard.create'), ['title' => 'CTA', 'type' => 'cta', 'template_id' => $template->id]);

        $this->assertSame('WINTER BEAR !', SharedLink::sole()->briefing);
        $this->assertSame('mount', SharedLink::sole()->slotDefinition(1)['type']);
    }
}
