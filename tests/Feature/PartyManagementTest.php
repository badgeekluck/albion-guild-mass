<?php

namespace Tests\Feature;

use App\Jobs\PostDiscordMessage;
use App\Models\PartyTemplate;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class PartyManagementTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
    }

    public function test_only_admin_or_creator_can_change_extra_slots(): void
    {
        $link = SharedLink::factory()->create();

        $this->actingAs(User::factory()->creator()->create())
            ->post(route('party.slots', $link), ['action' => 'add'])
            ->assertForbidden();

        $this->actingAs($link->creator)->post(route('party.slots', $link), ['action' => 'add'])->assertSessionHas('success');
        $this->assertSame(1, $link->refresh()->extra_slots);
    }

    public function test_extra_slots_can_be_added_by_party_up_to_the_total_cap(): void
    {
        $link = SharedLink::factory()->create(); // 20 template slots
        $add = fn (int $amount) => $this->actingAs($link->creator)->post(route('party.slots', $link), ['action' => 'add', 'amount' => $amount]);

        $add(20)->assertSessionHas('success');
        $add(1);
        $this->assertSame([21, 41], [$link->refresh()->extra_slots, $link->maxSlots()]);

        // Well past the old limit of 40 extra slots, up to 300 slots in total.
        foreach (range(1, 14) as $i) {
            $add(20);
        }
        $this->assertSame([280, 300], [$link->refresh()->extra_slots, $link->maxSlots()]);
        $add(1)->assertSessionHas('error', 'En fazla 300 slot olabilir (15 parti).');
        $this->assertSame(300, $link->refresh()->maxSlots());

        $this->actingAs($link->creator)->post(route('party.slots', $link), ['action' => 'remove', 'amount' => 20]);
        $this->assertSame(260, $link->refresh()->extra_slots);
        $this->actingAs($link->creator)->post(route('party.slots', $link), ['action' => 'add', 'amount' => 7])->assertSessionHasErrors('amount');

        $this->actingAs($link->creator)->get(route('party.board', $link))->assertOk()->assertSee('Party 14');
    }

    public function test_removing_an_extra_slot_returns_its_player_to_the_waitlist(): void
    {
        $link = SharedLink::factory()->create();
        $link->forceFill(['extra_slots' => 1])->save();
        $attendee = $this->registerAttendee($link, ['Bombsquad'], slot: 21);

        $this->actingAs($link->creator)->post(route('party.slots', $link), ['action' => 'remove']);

        $this->assertSame(0, $link->refresh()->extra_slots);
        $this->assertNull($attendee->refresh()->slot_index);
    }

    public function test_finishing_an_event_checks_in_placed_players(): void
    {
        $link = SharedLink::factory()->create();
        $placed = $this->registerAttendee($link, ['Bow', 'Locus'], slot: 1);
        $waiting = $this->registerAttendee($link, ['Bow', 'Locus']);

        $this->actingAs($link->creator)->post(route('dashboard.archive', $link))->assertSessionHas('success');

        $link->refresh();
        $this->assertTrue($link->isCompleted());
        $this->assertSame($link->creator_id, $link->archived_by);
        $this->assertTrue($placed->refresh()->attended);
        $this->assertFalse($waiting->refresh()->attended);
    }

    public function test_other_staff_cannot_finish_or_delete_someone_elses_event(): void
    {
        $link = SharedLink::factory()->create();
        $otherCaller = User::factory()->creator()->create();

        $this->actingAs($otherCaller)->post(route('dashboard.archive', $link))->assertForbidden();
        $this->actingAs($otherCaller)->delete(route('dashboard.delete', $link))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('dashboard.delete', $link))->assertSessionHas('success');

        $this->assertSoftDeleted($link);
    }

    public function test_attendance_can_be_corrected_after_the_event(): void
    {
        $link = SharedLink::factory()->completed()->create();
        $attendee = $this->registerAttendee($link, ['Bow', 'Locus']);

        $this->actingAs($link->creator)->post(route('party.attendance', [$link, $attendee]))->assertSessionHas('success');
        $this->assertTrue($attendee->refresh()->attended);

        $this->actingAs(User::factory()->create())->post(route('party.attendance', [$link, $attendee]))->assertForbidden();
    }

    public function test_attendance_stats_only_count_attended_players_of_finished_events(): void
    {
        $player = User::factory()->create(['name' => 'zz-player']);
        $finished = SharedLink::factory()->completed()->create();
        $finishedContent = SharedLink::factory()->content()->completed()->create();
        $absent = SharedLink::factory()->completed()->create();
        $running = SharedLink::factory()->create();

        foreach ([$finished, $finishedContent] as $link) {
            $this->registerAttendee($link, ['Bow', 'Locus'], $player)->forceFill(['attended' => true])->save();
        }
        $this->registerAttendee($absent, ['Bow', 'Locus'], $player);
        $this->registerAttendee($running, ['Bow', 'Locus'], $player)->forceFill(['attended' => true])->save();
        $finished->delete(); // removed from the dashboard archive, still counts

        $response = $this->actingAs($player)->get(route('attendance.index'))->assertOk();
        $row = $response->viewData('users')->firstWhere('id', $player->id);

        $this->assertSame(2, $row->total_attendance);
        $this->assertSame(1, $row->cta_attendance);
        $this->assertSame(1, $row->content_attendance);

        $this->actingAs($player)->get(route('attendance.show', $player))->assertOk()->assertSee('2 / 3 events attended');
    }

    public function test_roster_can_be_saved_as_template(): void
    {
        $link = SharedLink::factory()->withTemplate($this->template([1 => ['Hallowfall', 'heal'], 2 => ['', 'any']], size: 2))->create(['title' => 'Prime time']);
        $link->forceFill(['extra_slots' => 1])->save();
        $this->registerAttendee($link, ['Bow', 'Locus'], slot: 2)->forceFill(['assigned_role' => 'Bow'])->save();

        $this->actingAs($link->creator)->post(route('party.save-template', $link))->assertRedirect();

        $template = PartyTemplate::sole();
        $this->assertSame(3, $template->size);
        $this->assertSame('Hallowfall', $template->structure[1]['role']);
        $this->assertSame('Bow', $template->structure[2]['role']);
        $this->assertSame('Flex', $template->structure[3]['note']);
        $this->assertStringStartsWith('Prime time', $template->name);
    }

    public function test_regular_creator_without_staff_role_cannot_save_templates(): void
    {
        $creator = User::factory()->create();
        $link = SharedLink::factory()->create(['creator_id' => $creator->id]);

        $this->actingAs($creator)->post(route('party.save-template', $link))->assertForbidden();
    }

    public function test_ping_roster_queues_discord_message_with_mentions(): void
    {
        Queue::fake();
        config(['services.discord.bot_token' => 'bot', 'services.discord.announce_channel_id' => '555']);
        $link = SharedLink::factory()->create(['title' => 'ZvZ']);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);
        $this->registerAttendee($link, ['Bow', 'Locus'], $player, slot: 1);
        $this->registerAttendee($link, ['Bow', 'Locus']); // waitlist: not pinged

        $this->actingAs($link->creator)->post(route('party.ping', $link))->assertSessionHas('success');

        Queue::assertPushed(PostDiscordMessage::class, function (PostDiscordMessage $job) {
            return $job->channelId === '555'
                && $job->mentionUserIds === ['123456789012345678']
                && str_contains($job->content, '<@123456789012345678>');
        });
    }

    public function test_ping_roster_requires_configuration(): void
    {
        Queue::fake();
        $link = SharedLink::factory()->create();

        $this->actingAs($link->creator)->post(route('party.ping', $link))->assertSessionHas('error');
        Queue::assertNothingPushed();
    }

    public function test_creating_a_party_can_announce_it_on_discord(): void
    {
        Queue::fake();
        config(['services.discord.bot_token' => 'bot', 'services.discord.announce_channel_id' => '555']);
        $caller = User::factory()->creator()->create();

        $this->actingAs($caller)->post(route('dashboard.create'), [
            'title' => 'Castle fight',
            'type' => 'cta',
            'registration_hours' => 4,
            'announce' => '1',
        ])->assertSessionHas('success');

        $link = SharedLink::sole();
        $this->assertSame('Castle fight', $link->title);
        $this->assertEqualsWithDelta(now()->addHours(4)->timestamp, $link->expires_at->timestamp, 5);
        $this->assertSame(8, strlen($link->slug));
        Queue::assertPushed(PostDiscordMessage::class, fn ($job) => str_contains($job->content, 'Castle fight'));
    }
}
