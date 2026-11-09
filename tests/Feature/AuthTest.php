<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureGuildMembership;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function configureDiscord(): void
    {
        config([
            'services.discord.guild_id' => '111',
            'services.discord.role_id' => '222',
            'services.discord.bot_token' => 'bot-token',
        ]);
    }

    private function fakeSocialiteUser(string $id = '999888777666555444'): void
    {
        $user = (new SocialiteUser)->map([
            'id' => $id, 'name' => 'Raider', 'nickname' => 'raider', 'email' => 'raider@example.com', 'avatar' => 'https://cdn/x.png',
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($user);
    }

    public function test_login_requires_guild_check_configuration(): void
    {
        $this->get(route('auth.discord.callback'))->assertStatus(503);
    }

    public function test_member_with_role_logs_in(): void
    {
        $this->configureDiscord();
        $this->fakeSocialiteUser();
        Http::fake(['discord.com/api/v10/guilds/111/members/999888777666555444' => Http::response(['roles' => ['5', '222']])]);

        $this->get(route('auth.discord.callback'))->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $user = User::sole();
        $this->assertSame('999888777666555444', $user->discord_id);
        $this->assertSame(User::ROLE_USER, $user->role);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bot bot-token'));
    }

    public function test_non_members_and_members_without_role_are_rejected(): void
    {
        $this->configureDiscord();
        $this->fakeSocialiteUser();

        Http::fakeSequence()
            ->push(['message' => 'Unknown Member'], 404)
            ->push(['roles' => ['5']]);

        $this->get(route('auth.discord.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'Bu sisteme girmek için Discord sunucumuza üye olmalısınız!');
        $this->assertGuest();

        $this->get(route('auth.discord.callback'))->assertSessionHas('error', 'Giriş yetkiniz yok! Gerekli Discord rolüne sahip değilsiniz.');
        $this->assertGuest();
    }

    public function test_session_ends_when_the_guild_role_is_removed(): void
    {
        $this->configureDiscord();
        $user = User::factory()->create();
        Http::fake(['*' => Http::response(['roles' => []])]);

        $this->actingAs($user)
            ->withSession([EnsureGuildMembership::SESSION_KEY => time() - 3600])
            ->get(route('attendance.index'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_membership_is_not_rechecked_within_the_interval(): void
    {
        $this->configureDiscord();
        Http::fake();

        $this->actingAs(User::factory()->create())->withSession([EnsureGuildMembership::SESSION_KEY => time()])
            ->get(route('attendance.index'))->assertOk();

        Http::assertNothingSent();
    }

    public function test_discord_outage_does_not_lock_members_out(): void
    {
        $this->configureDiscord();
        Http::fake(['*' => Http::response('down', 503)]);

        $this->actingAs(User::factory()->create())->withSession([EnsureGuildMembership::SESSION_KEY => 0])
            ->get(route('attendance.index'))->assertOk();

        $this->assertAuthenticated();
    }

    public function test_logout_requires_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_dev_login_only_works_in_local_with_flag(): void
    {
        $dev = User::factory()->create(['discord_id' => 'dev-admin']);
        $real = User::factory()->create();

        config(['services.dev_login.enabled' => true]);
        $this->get(route('dev.login'))->assertNotFound(); // APP_ENV=testing

        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class); // CSRF is enforced outside "testing"
        $this->get(route('dev.login'))->assertOk()->assertSee($dev->name);
        $this->post(route('dev.login.store'), ['user_id' => $real->id])->assertNotFound();
        $this->post(route('dev.login.store'), ['user_id' => $dev->id])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($dev);
    }

    public function test_set_role_command(): void
    {
        $user = User::factory()->create(['discord_id' => '123']);

        $this->artisan('app:set-role', ['user' => '123', 'role' => 'admin'])->assertSuccessful();
        $this->assertTrue($user->refresh()->isAdmin());

        $this->artisan('app:set-role', ['user' => '123', 'role' => 'king'])->assertFailed();
    }
}
