<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI'),

        // Guild membership: users must be in this guild and have this role to log in.
        'guild_id' => env('DISCORD_GUILD_ID'),
        'role_id' => env('DISCORD_ROLE_ID'),
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'membership_recheck_minutes' => (int) env('DISCORD_MEMBERSHIP_RECHECK_MINUTES', 15),

        // Optional channel for party announcements / roster pings.
        'announce_channel_id' => env('DISCORD_ANNOUNCE_CHANNEL_ID'),

        'api_base' => 'https://discord.com/api/v10',
    ],

    // Albion Online killboard (official gameinfo API) for regear payouts.
    'albion' => [
        'region' => env('ALBION_REGION', 'europe'), // europe | americas | asia
        'guild_id' => env('ALBION_GUILD_ID'),
        'hosts' => [
            'europe' => 'https://gameinfo-ams.albiononline.com/api/gameinfo',
            'americas' => 'https://gameinfo.albiononline.com/api/gameinfo',
            'asia' => 'https://gameinfo-sgp.albiononline.com/api/gameinfo',
        ],
        // Safety cap: pages of battle events fetched per scan (51 events per page).
        'max_pages_per_battle' => (int) env('ALBION_MAX_PAGES_PER_BATTLE', 40),
    ],

    // Passwordless /dev/login for local testing. Only honoured when APP_ENV=local.
    'dev_login' => [
        'enabled' => (bool) env('DEV_LOGIN_ENABLED', false),
    ],

];
