<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionCheckTest extends TestCase
{
    use RefreshDatabase;

    private function productionConfig(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false, 'app.url' => 'https://party.example.com', 'session.secure' => true,
            'services.dev_login.enabled' => false,
            'services.discord.client_id' => 'id', 'services.discord.client_secret' => 'secret', 'services.discord.redirect' => 'https://party.example.com/auth/discord/callback',
            'services.discord.guild_id' => 'g', 'services.discord.role_id' => 'r', 'services.discord.bot_token' => 't',
            'services.albion.guild_id' => 'G', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 660,
        ]);
    }

    public function test_a_production_ready_configuration_passes(): void
    {
        $this->productionConfig();

        $this->artisan('app:production-check')->assertSuccessful()->expectsOutputToContain('Ready for production');
    }

    public function test_dangerous_settings_fail_the_check(): void
    {
        $this->productionConfig();
        config(['app.debug' => true, 'services.dev_login.enabled' => true, 'queue.connections.database.retry_after' => 90]);
        User::factory()->create(['discord_id' => User::DEV_DISCORD_PREFIX.'1']);

        $this->artisan('app:production-check')->assertFailed()->expectsOutputToContain('4 problem(s) must be fixed');
    }
}
