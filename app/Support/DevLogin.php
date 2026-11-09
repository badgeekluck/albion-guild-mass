<?php

namespace App\Support;

/**
 * Passwordless login for local testing without a Discord application.
 * Requires BOTH APP_ENV=local and DEV_LOGIN_ENABLED=true.
 */
final class DevLogin
{
    public static function enabled(): bool
    {
        return app()->environment('local') && (bool) config('services.dev_login.enabled');
    }
}
