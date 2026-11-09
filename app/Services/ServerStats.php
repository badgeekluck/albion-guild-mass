<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Machine and app health for the admin server screen. Reads Linux /proc and the
 * container's cgroup, so inside Docker this is the host (or Docker VM) CPU / RAM / disk,
 * plus how much memory the app container itself uses.
 */
class ServerStats
{
    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'time' => now()->toIso8601ZuluString(),
            'cpu' => $this->cpu(),
            'memory' => $this->memory(),
            'disk' => $this->disk(),
            'uptime_seconds' => $this->uptime(),
        ];
    }

    /** @return array<string, mixed> slower facts, loaded with the page */
    public function app(): array
    {
        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'database_mb' => $this->databaseSize(),
            'queue_pending' => $this->count('jobs'),
            'queue_failed' => $this->count('failed_jobs'),
            'container_memory_mb' => $this->containerMemory(),
            'icons' => count(glob(public_path('item-icons/*.png')) ?: []),
        ];
    }

    /** @return array{percent: float|null, cores: int, load: list<float>} */
    private function cpu(): array
    {
        $cores = is_readable('/proc/stat') ? max(1, preg_match_all('/^cpu\d+/m', (string) file_get_contents('/proc/stat'))) : 1;
        $load = is_readable('/proc/loadavg') ? array_map('floatval', array_slice(explode(' ', (string) file_get_contents('/proc/loadavg')), 0, 3)) : [];

        // Busy share of all cores over a short sample.
        $a = $this->cpuTimes();
        usleep(250_000);
        $b = $this->cpuTimes();
        $percent = null;
        if ($a && $b && ($total = $b['total'] - $a['total']) > 0) {
            $percent = round(100 * (1 - ($b['idle'] - $a['idle']) / $total), 1);
        }

        return ['percent' => $percent, 'cores' => $cores, 'load' => $load];
    }

    /** @return array{total: int, idle: int}|null */
    private function cpuTimes(): ?array
    {
        if (! is_readable('/proc/stat')) {
            return null;
        }
        $parts = preg_split('/\s+/', trim(strtok((string) file_get_contents('/proc/stat'), "\n")));
        $values = array_map('intval', array_slice($parts, 1));

        return ['total' => array_sum($values), 'idle' => ($values[3] ?? 0) + ($values[4] ?? 0)]; // idle + iowait
    }

    /** @return array{total_mb: int, used_mb: int, percent: float, swap_used_mb: int}|null */
    private function memory(): ?array
    {
        if (! is_readable('/proc/meminfo')) {
            return null;
        }
        preg_match_all('/^(\w+):\s+(\d+)/m', (string) file_get_contents('/proc/meminfo'), $m);
        $info = array_combine($m[1], array_map('intval', $m[2]));
        $total = $info['MemTotal'] ?? 0;
        $used = $total - ($info['MemAvailable'] ?? 0);

        return [
            'total_mb' => intdiv($total, 1024),
            'used_mb' => intdiv($used, 1024),
            'percent' => $total ? round(100 * $used / $total, 1) : 0.0,
            'swap_used_mb' => intdiv(($info['SwapTotal'] ?? 0) - ($info['SwapFree'] ?? 0), 1024),
        ];
    }

    /** @return array{total_gb: float, used_gb: float, percent: float}|null */
    private function disk(): ?array
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());
        if (! $total) {
            return null;
        }

        return [
            'total_gb' => round($total / 1024 ** 3, 1),
            'used_gb' => round(($total - $free) / 1024 ** 3, 1),
            'percent' => round(100 * ($total - $free) / $total, 1),
        ];
    }

    private function uptime(): ?int
    {
        return is_readable('/proc/uptime') ? (int) explode(' ', (string) file_get_contents('/proc/uptime'))[0] : null;
    }

    private function containerMemory(): ?int
    {
        $current = @file_get_contents('/sys/fs/cgroup/memory.current');

        return $current !== false ? intdiv((int) $current, 1024 ** 2) : null;
    }

    private function databaseSize(): ?float
    {
        try {
            if (DB::getDriverName() !== 'mysql') {
                return null;
            }

            return round((float) DB::table('information_schema.tables')
                ->where('table_schema', DB::getDatabaseName())
                ->sum(DB::raw('data_length + index_length')) / 1024 ** 2, 1);
        } catch (Throwable) {
            return null;
        }
    }

    private function count(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }
}
