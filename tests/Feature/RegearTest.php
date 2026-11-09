<?php

namespace Tests\Feature;

use App\Jobs\ScanRegearDeaths;
use App\Models\RegearClaim;
use App\Models\SavedBuild;
use App\Models\SharedLink;
use App\Models\User;
use App\Services\Albion\GameInfoClient;
use App\Services\Albion\ItemType;
use App\Services\Regear\RegearEvaluator;
use App\Services\Regear\RegearScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class RegearTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    private const GUILD = 'GUILD123';

    private SharedLink $link;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
        config(['services.albion.guild_id' => self::GUILD, 'services.albion.region' => 'europe']);

        $ids = ['1h Mace' => 'T8_MAIN_MACE', 'Heavy Mace' => 'T8_2H_MACE', 'Guardian Helmet' => 'T8_HEAD_PLATE_SET3', 'Martlock Cape' => 'T8_CAPEITEM_FW_MARTLOCK'];
        foreach ($ids as $name => $unique) {
            $this->roles[$name]->forceFill(['unique_name' => $unique])->save();
        }

        $build = new SavedBuild([
            'name' => 'Mace Engage', 'role_category' => 'Tank', 'weapon_id' => $this->roles['1h Mace']->id,
            'head_id' => $this->roles['Guardian Helmet']->id, 'cape_id' => $this->roles['Martlock Cape']->id,
            'swaps' => [['situation' => 'kite', 'slot' => 'weapon', 'item_id' => $this->roles['Heavy Mace']->id, 'text' => '']],
        ]);
        $build->save();

        $this->link = SharedLink::factory()->withTemplate($this->template([1 => ['1h Mace', 'tank', $build->id], 2 => ['Bow', 'dps']], size: 2))->create();
        $this->link->forceFill([
            'starts_at' => '2026-11-08 20:00:00', 'ends_at' => '2026-11-08 22:00:00',
            'regear_rules' => ['min_tier' => ['default' => 6, 'tank' => 7], 'payouts' => ['tank' => 1_200_000, 'dps' => 800_000]],
        ])->save();
    }

    private function death(int $id, string $name, array $equipment, string $time = '2026-11-08T21:28:49Z', string $guild = self::GUILD): array
    {
        $slots = [];
        foreach (['MainHand', 'OffHand', 'Head', 'Armor', 'Shoes', 'Bag', 'Cape', 'Mount', 'Potion', 'Food'] as $key) {
            $slots[$key] = isset($equipment[$key]) ? ['Type' => $equipment[$key], 'Count' => 1, 'Quality' => 4] : null;
        }

        return [
            'EventId' => $id, 'BattleId' => 900, 'TimeStamp' => $time,
            'Killer' => ['Name' => 'Enemy', 'GuildName' => 'Bad Guys', 'GuildId' => 'X'],
            'Victim' => ['Name' => $name, 'Id' => 'albion-'.$name, 'GuildId' => $guild, 'AverageItemPower' => 1450.7, 'Equipment' => $slots],
        ];
    }

    private function fakeKillboard(array $events): void
    {
        Http::fake([
            'gameinfo-ams.albiononline.com/api/gameinfo/battles*' => Http::response([
                ['id' => 900, 'startTime' => '2026-11-08T21:00:00Z', 'endTime' => '2026-11-08T21:40:00Z'],
                ['id' => 800, 'startTime' => '2026-11-07T10:00:00Z', 'endTime' => '2026-11-07T10:30:00Z'],
            ]),
            'gameinfo-ams.albiononline.com/api/gameinfo/events/battle/900*' => Http::response($events),
        ]);
    }

    public function test_item_ids_are_parsed_into_tier_equivalents(): void
    {
        $item = ItemType::parse('T5_MAIN_ARCANESTAFF_UNDEAD@3');

        $this->assertSame(['MAIN_ARCANESTAFF_UNDEAD', 5, 3, 8, '5.3'], [$item->base, $item->tier, $item->enchant, $item->equivalentTier(), $item->label()]);
        $this->assertTrue($item->sameItemAs('T8_MAIN_ARCANESTAFF_UNDEAD'));
        $this->assertNull(ItemType::parse('garbage'));
    }

    public function test_scan_matches_roster_deaths_and_checks_the_build(): void
    {
        $tank = $this->registerAttendee($this->link, ['1h Mace', 'Heavy Mace'], slot: 1);
        $tank->forceFill(['in_game_name' => 'Zero420'])->save();
        $kiter = $this->registerAttendee($this->link, ['1h Mace', 'Heavy Mace'], slot: 2);
        $kiter->forceFill(['in_game_name' => 'Kiter'])->save();

        $this->fakeKillboard([
            // correct build, 7.0+ everywhere
            $this->death(1, 'zero420', ['MainHand' => 'T6_MAIN_MACE@2', 'Head' => 'T7_HEAD_PLATE_SET3', 'Cape' => 'T4_CAPEITEM_FW_MARTLOCK@1', 'Bag' => 'T8_BAG', 'Potion' => 'T7_POTION_REVIVE']),
            // second death of the same player: swap weapon is fine, helmet is the wrong item and under tier
            $this->death(2, 'Zero420', ['MainHand' => 'T7_2H_MACE', 'Head' => 'T5_HEAD_LEATHER_SET1@1', 'Cape' => 'T8_CAPEITEM_FW_MARTLOCK'], '2026-11-08T21:35:00Z'),
            // a guild member who did not sign up
            $this->death(3, 'Randomer', ['MainHand' => 'T8_2H_BOW']),
            // enemy death and a death outside the event window are ignored
            $this->death(4, 'Enemy', ['MainHand' => 'T8_2H_BOW'], guild: 'OTHER'),
            $this->death(5, 'Zero420', ['MainHand' => 'T8_MAIN_MACE'], '2026-11-08T22:30:00Z'),
        ]);

        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));

        $this->assertStringStartsWith('done: 3 ölüm (3 yeni), 1 savaş', $this->link->refresh()->regear_scan_status);
        $claims = RegearClaim::orderBy('event_id')->get();
        $this->assertCount(3, $claims);

        [$clean, $mixed, $stranger] = $claims;
        $this->assertSame($tank->id, $clean->link_attendee_id);
        $this->assertSame([], $clean->warnings);
        $this->assertSame(1_200_000, $clean->suggested_amount);
        $this->assertSame('tank', $clean->role_type);
        $this->assertSame(['weapon' => 'ok', 'offhand' => 'unchecked', 'head' => 'ok', 'cape' => 'ok'],
            collect($clean->checks)->pluck('match', 'slot')->only(['weapon', 'head', 'cape', 'offhand'])->all());

        $checks = collect($mixed->checks)->keyBy('slot');
        $this->assertSame('alt', $checks['weapon']['match']);
        $this->assertSame(['wrong', false], [$checks['head']['match'], $checks['head']['tier_ok']]);
        $this->assertCount(2, $mixed->warnings); // wrong helmet + helmet under T7

        $this->assertNull($stranger->link_attendee_id);
        $this->assertSame(0, $stranger->suggested_amount);
        $this->assertStringContainsString('Roster', $stranger->warnings[0]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'events/battle/800'));
    }

    public function test_rescan_keeps_decisions_and_settings_reevaluate_pending_claims(): void
    {
        $tank = $this->registerAttendee($this->link, ['1h Mace'], slot: 1);
        $tank->forceFill(['in_game_name' => 'Zero420'])->save();
        $this->fakeKillboard([
            $this->death(1, 'Zero420', ['MainHand' => 'T8_MAIN_MACE', 'Head' => 'T8_HEAD_PLATE_SET3', 'Cape' => 'T8_CAPEITEM_FW_MARTLOCK']),
            $this->death(2, 'Zero420', ['MainHand' => 'T8_MAIN_MACE', 'Head' => 'T8_HEAD_PLATE_SET3', 'Cape' => 'T8_CAPEITEM_FW_MARTLOCK'], '2026-11-08T21:50:00Z'),
        ]);
        $scan = fn () => (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));
        $scan();

        $first = RegearClaim::where('event_id', 1)->sole();
        $this->actingAs($this->link->creator)->post(route('party.regear.decide', [$this->link, $first]), ['action' => 'approve', 'amount' => '1.5m'])
            ->assertSessionHas('success');
        $this->assertSame([RegearClaim::APPROVED, 1_500_000], [$first->refresh()->status, $first->amount]);

        $this->actingAs($this->link->creator)->post(route('party.regear.rules', $this->link), [
            'min_tier' => ['default' => 6], 'payouts' => ['tank' => '2m', 'dps' => '800k'],
        ])->assertSessionHasNoErrors();

        $scan();
        $this->assertSame(1_500_000, $first->refresh()->amount); // decision kept
        $this->assertSame(2_000_000, RegearClaim::where('event_id', 2)->sole()->suggested_amount); // pending re-evaluated
        $this->assertSame(2, RegearClaim::count());
    }

    public function test_review_flow_and_permissions(): void
    {
        Queue::fake();
        $caller = $this->link->creator;
        $claim = RegearClaim::create([
            'shared_link_id' => $this->link->id, 'event_id' => 1, 'victim_name' => 'Zero420', 'died_at' => now(),
            'equipment' => [], 'checks' => [], 'warnings' => [], 'suggested_amount' => 1_200_000,
        ]);
        RegearClaim::create([
            'shared_link_id' => $this->link->id, 'event_id' => 2, 'victim_name' => 'Other', 'died_at' => now(),
            'equipment' => [], 'checks' => [], 'warnings' => ['Roster\'da yok'], 'suggested_amount' => 0,
        ]);

        $this->actingAs(User::factory()->creator()->create())->get(route('party.regear', $this->link))->assertForbidden();

        $this->actingAs($caller)->get(route('party.regear', $this->link))->assertOk()->assertSee('Zero420')->assertSee('1.2M');

        $this->actingAs($caller)->post(route('party.regear.scan', $this->link))->assertSessionHas('success');
        Queue::assertPushed(ScanRegearDeaths::class);

        $this->actingAs($caller)->post(route('party.regear.approve-clean', $this->link))->assertSessionHas('success', '1 uyarısız regear onaylandı.');
        $this->assertSame(RegearClaim::APPROVED, $claim->refresh()->status);

        $this->actingAs($caller)->get(route('party.regear', $this->link))->assertSee("Zero420\t1200000", false);

        $this->actingAs($caller)->post(route('party.regear.paid', $this->link))->assertSessionHas('success', '1 regear ödendi olarak işaretlendi.');
        $this->assertNotNull($claim->refresh()->paid_at);
        $this->actingAs($caller)->post(route('party.regear.decide', [$this->link, $claim]), ['action' => 'reject'])->assertSessionHas('error');
    }

    public function test_event_window_and_rules_are_validated(): void
    {
        $this->actingAs($this->link->creator)->post(route('party.regear.window', $this->link), [
            'starts_at' => '2026-11-08T22:00:00Z', 'ends_at' => '2026-11-08T20:00:00Z',
        ])->assertSessionHasErrors('ends_at');

        // The browser sends UTC ISO strings (converted from the caller's local time).
        $this->actingAs($this->link->creator)->post(route('party.regear.window', $this->link), [
            'starts_at' => '2026-11-08T18:00:00.000Z', 'ends_at' => '2026-11-08T20:30:00.000Z',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-08 18:00', $this->link->refresh()->starts_at->utc()->format('Y-m-d H:i'));

        $this->actingAs($this->link->creator)->post(route('party.regear.rules', $this->link), [
            'min_tier' => ['default' => 13, 'tank' => ['armor' => 3]], 'payouts' => ['tank' => 'lots'],
        ])->assertSessionHasErrors(['min_tier.default', 'min_tier.tank.armor']);
        $this->actingAs($this->link->creator)->post(route('party.regear.rules', $this->link), [
            'min_tier' => ['default' => 7], 'payouts' => ['tank' => 'lots'],
        ])->assertSessionHasErrors('payouts.tank');
    }

    public function test_guild_defaults_apply_unless_the_party_overrides_them(): void
    {
        $admin = User::factory()->admin()->create();
        $this->link->forceFill(['regear_rules' => null])->save();

        $this->actingAs(User::factory()->creator()->create())->get(route('settings.regear'))->assertOk()->assertSee('sadece admin');
        $this->actingAs(User::factory()->creator()->create())->put(route('settings.regear.update'), [
            'min_tier' => ['default' => 6], 'payouts' => ['tank' => '1m'],
        ])->assertForbidden();

        $this->actingAs($admin)->put(route('settings.regear.update'), [
            'min_tier' => ['default' => 6, 'tank' => ['default' => 8]], 'payouts' => ['tank' => '1.5m', 'heal' => '1m'],
        ])->assertRedirect(route('settings.regear'));

        $this->assertSame([1_500_000, 8, 6], [$this->link->payoutFor('tank'), $this->link->minTierFor('tank'), $this->link->minTierFor('dps')]);

        $this->actingAs($this->link->creator)->post(route('party.regear.rules', $this->link), [
            'min_tier' => ['default' => 7], 'payouts' => ['tank' => '2m'],
        ]);
        $this->assertSame([2_000_000, 7], [$this->link->refresh()->payoutFor('tank'), $this->link->minTierFor('dps')]);

        $this->actingAs($this->link->creator)->post(route('party.regear.rules', $this->link), ['use_defaults' => '1']);
        $this->assertFalse($this->link->refresh()->hasRegearOverride());
        $this->assertSame(1_500_000, $this->link->payoutFor('tank'));
    }

    public function test_minimum_tiers_per_role_and_gear_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $this->link->forceFill(['regear_rules' => null])->save();

        // Guild rules: tank weapon T7 / chest T9, dps weapon T9 / other armor T8, support weapon T7 / chest T9, healer T8.
        $this->actingAs($admin)->put(route('settings.regear.update'), [
            'min_tier' => [
                'default' => 7,
                'tank' => ['default' => '', 'weapon' => 7, 'head' => '', 'armor' => 9, 'shoe' => ''],
                'dps' => ['default' => 8, 'weapon' => 9],
                'supp' => ['weapon' => 7, 'armor' => 9],
                'heal' => ['default' => 8],
            ],
            'payouts' => ['tank' => '1m'],
        ])->assertSessionHasNoErrors();

        $tier = fn ($role, $slot) => $this->link->minTierFor($role, $slot);
        $this->assertSame([7, 9, 7, 7], [$tier('tank', 'weapon'), $tier('tank', 'armor'), $tier('tank', 'head'), $tier('tank', 'offhand')]);
        $this->assertSame([9, 9, 8, 8, 8], [$tier('dps', 'weapon'), $tier('dps', 'offhand'), $tier('dps', 'head'), $tier('dps', 'armor'), $tier('dps', 'shoe')]);
        $this->assertSame([7, 9, 7], [$tier('supp', 'weapon'), $tier('supp', 'armor'), $tier('supp', 'shoe')]);
        $this->assertSame([8, 8], [$tier('heal', 'weapon'), $tier('heal', 'shoe')]);
        $this->assertSame(7, $tier(null, 'armor'));

        // A tank in 8.0 chest is under T9; a 6.3 chest (= T9) is fine.
        $tank = $this->registerAttendee($this->link, ['1h Mace'], slot: 1);
        $evaluate = fn ($armor) => app(RegearEvaluator::class)->evaluate($this->link->refresh(), $tank, [
            'MainHand' => ['Type' => 'T7_MAIN_MACE'], 'Head' => ['Type' => 'T7_HEAD_PLATE_SET3'], 'Armor' => ['Type' => $armor],
            'Cape' => ['Type' => 'T4_CAPEITEM_FW_MARTLOCK'],
        ]);
        $low = collect($evaluate('T8_ARMOR_PLATE_SET3')['checks'])->firstWhere('slot', 'armor');
        $this->assertSame([false, 9], [$low['tier_ok'], $low['min_tier']]);
        $this->assertContains('Zırh: 8.0 giyilmiş (T8 sayılır), en az T9 olmalıydı.', $evaluate('T8_ARMOR_PLATE_SET3')['warnings']);
        $this->assertTrue(collect($evaluate('T6_ARMOR_PLATE_SET3@3')['checks'])->firstWhere('slot', 'armor')['tier_ok']);

        $this->actingAs($admin)->get(route('settings.regear'))->assertOk()->assertSee('Göğüslük');
        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))->assertOk()
            ->assertSee('Tank silah T7, göğüs T9', false);
    }

    public function test_admin_finds_and_selects_the_guild_from_the_ui(): void
    {
        config(['services.albion.guild_id' => null]);
        $admin = User::factory()->admin()->create();
        Http::fake(['gameinfo-ams.albiononline.com/api/gameinfo/search*' => Http::response([
            'guilds' => [['Id' => 'B9WNSdhkQ_u0kxGG_gr-RQ', 'Name' => 'Road Block', 'AllianceName' => '']], 'players' => [],
        ])]);

        $this->actingAs($admin)->get(route('settings.regear', ['guild_search' => 'Road Block', 'region' => 'europe']))
            ->assertOk()->assertSee('Road Block')->assertSee('Bu guild\'i seç', false);

        $this->actingAs($admin)->post(route('settings.regear.guild'), ['id' => 'B9WNSdhkQ_u0kxGG_gr-RQ', 'name' => 'Road Block', 'region' => 'europe'])
            ->assertRedirect(route('settings.regear'));

        $this->assertSame('B9WNSdhkQ_u0kxGG_gr-RQ', GameInfoClient::guildId());
        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))->assertDontSee('henüz seçilmedi');
    }

    public function test_party_creation_can_set_the_event_time(): void
    {
        $this->actingAs(User::factory()->creator()->create())->post(route('dashboard.create'), [
            'title' => 'ZvZ', 'type' => 'cta', 'starts_at' => '2026-11-09T18:30:00.000Z', 'duration_hours' => '1.5',
        ])->assertSessionHas('success');

        $link = SharedLink::latest('id')->first();
        $this->assertSame(['18:30', '20:00'], [$link->starts_at->utc()->format('H:i'), $link->ends_at->utc()->format('H:i')]);
    }

    public function test_silver_amounts_are_parsed_and_formatted(): void
    {
        $this->assertSame([1_200_000, 900_000, 1_250_000, 0, null], array_map([RegearClaim::class, 'parseSilver'], ['1.2m', '900k', '1 250 000', '', 'abc']));
        $this->assertSame(['1.25M', '900K', '500'], array_map([RegearClaim::class, 'formatSilver'], [1_250_000, 900_000, 500]));
    }
}
