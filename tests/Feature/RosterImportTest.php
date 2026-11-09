<?php

namespace Tests\Feature;

use App\Models\GameRole;
use App\Models\PartyTemplate;
use App\Models\SavedBuild;
use App\Models\User;
use App\Services\RosterSpreadsheetImporter;
use App\Support\Import\AlbionItemResolver;
use Database\Seeders\GameRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsXlsx;
use Tests\TestCase;

class RosterImportTest extends TestCase
{
    use BuildsXlsx, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GameRoleSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    /** Layout of the guild sheets: B weapon, C player, D head, E armor, F shoes, G offhand/tips, H cape, I food. */
    private function sheet(): string
    {
        return $this->makeXlsx([
            '40 BRAWL' => [
                1 => ['B' => 'TOTAL NUMBER', 'C' => '0', 'D' => 'TANKS ->MIN T7 SIEGE HAMMER'],
                2 => ['A' => '1', 'B' => 'STAFF OF BALANCE/MONKEY->BMS ASAS HOOD SWAP', 'C' => 'someplayer', 'D' => 'HELLION HOOD', 'E' => 'DUSKWEAR ARMOR', 'F' => 'FEYSCALE SAN', 'G' => 'MONKEY-> STICKY POTION', 'H' => 'SMUGLER CAPE', 'I' => 'AVALONIAN PORK OMMELETTE'],
                3 => ['A' => '2', 'B' => 'SPIKED', 'D' => 'MISTWALKER HOOD', 'E' => 'JACKET OF TENACITY', 'F' => 'STALKER SHOES/ROYAL SHOES/VALOR BOOTS', 'H' => 'SMUGLER CAPE', 'I' => 'STEW'],
                4 => ['A' => '3', 'B' => 'HALLOWFALL W-BEAM', 'D' => 'CLEANSE HOOD', 'E' => 'JUDI ARMOR', 'F' => 'MERCENARY SHOES', 'G' => 'BLUEFLAME TORCH', 'H' => 'LYM CAPE', 'I' => 'LEYFIN OMMELETTE'],
                5 => ['B' => 'WINTER BEAR !'],
                6 => ['A' => '4', 'B' => 'BEHE'],
                7 => ['A' => '5', 'B' => 'SPIKED', 'D' => 'MISTWALKER HOOD', 'E' => 'JACKET OF TENACITY', 'F' => 'STALKER SHOES/ROYAL SHOES/VALOR BOOTS', 'H' => 'SMUGLER CAPE', 'I' => 'STEW'],
            ],
            'Notes only' => [1 => ['A' => 'nothing to import here']],
        ]);
    }

    public function test_sheet_becomes_template_with_builds_swaps_and_briefing(): void
    {
        $report = app(RosterSpreadsheetImporter::class)->import($this->sheet(), $this->admin->id, 'ROAD BLOCK');

        $this->assertSame(4, $report['builds_created']); // the duplicated Spiked row is one build
        $this->assertSame(['ROAD BLOCK · 40 BRAWL', 5, 'created'], array_values($report['templates'][0]));
        $this->assertStringStartsWith('skipped', $report['templates'][1]['status']);
        $this->assertArrayHasKey('Head: CLEANSE HOOD', $report['unresolved']);

        $template = PartyTemplate::sole();
        $this->assertSame("TANKS ->MIN T7 SIEGE HAMMER\nWINTER BEAR !", $template->briefing);
        $this->assertSame(['Staff of Balance', 'tank'], [$template->structure[1]['role'], $template->structure[1]['type']]);
        $this->assertSame(['Behemoth', 'mount'], [$template->structure[4]['role'], $template->structure[4]['type']]);
        $this->assertSame($template->structure[2]['build_id'], $template->structure[5]['build_id']);

        $sob = SavedBuild::findOrFail($template->structure[1]['build_id']);
        $this->assertSame('Staff of Balance', $sob->weapon->name);
        $this->assertSame('Duskweaver Armor', $sob->armor->name);
        $this->assertSame('Feyscale Sandals', $sob->shoe->name);
        $this->assertSame('Smuggler Cape', $sob->cape->name);
        $this->assertSame($this->admin->id, $sob->created_by);
        $swaps = collect($sob->swapsWithItems());
        $this->assertEqualsCanonicalizing(
            ['Black Monk Stave', 'Assassin Hood', 'Sticky Potion'],
            $swaps->where('situation', 'monkey')->map(fn ($s) => $s['item']['name'] ?? $s['text'])->map(fn ($t) => str_replace('Monkey-> ', '', $t))->values()->all(),
        );

        $spiked = SavedBuild::findOrFail($template->structure[2]['build_id']);
        $this->assertSame('Stalker Shoes', $spiked->shoe->name);
        $this->assertSame(['Royal Shoes', 'Boots of Valor'], collect($spiked->swapsWithItems())->whereNull('situation')->pluck('item.name')->all());

        $hallow = SavedBuild::findOrFail($template->structure[3]['build_id']);
        $this->assertSame('W Beam', $hallow->abilities);
        $this->assertNull($hallow->head_id);
        $this->assertStringContainsString('Head: CLEANSE HOOD', $hallow->notes);
        $this->assertStringContainsString('Food: LEYFIN OMMELETTE', $hallow->notes);
        $this->assertSame('Blueflame Torch', $hallow->offhand->name);

        $this->assertSame('Behemoth', SavedBuild::findOrFail($template->structure[4]['build_id'])->mount->name);
    }

