<?php

namespace Tests\Feature;

use App\Models\RegearClaim;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class RegearNotifyTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    private SharedLink $link;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
        config(['services.discord.bot_token' => 'bot-token']);
        $this->link = SharedLink::factory()->create(['title' => 'Friday ZvZ']);
        $this->player = User::factory()->create(['discord_id' => '555']);
        $this->registerAttendee($this->link, ['Bow', 'Crossbow'], $this->player, slot: 1)->forceFill(['in_game_name' => 'Zero420'])->save();
    }

    private function claim(int $event, string $name, string $time = '2026-11-08 21:28:00'): RegearClaim
    {
        return RegearClaim::forceCreate([
            'shared_link_id' => $this->link->id, 'event_id' => $event, 'battle_id' => 1, 'victim_name' => $name, 'died_at' => $time,
            'status' => RegearClaim::PENDING, 'suggested_amount' => 1_200_000, 'equipment' => [], 'checks' => [], 'warnings' => [],
            'link_attendee_id' => $this->link->attendees()->where('in_game_name', $name)->value('id'),
        ]);
    }

    private function fakeDiscord(int $messageStatus = 200): void
    {
        Http::fake([
            'discord.com/api/v10/users/@me/channels' => Http::response(['id' => 'dm-1']),
            'discord.com/api/v10/channels/dm-1/messages' => $messageStatus === 200
                ? Http::response(['id' => 'm'])
                : Http::response(['message' => 'Cannot send messages to this user', 'code' => 50007], $messageStatus),
        ]);
    }

    private function decide(RegearClaim $claim, string $action, array $extra = []): void
    {
        $this->actingAs($this->link->creator)->post(route('party.regear.decide', [$this->link, $claim]), ['action' => $action] + $extra)->assertSessionHas('success');
    }

    private function sentMessages(): array
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/messages'))->map(fn ($pair) => $pair[0]['content'])->values()->all();
    }

    public function test_players_get_one_dm_with_their_decisions_and_another_when_paid(): void
    {
        $this->fakeDiscord();
        $first = $this->claim(1, 'Zero420');
        $second = $this->claim(2, 'Zero420', '2026-11-08 21:50:00');
        $this->decide($first, 'approve', ['amount' => '1.2m']);
        $this->decide($second, 'reject', ['reason' => 'build dışı silah']);

        $this->actingAs($this->link->creator)->get(route('party.regear', [$this->link, 'tab' => 'all']))
            ->assertSee('📨 Oyunculara bildir (1)')->assertSee('build dışı silah');

        $this->actingAs($this->link->creator)->post(route('party.regear.notify', $this->link))
            ->assertSessionHas('success', '1 oyuncuya regear sonucu gönderiliyor.');

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('💰 **Friday ZvZ** regear sonucun:', $messages[0]);
        $this->assertStringContainsString('ölümün: **1.2M** onaylandı', $messages[0]);
        $this->assertStringContainsString('reddedildi — *build dışı silah*', $messages[0]);
        $this->assertStringContainsString('<t:'.$first->died_at->timestamp.':t>', $messages[0]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/users/@me/channels') && $r['recipient_id'] === '555');
        $this->assertNotNull($first->refresh()->notified_at);
        $this->assertFalse($first->needsNotification());

        // Nothing new: the button is disabled. A changed decision is sent again as an update.
        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))->assertDontSee('📨 Oyunculara bildir (');
        $this->decide($first, 'reset');
        $this->decide($first, 'approve', ['amount' => '1.5m']);
        $this->actingAs($this->link->creator)->post(route('party.regear.notify', $this->link));
        $this->assertStringContainsString('(güncellendi)', $this->sentMessages()[1]);
        $this->assertStringContainsString('**1.5M** onaylandı', $this->sentMessages()[1]);

        $this->actingAs($this->link->creator)->post(route('party.regear.paid', $this->link))
            ->assertSessionHas('success', "1 regear ödendi olarak işaretlendi. 1 oyuncuya Discord'dan haber veriliyor.");
        $this->assertStringContainsString("💸 **Friday ZvZ** regear'ın ödendi: **1.5M**", $this->sentMessages()[2]);
        $this->assertNotNull($first->refresh()->paid_notified_at);
    }

    public function test_closed_dms_and_unknown_players_are_reported_on_the_review_screen(): void
    {
        $this->fakeDiscord(403);
        $this->decide($closed = $this->claim(1, 'Zero420'), 'approve');
        $this->decide($stranger = $this->claim(2, 'NotOnRoster'), 'reject');
        $dev = User::factory()->create(['discord_id' => User::DEV_DISCORD_PREFIX.'7']);
        $this->registerAttendee(SharedLink::factory()->create(), ['Bow', 'Crossbow'], $dev)->forceFill(['in_game_name' => 'DevGuy'])->save();
        $this->decide($devClaim = $this->claim(3, 'DevGuy'), 'approve');

        $this->actingAs($this->link->creator)->post(route('party.regear.notify', $this->link))
            ->assertSessionHas('success', '1 oyuncuya regear sonucu gönderiliyor. 2 oyuncunun Discord hesabı bulunamadı.');

        $this->assertSame('dm_closed', $closed->refresh()->notify_error);
        $this->assertSame('no_discord', $stranger->refresh()->notify_error);
        $this->assertSame('no_discord', $devClaim->refresh()->notify_error);
        $this->assertTrue($closed->needsNotification()); // can be retried later

        $this->actingAs($this->link->creator)->get(route('party.regear', [$this->link, 'tab' => 'all']))
            ->assertSee('DM kapalı: 1')->assertSee('Discord hesabı bulunamadı: 2');
    }

    public function test_a_player_not_on_this_roster_is_found_by_their_in_game_name(): void
    {
        $this->fakeDiscord();
        $other = User::factory()->create(['discord_id' => '777']);
        $this->registerAttendee(SharedLink::factory()->create(), ['Bow', 'Crossbow'], $other)->forceFill(['in_game_name' => 'Wanderer'])->save();
        $this->decide($this->claim(1, 'wanderer'), 'approve');

        $this->actingAs($this->link->creator)->post(route('party.regear.notify', $this->link));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/users/@me/channels') && $r['recipient_id'] === '777');
    }

    public function test_without_a_bot_there_is_no_button_and_nothing_is_sent(): void
    {
        config(['services.discord.bot_token' => null]);
        Http::fake();
        $this->decide($this->claim(1, 'Zero420'), 'approve');

        $this->actingAs($this->link->creator)->get(route('party.regear', $this->link))->assertDontSee('Oyunculara bildir');
        $this->actingAs($this->link->creator)->post(route('party.regear.notify', $this->link))->assertSessionHas('error');
        $this->actingAs($this->link->creator)->post(route('party.regear.paid', $this->link))
            ->assertSessionHas('success', '1 regear ödendi olarak işaretlendi.');
        Http::assertNothingSent();
    }
}
