<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
    }

    public function test_caller_actions_are_logged_and_shown_to_managers_only(): void
    {
        $link = SharedLink::factory()->create();
        $caller = $link->creator;
        $player = $this->registerAttendee($link, ['Bow', 'Crossbow']);
        $player->forceFill(['in_game_name' => 'Arrows'])->save();
        $other = $this->registerAttendee($link, ['Bow', 'Crossbow'], slot: 3);
        $other->forceFill(['in_game_name' => 'Sitter'])->save();

        $this->actingAs($caller)->postJson(route('party.move', $link), ['attendee_id' => $player->id, 'target_slot' => 3])->assertOk();
        $this->actingAs($caller)->post(route('party.attendees.bench', [$link, $player]))->assertSessionHas('success');
        $this->actingAs($caller)->post(route('party.attendees.kick', [$link, $other]))->assertSessionHas('success');
        $this->actingAs($caller)->post(route('party.situation', $link), ['situation' => 'kite']);

        $summaries = AuditLog::where('shared_link_id', $link->id)->orderBy('id')->pluck('summary')->all();
        $this->assertSame('Arrows: yedek → Slot 3 (Sitter yedeğe düştü)', $summaries[0]);
        $this->assertSame('Arrows: Slot 3 yedeğe alındı', $summaries[1]);
        $this->assertSame('Sitter partiden çıkarıldı (yedekteydi)', $summaries[2]);
        $this->assertStringStartsWith('Durum: ', $summaries[3]);
        $this->assertSame($caller->id, AuditLog::first()->user_id);

        $this->actingAs($caller)->get(route('party.activity', $link))->assertOk()
            ->assertSee('Arrows: yedek → Slot 3 (Sitter yedeğe düştü)')->assertSee($caller->name);
        $this->actingAs(User::factory()->create())->get(route('party.activity', $link))->assertForbidden();
        $this->actingAs($caller)->get(route('party.show', $link))->assertSee(route('party.activity', $link), false);
    }

    public function test_guild_wide_changes_are_listed_on_the_settings_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->put(route('settings.regear.update'), [
            'min_tier' => ['default' => 7], 'payouts' => ['tank' => '1m'],
        ])->assertSessionHasNoErrors();

        $entry = AuditLog::sole();
        $this->assertNull($entry->shared_link_id);
        $this->assertSame(1_000_000, $entry->details['after']['payouts']['tank']);

        $this->actingAs($admin)->get(route('settings.regear'))->assertSee('Son değişiklikler')->assertSee('Varsayılan regear ayarları değiştirildi');
    }

    public function test_party_entries_survive_deleting_the_party(): void
    {
        $link = SharedLink::factory()->create();
        $this->actingAs($link->creator)->delete(route('dashboard.delete', $link));

        $this->assertSame('Parti silindi: '.$link->title, AuditLog::sole()->summary);
        $this->assertSame($link->id, AuditLog::sole()->link->id);
    }
}
