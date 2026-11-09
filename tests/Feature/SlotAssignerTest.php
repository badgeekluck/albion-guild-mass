<?php

namespace Tests\Feature;

use App\Models\LinkAttendee;
use App\Models\SavedBuild;
use App\Models\SharedLink;
use App\Services\SlotAssigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class SlotAssignerTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    private SlotAssigner $assigner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
        $this->assigner = new SlotAssigner;
    }

    private function link(array $slots): SharedLink
    {
        return SharedLink::factory()->withTemplate($this->template($slots))->create();
    }

    public function test_exact_weapon_match_is_not_forced(): void
    {
        $link = $this->link([1 => ['Hallowfall', 'heal']]);
        $attendee = $this->registerAttendee($link, ['Fallen Staff', 'Hallowfall']);

        $result = $this->assigner->assign($attendee, $link, 1);

        $this->assertSame(['slot_index' => 1, 'assigned_role' => 'Hallowfall', 'is_forced' => false], $result);
    }

    public function test_similar_names_do_not_match_anymore(): void
    {
        // Old substring logic matched "Bow" with "Crossbow" / "Longbow".
        $link = $this->link([1 => ['Bow', 'dps']]);
        $attendee = $this->registerAttendee($link, ['Crossbow', 'Longbow']);

        $result = $this->assigner->assign($attendee, $link, 1);

        $this->assertTrue($result['is_forced']);
        $this->assertSame('Bow', $result['assigned_role']);
    }

    public function test_slot_label_suffixes_are_ignored(): void
    {
        $link = $this->link([1 => ['1h Mace - Engage', 'tank']]);
        $attendee = $this->registerAttendee($link, ['Heavy Mace', '1h Mace']);

        $result = $this->assigner->assign($attendee, $link, 1);

        $this->assertSame('1h Mace', $result['assigned_role']);
        $this->assertFalse($result['is_forced']);
    }

    public function test_build_slot_matches_on_build_weapon(): void
    {
        $build = new SavedBuild(['name' => 'Mace Engage', 'role_category' => 'Tank', 'weapon_id' => $this->roles['1h Mace']->id]);
        $build->save();
        $link = $this->link([1 => ['Mace Engage', 'tank', $build->id]]);

        $match = $this->assigner->assign($this->registerAttendee($link, ['Heavy Mace', '1h Mace']), $link, 1);
        $miss = $this->assigner->assign($this->registerAttendee($link, ['Heavy Mace', 'Locus']), $link, 1);

        $this->assertSame(['slot_index' => 1, 'assigned_role' => '1h Mace', 'is_forced' => false], $match);
        $this->assertSame(['slot_index' => 1, 'assigned_role' => 'Mace Engage', 'is_forced' => true], $miss);
    }

    public function test_category_label_accepts_any_weapon_of_that_category(): void
    {
        $link = $this->link([1 => ['Healer', 'heal']]);

        $result = $this->assigner->assign($this->registerAttendee($link, ['Bow', 'Fallen Staff']), $link, 1);

        $this->assertSame('Fallen Staff', $result['assigned_role']);
        $this->assertFalse($result['is_forced']);
    }

    public function test_fill_rules(): void
    {
        $link = $this->link([1 => ['Heavy Mace', 'tank'], 2 => ['Great Frost Staff', 'dps']]);

        $fill = $this->assigner->assign($this->registerAttendee($link, [LinkAttendee::ROLE_FILL]), $link, 2);
        $fillTankOnTank = $this->assigner->assign($this->registerAttendee($link, [LinkAttendee::ROLE_FILL_TANK]), $link, 1);
        $fillTankOnDps = $this->assigner->assign($this->registerAttendee($link, [LinkAttendee::ROLE_FILL_TANK]), $link, 2);

        $this->assertSame(['slot_index' => 2, 'assigned_role' => 'Great Frost Staff', 'is_forced' => false], $fill);
        $this->assertSame(['slot_index' => 1, 'assigned_role' => 'Heavy Mace', 'is_forced' => false], $fillTankOnTank);
        $this->assertTrue($fillTankOnDps['is_forced']);
    }

    public function test_open_extra_and_bombsquad_slots(): void
    {
        $link = $this->link([1 => ['', 'any'], 2 => ['Hallowfall', 'heal']]);
        $link->forceFill(['extra_slots' => 1])->save();

        $open = $this->assigner->assign($this->registerAttendee($link, ['Bow', 'Locus']), $link, 1);
        $extra = $this->assigner->assign($this->registerAttendee($link, ['Bow', 'Locus']), $link, 21);
        $bomb = $this->assigner->assign($this->registerAttendee($link, [LinkAttendee::ROLE_BOMBSQUAD]), $link, 2);
        $waitlist = $this->assigner->assign($this->registerAttendee($link, ['Bow', 'Locus']), $link, 0);

        $this->assertSame(['slot_index' => 1, 'assigned_role' => 'Bow', 'is_forced' => false], $open);
        $this->assertSame(['slot_index' => 21, 'assigned_role' => 'Bow', 'is_forced' => false], $extra);
        $this->assertSame(['slot_index' => 2, 'assigned_role' => 'Bombsquad', 'is_forced' => false], $bomb);
        $this->assertSame(['slot_index' => null, 'assigned_role' => null, 'is_forced' => false], $waitlist);
    }
}