    public function test_reimport_updates_in_place_and_dry_run_saves_nothing(): void
    {
        $importer = app(RosterSpreadsheetImporter::class);

        $dry = $importer->import($this->sheet(), $this->admin->id, 'RB', dryRun: true);
        $this->assertSame(4, $dry['builds_created']);
        $this->assertDatabaseCount('saved_builds', 0);
        $this->assertDatabaseCount('party_templates', 0);

        $importer->import($this->sheet(), $this->admin->id, 'RB');
        SavedBuild::where('name', 'Spiked')->update(['name' => 'Spiked (renamed by staff)']);
        $second = $importer->import($this->sheet(), $this->admin->id, 'RB');

        $this->assertSame([0, 4], [$second['builds_created'], $second['builds_updated']]);
        $this->assertSame('updated', $second['templates'][0]['status']);
        $this->assertDatabaseCount('saved_builds', 4);
        $this->assertDatabaseHas('saved_builds', ['name' => 'Spiked (renamed by staff)']);
    }

    public function test_staff_can_upload_a_spreadsheet(): void
    {
        $file = new UploadedFile($this->sheet(), 'ROAD BLOCK.xlsx', null, null, true);

        $this->actingAs(User::factory()->creator()->create())
            ->post(route('builds.import'), ['file' => $file])
            ->assertRedirect(route('builds.index'))
            ->assertSessionHas('importReport');

        $this->assertDatabaseHas('party_templates', ['name' => 'ROAD BLOCK · 40 BRAWL']);

        $this->actingAs(User::factory()->create())
            ->post(route('builds.import'), ['file' => new UploadedFile($this->sheet(), 'x.xlsx', null, null, true)])
            ->assertRedirect(route('home'));
    }

    public function test_upload_rejects_non_spreadsheets(): void
    {
        $this->actingAs($this->admin)
            ->post(route('builds.import'), ['file' => UploadedFile::fake()->create('evil.php', 1)])
            ->assertSessionHasErrors('file');
    }

    public function test_resolver_understands_spreadsheet_shorthand(): void
    {
        $resolver = new AlbionItemResolver;

        $shoes = $resolver->parseCell('ROYAL SHOES(MONKEY->CULTIST SANDALS)', 'shoe');
        $this->assertSame('Royal Shoes', $shoes['primary']->name);
        $this->assertSame([['monkey', 'shoe', GameRole::where('name', 'Cultist Sandals')->value('id')]], array_map(fn ($s) => [$s['situation'], $s['slot'], $s['item_id']], $shoes['swaps']));

        $weapon = $resolver->parseCell('1H HAMMER-LEERING C/KITE OR CASTLE->POLE SWAP6.1', 'weapon');
        $this->assertSame('1h Hammer', $weapon['primary']->name);
        $this->assertEqualsCanonicalizing(['kite', 'castle', null], array_column($weapon['swaps'], 'situation'));

        // "HELLFIRE POT" is a potion, not the Hellfire Hands weapon.
        $this->assertSame([], $resolver->itemsIn('MONKEY-> HELLFIRE POT'));
        $this->assertSame('Q3 · W5', AlbionItemResolver::abilitiesIn('1H NATURE Q3-W5'));
        $this->assertSame('Heavy Mace / Purge Gala or Hold Engage', AlbionItemResolver::titleCase('HEAVY MACE/PURGE GALA OR HOLD ENGAGE'));
    }
}
