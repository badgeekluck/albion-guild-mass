<?php

namespace Tests\Feature;

use App\Models\PartyTemplate;
use App\Models\SavedBuild;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class StaffAreaTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
    }

    public function test_regular_members_cannot_access_staff_pages(): void
    {
        $member = User::factory()->create();

        foreach (['dashboard', 'templates.index', 'builds.index', 'builds.create'] as $route) {
            $this->actingAs($member)->get(route($route))->assertRedirect(route('home'));
        }
        $this->actingAs($member)->post(route('dashboard.create'), ['title' => 'x', 'type' => 'cta'])->assertRedirect(route('home'));
        $this->assertDatabaseCount('shared_links', 0);
    }

    public function test_staff_pages_render(): void
    {
        $caller = User::factory()->creator()->create();
        SharedLink::factory()->create();
        SharedLink::factory()->completed()->create();
        $template = PartyTemplate::create(['name' => 'T', 'size' => 2, 'structure' => PartyTemplate::emptyStructure(2), 'created_by' => $caller->id]);
        $build = new SavedBuild(['name' => 'B', 'role_category' => 'Tank', 'weapon_id' => $this->roles['1h Mace']->id]);
        $build->save();

        $this->actingAs($caller)->get(route('dashboard'))->assertOk()->assertSee('Active Events');
        $this->actingAs($caller)->get(route('templates.index'))->assertOk();
        $this->actingAs($caller)->get(route('templates.edit', $template))->assertOk();
        $this->actingAs($caller)->get(route('builds.index'))->assertOk();
        $this->actingAs($caller)->get(route('builds.create'))->assertOk();
        $this->actingAs($caller)->get(route('builds.edit', $build))->assertOk()->assertSee('Değişiklikleri kaydet')->assertSee('⧉ Kopyala', false);

        $this->actingAs($caller)->post(route('builds.duplicate', $build))->assertRedirect();
        $copy = SavedBuild::where('name', 'B (kopya)')->sole();
        $this->assertSame([$build->weapon_id, $caller->id, null], [$copy->weapon_id, $copy->created_by, $copy->import_key]);
    }

    public function test_build_library_shows_how_many_templates_use_each_build(): void
    {
        $caller = User::factory()->creator()->create();
        $used = new SavedBuild(['name' => 'Used', 'role_category' => 'Tank', 'weapon_id' => $this->roles['1h Mace']->id]);
        $used->save();
        $unused = new SavedBuild(['name' => 'Unused', 'role_category' => 'DPS', 'weapon_id' => $this->roles['Bow']->id]);
        $unused->save();
        foreach ([1, 2] as $n) {
            $structure = PartyTemplate::emptyStructure(3);
            $structure[1]['build_id'] = $used->id;
            $structure[2]['build_id'] = $used->id; // twice in one template still counts once
            PartyTemplate::create(['name' => "T{$n}", 'size' => 3, 'structure' => $structure, 'created_by' => $caller->id]);
        }

        $response = $this->actingAs($caller)->get(route('builds.index'))->assertOk();

        $this->assertSame([$used->id => 2], $response->viewData('usage'));
        $response->assertSee("2 template'te", false)->assertSee('kullanılmıyor')->assertSee('data-role="DPS"', false);
    }

    public function test_template_editor_offers_the_build_library(): void
    {
        $caller = User::factory()->creator()->create();
        $mace = $this->roles['1h Mace'];
        $mace->forceFill(['unique_name' => 'T8_MAIN_MACE'])->save();
        $build = new SavedBuild(['name' => 'Mace </script> Engage', 'role_category' => 'Tank', 'weapon_id' => $mace->id, 'abilities' => 'Q1']);
        $build->save();
        $structure = PartyTemplate::emptyStructure(2);
        $structure[1] = ['role' => '1h Mace', 'note' => 'Q1', 'type' => 'tank', 'build_id' => $build->id];
        $template = PartyTemplate::create(['name' => 'T', 'size' => 2, 'structure' => $structure, 'created_by' => $caller->id]);

        $response = $this->actingAs($caller)->get(route('templates.edit', $template))->assertOk();

        $library = $response->viewData('library');
        $this->assertSame(['id' => $build->id, 'type' => 'tank', 'role' => '1h Mace', 'icon' => '/item-icons/T8_MAIN_MACE.png'],
            collect($library->first())->only(['id', 'type', 'role', 'icon'])->all());
        $response->assertSee('name="slots[1][build_id]" class="f-build" value="'.$build->id.'"', false)
            ->assertDontSee('Mace </script> Engage', false); // JSON + HTML escaped
    }

    public function test_template_update_is_validated_and_normalised(): void
    {
        $caller = User::factory()->creator()->create();
        $template = PartyTemplate::create(['name' => 'T', 'size' => 2, 'structure' => PartyTemplate::emptyStructure(2), 'created_by' => $caller->id]);

        $this->actingAs($caller)->put(route('templates.update', $template), [
            'name' => 'T',
            'slots' => [1 => ['role' => 'x', 'type' => 'wizard']],
        ])->assertSessionHasErrors('slots.1.type');

        $this->actingAs($caller)->put(route('templates.update', $template), [
            'name' => 'ZvZ',
            'slots' => [
                1 => ['role' => ' Hallowfall ', 'note' => 'main heal', 'type' => 'heal', 'build_id' => ''],
                2 => ['role' => '', 'note' => '', 'type' => 'any'],
                99 => ['role' => 'ignored', 'type' => 'any'],
            ],
        ])->assertRedirect(route('templates.index'));

        $template->refresh();
        $this->assertSame('ZvZ', $template->name);
        $this->assertSame(['role' => 'Hallowfall', 'note' => 'main heal', 'type' => 'heal', 'build_id' => null], $template->structure[1]);
        $this->assertCount(2, $template->structure);
    }

    public function test_build_store_validates_slots_and_ignores_protected_fields(): void
    {
        $caller = User::factory()->creator()->create();
        $someoneElse = User::factory()->create();

        // A helmet is not a weapon.
        $this->actingAs($caller)->post(route('builds.store'), [
            'name' => 'Bad', 'role_category' => 'Tank', 'weapon_id' => $this->roles['Guardian Helmet']->id,
        ])->assertSessionHasErrors('weapon_id');

        $this->actingAs($caller)->post(route('builds.store'), [
            'name' => 'Mace',
            'role_category' => 'Tank',
            'weapon_id' => $this->roles['1h Mace']->id,
            'head_id' => $this->roles['Guardian Helmet']->id,
            'cape_id' => $this->roles['Martlock Cape']->id,
            'created_by' => $someoneElse->id,
            'id' => 999,
        ])->assertRedirect(route('builds.index'));

        $build = SavedBuild::sole();
        $this->assertNotSame(999, $build->id);
        $this->assertSame($caller->id, $build->created_by);
        $this->assertSame($this->roles['Guardian Helmet']->id, $build->head_id);
    }
}
