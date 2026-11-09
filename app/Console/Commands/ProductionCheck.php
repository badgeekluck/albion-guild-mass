<?php

namespace App\Console\Commands;

use App\Jobs\ScanRegearDeaths;
use App\Models\User;
use App\Services\Albion\GameInfoClient;
use App\Services\Discord\DiscordClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pre-flight check before (and after) a deploy: settings that are fine locally but
 * dangerous or broken in production. Exit code 1 when something must be fixed.
 */
class ProductionCheck extends Command
{
    protected $signature = 'app:production-check';

    protected $description = 'Check the configuration before going live';

    /** @var list<array{0: string, 1: string, 2: string}> level, check, hint */
    private array $results = [];

    public function handle(): int
    {
        $this->must(app()->environment('production'), 'APP_ENV=production', 'Currently "'.app()->environment().'".');
        $this->must(! config('app.debug'), 'APP_DEBUG=false', 'Debug pages leak secrets and stack traces.');
        $this->must(filled(config('app.key')), 'APP_KEY is set', 'Run php artisan key:generate.');
        $this->must(! config('services.dev_login.enabled'), 'Dev login is off', 'Set DEV_LOGIN_ENABLED=false.');
        $this->must(User::all(['discord_id'])->filter(fn (User $u) => $u->isDevAccount())->isEmpty(), 'No dev accounts in the database',
            'Dev accounts come from APP_DEV_SEED=true; delete them and keep APP_DEV_SEED=false.');

        $discord = config('services.discord');
        $this->must(filled($discord['client_id']) && filled($discord['client_secret']) && filled($discord['redirect']), 'Discord login is configured',
            'DISCORD_CLIENT_ID / DISCORD_CLIENT_SECRET / DISCORD_REDIRECT_URI.');
        $this->must(app(DiscordClient::class)->membershipCheckConfigured(), 'Guild membership check is configured',
            'DISCORD_GUILD_ID, DISCORD_ROLE_ID and DISCORD_BOT_TOKEN: without them nobody can log in.');

        $queue = config('queue.default');
        $this->must($queue !== 'sync', 'A real queue is used', 'QUEUE_CONNECTION=sync runs killboard scans inside the web request.');
        $retryAfter = (int) config("queue.connections.{$queue}.retry_after", 0);
        $timeout = (new ScanRegearDeaths(0))->timeout;
        $this->must($queue === 'sync' || $retryAfter > $timeout, "Queue retry_after ({$retryAfter}s) > scan timeout ({$timeout}s)",
            'Raise DB_QUEUE_RETRY_AFTER, otherwise slow scans start twice.');

        $https = str_starts_with((string) config('app.url'), 'https://');
        $this->should($https, 'APP_URL uses https', 'Discord OAuth and secure cookies need HTTPS.');
        $this->should(! $https || config('session.secure'), 'Session cookie is HTTPS-only', 'Set SESSION_SECURE_COOKIE=true.');
        $this->should(config('logging.channels.'.config('logging.default').'.level', 'debug') !== 'debug', 'LOG_LEVEL is not debug', 'Use LOG_LEVEL=warning or error.');
        $this->should(app()->configurationIsCached(), 'Config is cached', 'Run php artisan config:cache route:cache view:cache on deploy.');
        $this->should(filled(GameInfoClient::guildId()), 'Albion guild is selected', 'Pick it in Regear Settings (or ALBION_GUILD_ID) for regear and name checks.');

        try {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            $this->should($failed === 0, 'No failed jobs in the last 24h', "{$failed} failed: php artisan queue:failed");
        } catch (Throwable) {
            $this->should(false, 'failed_jobs table exists', 'Run php artisan migrate.');
        }

        $this->table(['', 'Check', 'Fix'], array_map(fn ($r) => [$r[0] === 'ok' ? '✅' : ($r[0] === 'fail' ? '❌' : '⚠️'), $r[1], $r[0] === 'ok' ? '' : $r[2]], $this->results));

        $this->line('Not checked here: daily database backups (e.g. mysqldump to another machine), the MySQL port staying private, a queue worker running.');

        $failures = count(array_filter($this->results, fn ($r) => $r[0] === 'fail'));
        if ($failures) {
            $this->error("{$failures} problem(s) must be fixed before going live.");

            return self::FAILURE;
        }

        $this->info('Ready for production'.(count(array_filter($this->results, fn ($r) => $r[0] === 'warn')) ? ' (see the warnings).' : '.'));

        return self::SUCCESS;
    }

    private function must(bool $ok, string $check, string $hint): void
    {
        $this->results[] = [$ok ? 'ok' : 'fail', $check, $hint];
    }

    private function should(bool $ok, string $check, string $hint): void
    {
        $this->results[] = [$ok ? 'ok' : 'warn', $check, $hint];
    }
}
