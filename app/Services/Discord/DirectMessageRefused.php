<?php

namespace App\Services\Discord;

use RuntimeException;

/** Discord refused the DM (closed DMs, blocked bot, no shared server): retrying won't help. */
class DirectMessageRefused extends RuntimeException {}
