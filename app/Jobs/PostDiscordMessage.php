<?php

namespace App\Jobs;

use App\Services\Discord\DiscordClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PostDiscordMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30];

    /**
     * @param  list<string>  $mentionUserIds
     */
    public function __construct(
        public string $channelId,
        public string $content,
        public array $mentionUserIds = [],
    ) {}

    public function handle(DiscordClient $discord): void
    {
        $discord->postMessage($this->channelId, $this->content, $this->mentionUserIds);
    }

    /**
     * Splits long text into Discord-sized messages on line boundaries.
     *
     * @return list<string>
     */
    public static function chunk(string $text, int $limit = DiscordClient::MESSAGE_LIMIT): array
    {
        $chunks = [];
        $current = '';
        foreach (explode("\n", $text) as $line) {
            $candidate = $current === '' ? $line : $current."\n".$line;
            if (mb_strlen($candidate) > $limit && $current !== '') {
                $chunks[] = $current;
                $current = mb_substr($line, 0, $limit);
            } else {
                $current = mb_substr($candidate, 0, $limit);
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
