<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RequestLog;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameData;
use Tests\TestCase;

class AdminScreensTest extends TestCase
{
    use CreatesGameData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createGameRoles();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Boss']);
    }

    public function test_page_views_and_actions_are_logged_without_noise(): void
    {
        $player = User::factory()->create(['name' => 'Zero']);
        $link = SharedLink::factory()->content()->create(['title' => 'Friday CTA']);

        $this->actingAs($player)->get(route('party.show', $link))->assertOk();
        $this->actingAs($player)->get(route('party.board', $link))->assertOk(); // live refresh: skipped
        $this->actingAs($player)->withHeader('User-Agent', 'Mozilla/5.0 (iPhone)')
            ->post(route('party.join', $link), ['in_game_name' => 'Zero', 'main_role' => 'Bow'])->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get(route('home')); // guest: skipped

        $logs = RequestLog::orderBy('id')->get();
        $this->assertSame(['party.show', 'party.join'], $logs->pluck('route')->all());
        $this->assertSame($link->id, $logs[0]->shared_link_id);
        $this->assertSame(['GET', 'POST'], $logs->pluck('method')->all());
        $this->assertTrue($logs[1]->is_mobile);
        $this->assertSame('Partiye katıldı', $logs[1]->label());
    }

    public function test_activity_screen_shows_requests_and_changes_to_admins_only(): void
    {
        $player = User::factory()->create(['name' => 'Zero']);
        $link = SharedLink::factory()->content()->create(['title' => 'Friday CTA']);
        $this->actingAs($player)->get(route('party.show', $link));
        $this->actingAs($player)->post(route('party.join', $link), ['in_game_name' => 'Zero', 'main_role' => 'Bow']);
        AuditLog::create(['user_id' => $this->admin->id, 'shared_link_id' => $link->id, 'action' => 'kick', 'summary' => 'Zero partiden çıkarıldı']);

        $this->actingAs($this->admin)->get(route('admin.activity'))->assertOk()
            ->assertSee('Şu an çevrimiçi')->assertSee('Parti ekranı')->assertSee('Partiye katıldı')->assertSee('Friday CTA')->assertSee('Zero');
        $this->actingAs($this->admin)->get(route('admin.activity', ['only' => 'actions', 'user' => $player->id]))
            ->assertSee('Partiye katıldı')->assertDontSee('Parti ekranı</span>', false);
        $this->actingAs($this->admin)->get(route('admin.activity', ['tab' => 'changes']))->assertSee('Zero partiden çıkarıldı');

        $caller = User::factory()->creator()->create();
        $this->actingAs($caller)->get(route('admin.activity'))->assertForbidden();
        $this->actingAs($caller)->get(route('admin.server'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee(route('admin.server'), false);
        $this->actingAs($caller)->get(route('dashboard'))->assertDontSee(route('admin.server'), false);
    }

    public function test_server_screen_reports_machine_and_app_stats(): void
    {
        $this->actingAs($this->admin)->get(route('admin.server'))->assertOk()->assertSee('Sunucu durumu')->assertSee(PHP_VERSION);

        $stats = $this->actingAs($this->admin)->getJson(route('admin.server.stats'))->assertOk()
            ->assertJsonStructure(['time', 'cpu' => ['percent', 'cores', 'load'], 'memory', 'disk', 'uptime_seconds'])->json();
        $this->assertGreaterThan(0, $stats['disk']['total_gb']);
        $this->assertSame(0, RequestLog::where('route', 'admin.server.stats')->count()); // polling isn't logged
    }

    public function test_old_entries_are_cleaned_up(): void
    {
        RequestLog::create(['user_id' => $this->admin->id, 'method' => 'GET', 'route' => 'dashboard', 'path' => '/dashboard', 'status' => 200, 'duration_ms' => 5, 'created_at' => now()->subDays(40)]);
        $this->assertSame(1, RequestLog::where('created_at', '<', now()->subDays(RequestLog::KEEP_DAYS))->count());

        // The cleanup is a 1/500 lottery on each request; run it directly here.
        RequestLog::where('created_at', '<', now()->subDays(RequestLog::KEEP_DAYS))->delete();
        $this->assertSame(0, RequestLog::where('created_at', '<', now()->subDays(RequestLog::KEEP_DAYS))->count());
    }
}
