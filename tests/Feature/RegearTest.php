<?php

namespace Tests\Feature;

use App\Jobs\ScanRegearDeaths;
use App\Models\RegearClaim;
use App\Models\SavedBuild;
use App\Models\Setting;
use App\Models\SharedLink;
use App\Models\User;
use App\Services\Albion\GameInfoClient;
use App\Services\Albion\ItemType;
use App\Services\Regear\RegearEvaluator;
use App\Services\Regear\RegearHistory;
use App\Services\Regear\RegearScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        // The roster is arranged before the event's deaths (slot history starts "now").
        $this->travelTo(Carbon::parse('2026-11-08 19:00:00'));
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

    private function death(int $id, string $name, array $equipment, string $time = '2026-11-08T21:28:49Z', string $guild = self::GUILD, array $killer = []): array
    {
        $slots = [];
        foreach (['MainHand', 'OffHand', 'Head', 'Armor', 'Shoes', 'Bag', 'Cape', 'Mount', 'Potion', 'Food'] as $key) {
            $slots[$key] = isset($equipment[$key]) ? ['Type' => $equipment[$key], 'Count' => 1, 'Quality' => 4] : null;
        }

        return [
            'EventId' => $id, 'BattleId' => 900, 'TimeStamp' => $time,
            'Killer' => $killer + ['Name' => 'Enemy', 'GuildName' => 'Bad Guys', 'GuildId' => 'X', 'AllianceId' => '', 'AllianceName' => '',
                'Equipment' => ['MainHand' => ['Type' => 'T8_2H_HAMMER']]],
            'Victim' => ['Name' => $name, 'Id' => 'albion-'.$name, 'GuildId' => $guild, 'AverageItemPower' => 1450.7, 'Equipment' => $slots],
        ];
    }

    private function fakeKillboard(array $events): void
    {
        Http::fake([
            'gameinfo-ams.albiononline.com/api/gameinfo/battles*' => Http::response([
                ['id' => 900, 'startTime' => '2026-11-08T21:00:00Z', 'endTime' => '2026-11-08T21:40:00Z', 'players' => $this->players(50)],
                ['id' => 700, 'startTime' => '2026-11-08T20:10:00Z', 'endTime' => '2026-11-08T20:12:00Z', 'players' => $this->players(1)],
                ['id' => 800, 'startTime' => '2026-11-07T10:00:00Z', 'endTime' => '2026-11-07T10:30:00Z', 'players' => $this->players(50)],
            ]),
            'gameinfo-ams.albiononline.com/api/gameinfo/events/battle/900*' => Http::response($events),
        ]);
    }

    private function players(int $count): array
    {
        $players = [];
        for ($i = 0; $i < $count; $i++) {
            $players['p'.$i] = ['name' => 'P'.$i];
        }

        return $players;
    }

    public function test_small_battles_are_skipped_and_kills_by_others_are_flagged(): void
    {
        foreach (['Ally1', 'Ally2', 'Ally3', 'Ally4', 'Ganked', 'Gankweapon'] as $n => $ign) {
            $this->registerAttendee($this->link, ['1h Mace'], slot: null)->forceFill(['in_game_name' => $ign])->save();
        }
        $this->roles['Bow']->forceFill(['unique_name' => 'T8_2H_BOW'])->save();
        Setting::put('regear.gank_weapons', [$this->roles['Bow']->id]);
        $enemy = ['GuildName' => 'Big Zerg', 'GuildId' => 'G1', 'AllianceName' => 'ZERG', 'AllianceId' => 'A1'];

        $this->fakeKillboard([
            $this->death(1, 'Ally1', [], killer: $enemy),
            $this->death(2, 'Ally2', [], killer: $enemy),
            $this->death(3, 'Ally3', [], killer: $enemy),
            $this->death(4, 'Ally4', [], killer: ['GuildName' => 'Random Guild', 'GuildId' => 'G9']),
            $this->death(5, 'Ganked', [], killer: ['GuildName' => '', 'GuildId' => '']),
            $this->death(6, 'Gankweapon', [], killer: $enemy + ['Equipment' => ['MainHand' => ['Type' => 'T6_2H_BOW@2']]]),
        ]);

        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));

        $this->assertStringContainsString('2 kişiden küçük 1 savaş atlandı', $this->link->refresh()->regear_scan_status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'events/battle/700'));

        $warnings = RegearClaim::orderBy('event_id')->get()->mapWithKeys(fn ($c) => [$c->victim_name => implode(' | ', $c->warnings)]);
        $this->assertStringNotContainsString('Ana rakip', $warnings['Ally1']);
        $this->assertStringContainsString('Ana rakip dışında bir guild öldürdü (Random Guild)', $warnings['Ally4']);
        $this->assertStringContainsString('Guildsiz biri öldürdü', $warnings['Ganked']);
        $this->assertStringContainsString('Gank silahıyla öldürüldü (Bow)', $warnings['Gankweapon']);
        $this->assertSame(50, RegearClaim::first()->battle_size);
        $this->assertSame('A1', RegearClaim::first()->killer_alliance_id);

        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))
            ->assertSee('Ana rakip:')->assertSee('[ZERG]')->assertSee('50 kişilik savaş');
    }

    private function allyDeath(int $id, string $name, string $guildId, string $guildName, array $killer = []): array
    {
        $event = $this->death($id, $name, [], guild: $guildId, killer: $killer);
        $event['Victim'] += ['GuildName' => $guildName, 'AllianceId' => 'ALLY1'];

        return $event;
    }

    private function fakeAlliance(): void
    {
        Http::fake([
            'gameinfo-ams.albiononline.com/api/gameinfo/guilds/'.self::GUILD => Http::response(['Name' => 'Road Block', 'AllianceId' => 'ALLY1', 'AllianceTag' => 'N5FW']),
            'gameinfo-ams.albiononline.com/api/gameinfo/alliances/ALLY1' => Http::response([
                'AllianceTag' => 'N5FW', 'AllianceName' => 'Friends', 'Guilds' => [['Id' => self::GUILD, 'Name' => 'Road Block'], ['Id' => 'G2', 'Name' => 'Other Guild']],
            ]),
        ]);
    }

    public function test_alliance_deaths_are_only_included_when_the_party_allows_it(): void
    {
        $this->registerAttendee($this->link, ['1h Mace'], slot: null)->forceFill(['in_game_name' => 'AllyGuy'])->save();
        $this->fakeAlliance();
        $events = [
            $this->death(1, 'OurGuy', []),
            $this->allyDeath(2, 'AllyGuy', 'G2', 'Other Guild'),
            $this->allyDeath(3, 'AllyStranger', 'G2', 'Other Guild'),
        ];
        $this->fakeKillboard($events);

        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));
        $this->assertSame(['OurGuy'], RegearClaim::pluck('victim_name')->all());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'alliances/'));

        $this->actingAs($this->link->creator)->post(route('party.regear.window', $this->link), [
            'starts_at' => '2026-11-08T20:00:00Z', 'ends_at' => '2026-11-08T22:00:00Z', 'include_alliance' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($this->link->refresh()->regear_include_alliance);

        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));

        // Allies are taken only when they are on this party's roster.
        $this->assertSame(['OurGuy', 'AllyGuy'], RegearClaim::orderBy('event_id')->pluck('victim_name')->all());
        $ally = RegearClaim::where('victim_name', 'AllyGuy')->first();
        $this->assertSame(['G2', 'Other Guild', 'ALLY1'], [$ally->victim_guild_id, $ally->victim_guild, $ally->victim_alliance_id]);
        $this->assertNotNull($ally->link_attendee_id);
        $this->assertStringContainsString('alliance [N5FW] dahil', $this->link->refresh()->regear_scan_status);

        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))
            ->assertSee('🤝 Other Guild')->assertSee('Alliance [N5FW] Friends: Road Block, Other Guild');
    }

    public function test_guild_without_alliance_scans_only_the_guild(): void
    {
        $this->link->forceFill(['regear_include_alliance' => true])->save();
        Http::fake(['gameinfo-ams.albiononline.com/api/gameinfo/guilds/'.self::GUILD => Http::response(['Name' => 'Road Block', 'AllianceId' => ''])]);
        $this->fakeKillboard([$this->death(1, 'OurGuy', [])]);

        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));

        $this->assertSame(1, RegearClaim::count());
        $this->assertStringContainsString("guild bir alliance'ta değil", $this->link->refresh()->regear_scan_status);
    }

    public function test_our_own_alliance_is_never_the_main_enemy(): void
    {
        $friendly = ['GuildName' => 'Other Guild', 'GuildId' => 'G2', 'AllianceName' => 'N5FW', 'AllianceId' => 'ALLY1'];
        $enemy = ['GuildName' => 'Big Zerg', 'GuildId' => 'G1', 'AllianceName' => 'ZERG', 'AllianceId' => 'A1'];
        $events = [];
        foreach (range(1, 4) as $i) {
            $event = $this->death($i, 'Guy'.$i, [], killer: $i === 1 ? $enemy : $friendly);
            $event['Victim']['AllianceId'] = 'ALLY1';
            $events[] = $event;
        }
        $events[] = $this->death(5, 'Guy5', [], killer: $enemy);
        $this->fakeKillboard($events);

        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));

        $claims = RegearClaim::orderBy('event_id')->get();
        $this->assertSame([], RegearEvaluator::mainEnemies($claims->all())); // only 2 attributable kills left
        $this->assertStringContainsString('Kendi tarafından biri öldürdü', implode(' | ', $claims[1]->warnings));
    }

    public function test_finished_events_keep_the_build_they_were_played_with(): void
    {
        $build = SavedBuild::firstWhere('name', 'Mace Engage');
        $upcoming = SharedLink::factory()->withTemplate($this->template([1 => ['1h Mace', 'tank', $build->id]], size: 1))->create();
        $upcoming->forceFill(['starts_at' => '2026-11-10 20:00:00', 'ends_at' => '2026-11-10 22:00:00'])->save();
        $this->registerAttendee($this->link, ['1h Mace'], slot: 1)->forceFill(['in_game_name' => 'Tanky'])->save();

        $this->travelTo(Carbon::parse('2026-11-09 10:00:00')); // the event is over, the next one isn't
        $build->update(['updated_by' => $this->link->creator_id]); // not a gear change: nothing frozen
        $this->assertNull($this->link->refresh()->build_snapshots);

        $build->update(['weapon_id' => $this->roles['Bow']->id, 'name' => 'Bow Engage']);
        $build->update(['weapon_id' => $this->roles['Crossbow']->id]); // the first frozen version stays

        $this->assertSame('1h Mace', $this->link->refresh()->resolveBuilds()[$build->id]->weapon->name);
        $this->assertSame('Mace Engage', $this->link->resolveBuilds()[$build->id]->name);
        $this->assertSame('Crossbow', $upcoming->refresh()->resolveBuilds()[$build->id]->weapon->name);
        $this->assertNull($upcoming->build_snapshots);

        $build->delete();
        $this->assertSame('Mace Engage', $this->link->refresh()->resolveBuilds()[$build->id]->name);

        // Regear still checks against the build of the event.
        $this->fakeKillboard([$this->death(1, 'Tanky', ['MainHand' => 'T8_MAIN_MACE', 'Head' => 'T8_HEAD_PLATE_SET3', 'Cape' => 'T8_CAPEITEM_FW_MARTLOCK'])]);
        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));
        $weapon = collect(RegearClaim::first()->checks)->firstWhere('slot', 'weapon');
        $this->assertSame(['ok', '1h Mace'], [$weapon['match'], $weapon['expected']]);

        $this->actingAs($this->link->creator)->get(route('party.show', $this->link))->assertOk()->assertSee('Mace Engage');
    }

    public function test_scan_warns_when_the_killboard_page_limits_are_hit(): void
    {
        config(['services.albion.max_battle_pages' => 2, 'services.albion.max_pages_per_battle' => 1]);
        $battle = fn ($id) => ['id' => $id, 'startTime' => '2026-11-08T21:00:00Z', 'endTime' => '2026-11-08T21:40:00Z', 'players' => $this->players(50)];
        $events = array_map(fn ($i) => $this->death($i, 'Dead'.$i, []), range(1, GameInfoClient::PAGE_SIZE));
        Http::fake([
            // A full page of battles, all still inside the window, on every page.
            'gameinfo-ams.albiononline.com/api/gameinfo/battles*' => Http::response(array_fill(0, GameInfoClient::PAGE_SIZE, $battle(900))),
            'gameinfo-ams.albiononline.com/api/gameinfo/events/battle/900*' => Http::response($events),
        ]);

        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));

        $status = $this->link->refresh()->regear_scan_status;
        $this->assertStringStartsWith('done: 51 ölüm', $status);
        $this->assertStringContainsString('event başlangıcına kadar geriye gidilemedi', $status);
        $this->assertStringContainsString('hepsi okunamadı (sayfa limiti), bazı ölümler eksik olabilir: #900', $status);
        Http::assertSentCount(3); // 2 battle pages + 1 event page

        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))
            ->assertSee('⚠️ Killboard', false);
    }

    public function test_minimum_battle_size_defaults_to_party_size_and_can_be_changed(): void
    {
        $this->assertSame(2, $this->link->minBattleSize()); // 2 template slots in this fixture

        $this->actingAs($this->link->creator)->post(route('party.regear.window', $this->link), [
            'starts_at' => '2026-11-08T20:00:00Z', 'ends_at' => '2026-11-08T22:00:00Z', 'min_battle_size' => 60,
        ])->assertSessionHasNoErrors();
        $this->assertSame(60, $this->link->refresh()->minBattleSize());

        $this->fakeKillboard([$this->death(1, 'Someone', [])]);
        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));
        $this->assertSame(0, RegearClaim::count()); // the 50 player battle is now too small
    }

    public function test_gank_weapons_are_managed_by_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs(User::factory()->creator()->create())->put(route('settings.regear.gank'), ['gank_weapons' => [$this->roles['Bow']->id]])->assertForbidden();
        $this->actingAs($admin)->put(route('settings.regear.gank'), ['gank_weapons' => [$this->roles['Guardian Helmet']->id]])->assertSessionHasErrors('gank_weapons.0');

        $this->actingAs($admin)->put(route('settings.regear.gank'), ['gank_weapons' => [$this->roles['Bow']->id, $this->roles['Crossbow']->id]])
            ->assertRedirect(route('settings.regear'));
        $this->assertSame([$this->roles['Bow']->id, $this->roles['Crossbow']->id], Setting::get('regear.gank_weapons'));
        $this->actingAs($admin)->get(route('settings.regear'))->assertSee('Gank silahları')->assertSee('Crossbow');
    }

    public function test_the_same_death_cannot_be_approved_in_two_parties(): void
    {
        $other = SharedLink::factory()->create(['title' => 'Other CTA']);
        $make = fn (SharedLink $l) => RegearClaim::create([
            'shared_link_id' => $l->id, 'event_id' => 4242, 'victim_name' => 'Zero420', 'died_at' => now(),
            'equipment' => [], 'checks' => [], 'warnings' => [], 'suggested_amount' => 1_000_000,
        ]);
        $theirs = $make($other);
        $ours = $make($this->link);

        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))->assertSee('partisinde de var')->assertSee('henüz karar verilmedi');

        $theirs->forceFill(['status' => RegearClaim::APPROVED, 'amount' => 1_000_000, 'paid_at' => now()])->save();

        $this->actingAs($this->link->creator)->post(route('party.regear.decide', [$this->link, $ours]), ['action' => 'approve'])
            ->assertSessionHas('error', 'Bu ölüm "Other CTA" partisinde zaten onaylandı ve ödendi; aynı ölüm iki kez ödenemez.');
        $this->actingAs($this->link->creator)->post(route('party.regear.approve-clean', $this->link))->assertSessionHas('success', '0 uyarısız regear onaylandı.');
        $this->assertTrue($ours->refresh()->isPending());
        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))->assertSee('orada ödendi');
    }

    public function test_deaths_use_the_slot_held_at_the_moment_of_death(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 20:30:00'));
        $tank = $this->registerAttendee($this->link, ['1h Mace'], slot: 1);
        $tank->forceFill(['in_game_name' => 'Zero420'])->save();
        $leaver = $this->registerAttendee($this->link, ['Bow'], slot: 2);
        $leaver->forceFill(['in_game_name' => 'Leaver'])->save();

        // After the deaths: the leaver quits, the tank is moved into the DPS slot.
        $this->travelTo(Carbon::parse('2026-11-08 21:39:00'));
        $this->actingAs($leaver->user)->post(route('party.leave', $this->link));
        $this->travelTo(Carbon::parse('2026-11-08 21:40:00'));
        $tank->forceFill(['slot_index' => 2])->save();

        $this->travelTo(Carbon::parse('2026-11-08 22:30:00'));
        $this->fakeKillboard([
            $this->death(1, 'Zero420', ['MainHand' => 'T8_MAIN_MACE', 'Head' => 'T8_HEAD_PLATE_SET3', 'Cape' => 'T8_CAPEITEM_FW_MARTLOCK'], '2026-11-08T21:28:00Z'),
            $this->death(2, 'Leaver', ['MainHand' => 'T8_2H_BOW'], '2026-11-08T21:30:00Z'),
        ]);
        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));

        $tankClaim = RegearClaim::where('victim_name', 'Zero420')->sole();
        $this->assertSame([1, 'tank', 1_200_000], [$tankClaim->slot_index, $tankClaim->role_type, $tankClaim->suggested_amount]);
        $this->assertSame([], $tankClaim->warnings);

        $leaverClaim = RegearClaim::where('victim_name', 'Leaver')->sole();
        $this->assertNull($leaverClaim->link_attendee_id);
        $this->assertSame([2, 800_000], [$leaverClaim->slot_index, $leaverClaim->suggested_amount]);
        $this->assertContains('Öldükten sonra partiden ayrıldı ya da çıkarıldı (öldüğünde Slot 2\'teydi).', $leaverClaim->warnings);
    }

    public function test_players_placed_after_their_death_still_get_their_slot_with_a_note(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 23:00:00')); // caller fixes the roster after the event
        $this->registerAttendee($this->link, ['1h Mace'], slot: 1)->forceFill(['in_game_name' => 'Latefix'])->save();

        $this->fakeKillboard([$this->death(1, 'Latefix', ['MainHand' => 'T8_MAIN_MACE'], '2026-11-08T21:28:00Z')]);
        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));

        $claim = RegearClaim::sole();
        $this->assertSame([1, 1_200_000], [$claim->slot_index, $claim->suggested_amount]);
        $this->assertContains("Öldüğü anda slotta değildi; sonradan Slot 1'e yerleştirildi.", $claim->warnings);
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

    public function test_optional_limits_per_player_and_item_power(): void
    {
        $tank = $this->registerAttendee($this->link, ['1h Mace'], slot: 1);
        $tank->forceFill(['in_game_name' => 'Zero420'])->save();
        $gear = ['MainHand' => 'T8_MAIN_MACE', 'Head' => 'T8_HEAD_PLATE_SET3', 'Cape' => 'T8_CAPEITEM_FW_MARTLOCK'];
        $lowIp = $this->death(3, 'Zero420', $gear, '2026-11-08T21:55:00Z');
        $lowIp['Victim']['AverageItemPower'] = 1180.2;
        $this->fakeKillboard([$this->death(1, 'Zero420', $gear), $this->death(2, 'Zero420', $gear, '2026-11-08T21:50:00Z'), $lowIp]);

        $this->actingAs($this->link->creator)->post(route('party.regear.rules', $this->link), [
            'min_tier' => ['default' => 6], 'payouts' => ['tank' => '1.2m'], 'limits' => ['max_per_player' => 2, 'min_item_power' => 1200],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['max_per_player' => 2, 'min_item_power' => 1200], $this->link->refresh()->regearRules()['limits']);

        (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));
        $warnings = fn () => RegearClaim::orderBy('event_id')->get()->map(fn ($c) => implode(' | ', $c->warnings))->all();
        [$first, $second, $third] = $warnings();
        $this->assertSame('', $first);
        $this->assertSame('', $second);
        $this->assertStringContainsString("Bu event'teki 3. ölümü; oyuncu başına en fazla 2 regear.", $third);
        $this->assertStringContainsString('Ortalama IP 1180, en az 1200 olmalıydı.', $third);

        // Rejecting an earlier death frees a regear for the later one.
        $this->actingAs($this->link->creator)->post(route('party.regear.decide', [$this->link, RegearClaim::firstWhere('event_id', 1)]), ['action' => 'reject']);
        $this->assertStringNotContainsString('oyuncu başına', $warnings()[2]);

        $this->actingAs($this->link->creator)->post(route('party.regear.rules', $this->link), [
            'min_tier' => ['default' => 6], 'limits' => ['max_per_player' => 0, 'min_item_power' => 99],
        ])->assertSessionHasErrors(['limits.max_per_player', 'limits.min_item_power']);

        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))
            ->assertSee('oyuncu başına en fazla 2')->assertSee('min 1200 IP');
    }

    public function test_queue_waits_for_a_slow_scan_before_retrying_it(): void
    {
        $job = new ScanRegearDeaths(1);
        $this->assertGreaterThan($job->timeout, config('queue.connections.database.retry_after'));
        $this->assertGreaterThan($job->timeout, $job->uniqueFor);
    }

    public function test_battle_players_are_used_as_attendance_evidence(): void
    {
        $placed = $this->registerAttendee($this->link, ['1h Mace'], slot: 1);
        $placed->forceFill(['in_game_name' => 'Tanky'])->save();
        $bench = $this->registerAttendee($this->link, ['Bow'], slot: null);
        $bench->forceFill(['in_game_name' => 'BenchBow'])->save();
        $quiet = $this->registerAttendee($this->link, ['Bow'], slot: 2);
        $quiet->forceFill(['in_game_name' => 'QuietHealer'])->save();

        $players = ['a' => ['name' => 'Tanky', 'guildId' => self::GUILD, 'kills' => 2, 'deaths' => 1],
            'b' => ['name' => 'benchbow', 'guildId' => self::GUILD, 'kills' => 0, 'deaths' => 0],
            'c' => ['name' => 'Freeloader', 'guildId' => self::GUILD, 'kills' => 1, 'deaths' => 0],
            'd' => ['name' => 'Enemy1', 'guildId' => 'G1', 'kills' => 5, 'deaths' => 0]]
            + $this->players(50);
        Http::fake([
            'gameinfo-ams.albiononline.com/api/gameinfo/battles*' => Http::response([
                ['id' => 900, 'startTime' => '2026-11-08T21:00:00Z', 'endTime' => '2026-11-08T21:40:00Z', 'players' => $players],
            ]),
            'gameinfo-ams.albiononline.com/api/gameinfo/events/battle/900*' => Http::response([]),
        ]);

        $scan = fn () => (new ScanRegearDeaths($this->link->id))->handle(app(RegearScanner::class));
        $scan();
        $this->link->refresh();
        $this->assertSame(['benchbow', 'freeloader', 'tanky'], array_keys($this->link->battle_players));
        $this->assertSame(['name' => 'Tanky', 'battles' => 1, 'kills' => 2, 'deaths' => 1], $this->link->battlePlayer('TANKY'));
        $this->assertStringContainsString('savaşta 3 oyuncumuz görüldü', $this->link->regear_scan_status);

        // Archive: placed players + anyone seen fighting (even from the waitlist).
        $this->actingAs($this->link->creator)->post(route('dashboard.archive', $this->link));
        $this->assertTrue($bench->refresh()->attended);
        $this->assertTrue($quiet->refresh()->attended); // not on the killboard, but never marked absent automatically

        $this->actingAs($this->link->creator)->get(route('party.board', $this->link))
            ->assertSee('On killboard 2/3')->assertSee('Fought without signing up (1)')->assertSee('Freeloader')
            ->assertSee('Not on the killboard of this event', false);

        // A scan after archiving marks the ones seen fighting.
        $bench->forceFill(['attended' => false])->save();
        $scan();
        $this->assertTrue($bench->refresh()->attended);
        $this->assertStringContainsString('1 kişi katıldı işaretlendi', $this->link->refresh()->regear_scan_status);
    }

    public function test_regear_history_per_player_across_events(): void
    {
        $other = SharedLink::factory()->create(['title' => 'Last week ZvZ']);
        $claim = fn (SharedLink $link, int $event, string $name, string $status, ?int $amount = null, bool $paid = false) => RegearClaim::forceCreate([
            'shared_link_id' => $link->id, 'event_id' => $event, 'battle_id' => 1, 'victim_name' => $name, 'died_at' => '2026-11-01 20:00:00',
            'status' => $status, 'amount' => $amount, 'suggested_amount' => 1_000_000, 'equipment' => [], 'checks' => [], 'warnings' => [],
            'paid_at' => $paid ? now() : null,
        ]);
        $claim($other, 1, 'Zero420', RegearClaim::APPROVED, 1_500_000, paid: true);
        $claim($other, 2, 'zero420', RegearClaim::APPROVED); // suggested amount counts
        $claim($other, 3, 'Zero420', RegearClaim::REJECTED);
        $claim($this->link, 4, 'Zero420', RegearClaim::PENDING);
        $claim($other, 5, 'Someone', RegearClaim::PENDING);

        $this->assertSame(['zero420' => ['deaths' => 3, 'events' => 1, 'approved' => 2, 'rejected' => 1, 'silver' => 2_500_000]],
            RegearHistory::summaries(['ZERO420'], $this->link->id));

        $staff = $this->link->creator;
        $this->actingAs($staff)->get(route('party.regear', [$this->link, 'tab' => 'all']))
            ->assertSee('📒 Geçmiş: 3 ölüm · 2 onay · 1 red · 2.5M')->assertSee(route('regear.player', 'Zero420'), false);

        $this->actingAs($staff)->get(route('regear.players'))->assertOk()->assertSee('Zero420')->assertSee('2.5M')->assertSee('1M')->assertSee('Someone');
        $this->actingAs($staff)->get(route('regear.players', ['q' => 'some']))->assertSee('Someone')->assertDontSee('Zero420');
        $this->actingAs($staff)->get(route('regear.player', 'zero420'))->assertOk()
            ->assertSee('Last week ZvZ')->assertSee('Reddedildi')->assertSee('Ödendi')->assertSee('Ödenmedi');
        $this->actingAs($staff)->get(route('regear.player', 'Nobody'))->assertNotFound();

        $this->actingAs(User::factory()->create())->get(route('regear.players'))->assertRedirect(route('home'));
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
