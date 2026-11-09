<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->app->environment(['local', 'testing'])) {
            URL::forceScheme('https');
        }

        Event::listen(SocialiteWasCalled::class, [DiscordExtendSocialite::class, 'handle']);

        $this->configureRateLimits();
    }

    /**
     * Throttles on party actions: protects the row locks and the live broadcasts from
     * scripts and click-spam, while leaving room for a caller dragging a whole roster.
     */
    private function configureRateLimits(): void
    {
        $tooFast = fn (string $message) => fn (Request $request, array $headers) => $request->expectsJson()
            ? response()->json(['message' => $message], 429, $headers)
            : back()->with('error', $message);

        $perUser = fn (Request $request) => (string) ($request->user()?->id ?? $request->ip());

        // Join / leave / claim / release: a player needs a handful of these per event.
        RateLimiter::for('party-signup', fn (Request $request) => Limit::perMinute(10)->by('signup:'.$perUser($request))
            ->response($tooFast('Çok hızlı işlem yapıyorsun, bir dakika sonra tekrar dene.')));

        // Caller actions (move, confirm, bench, kick, extra slots, situation).
        RateLimiter::for('party-arrange', fn (Request $request) => Limit::perMinute(120)->by('arrange:'.$perUser($request))
            ->response($tooFast('Çok hızlı işlem yapıyorsun, birkaç saniye bekle.')));

        // Discord messages to the whole roster (ping, regear DMs): at most twice per 5 minutes per party and kind.
        RateLimiter::for('party-ping', fn (Request $request) => Limit::perMinutes(5, 2)->by('ping:'.$request->route()?->getName().':'.self::routeKey($request->route('link')))
            ->response($tooFast('Bu parti için az önce Discord\'a mesaj gönderildi, birkaç dakika bekle.')));
    }

    /** Throttling runs before route model binding, so the parameter may still be the raw slug. */
    private static function routeKey(mixed $parameter): string
    {
        return (string) (is_object($parameter) && method_exists($parameter, 'getRouteKey') ? $parameter->getRouteKey() : $parameter);
    }
}
