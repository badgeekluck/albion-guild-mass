<?php

namespace App\Jobs;

use App\Http\Controllers\ItemIconController;
use App\Models\SharedLink;
use App\Services\Regear\RegearScanner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Big ZvZ battles take dozens of API pages, so scanning runs on the queue. */
class ScanRegearDeaths implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /** The "already scanning" lock expires on its own if a worker dies mid-scan. */
    public int $uniqueFor = 900;

    public function __construct(public int $linkId) {}

    public function uniqueId(): string
    {
        return (string) $this->linkId;
    }

    public function handle(RegearScanner $scanner): void
    {
        $link = SharedLink::withTrashed()->findOrFail($this->linkId);

        try {
            $result = $scanner->scan($link);
            $status = "done: {$result['deaths']} ölüm ({$result['new']} yeni), {$result['battles']} savaş tarandı"
                .($result['skipped_small'] ? ", {$result['min_battle_size']} kişiden küçük {$result['skipped_small']} savaş atlandı" : '')
                .($result['alliance'] ? ", {$result['alliance']}" : '')
                .", savaşta {$result['fighters']} oyuncumuz görüldü"
                .($result['attended'] ? " ({$result['attended']} kişi katıldı işaretlendi)" : '')
                .($result['warnings'] ? ' ⚠️ '.implode(' ', $result['warnings']) : '');
        } catch (Throwable $e) {
            report($e);
            $status = 'failed: '.mb_substr($e->getMessage(), 0, 200);
        }

        $link->forceFill(['regear_scan_status' => $status, 'regear_scanned_at' => now()])->save();

        $this->warmIcons($link);
    }

    /** Download the icons of the death gear one by one, so the review page doesn't burst the render API. */
    private function warmIcons(SharedLink $link): void
    {
        $claims = $link->regearClaims()->get(['checks', 'killer_weapon']);
        $ids = $claims->pluck('checks')
            ->flatMap(fn ($checks) => array_column((array) $checks, 'actual'))
            ->merge($claims->pluck('killer_weapon'))
            ->filter()->unique();

        foreach ($ids as $id) {
            $path = public_path(ItemIconController::DIRECTORY.'/'.$id.'.png');
            if (! is_file($path) && ! ItemIconController::recentlyFailed($path)) {
                ItemIconController::download($id, $path);
            }
        }
    }
}
