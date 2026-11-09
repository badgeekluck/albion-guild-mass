<?php

namespace Tests\Unit;

use App\Jobs\PostDiscordMessage;
use PHPUnit\Framework\TestCase;

class PostDiscordMessageTest extends TestCase
{
    public function test_long_messages_are_split_on_line_boundaries(): void
    {
        $lines = array_map(fn ($i) => str_repeat('x', 90)." #{$i}", range(1, 50));

        $chunks = PostDiscordMessage::chunk(implode("\n", $lines), 2000);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(2000, mb_strlen($chunk));
        }
        $this->assertSame(implode("\n", $lines), implode("\n", $chunks));
    }
}
