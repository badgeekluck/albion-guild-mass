<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Serves item icons from public/item-icons, downloading each one from the Albion render API
 * only once. After the first request nginx serves the file directly (try_files), so party
 * screens with hundreds of icons no longer hit (and get rate-limited by) the render API.
 */
class ItemIconController extends Controller
{
    public const DIRECTORY = 'item-icons';

    public function __invoke(string $uniqueName): BinaryFileResponse
    {
        abort_unless(preg_match('/^(T\d_[A-Z0-9_]+|UNIQUE_[A-Z0-9_]+)(@[1-4])?$/', $uniqueName) === 1, 404);

        $path = public_path(self::DIRECTORY.'/'.$uniqueName.'.png');

        if (! is_file($path) && (self::recentlyFailed($path) || ! self::download($uniqueName, $path))) {
            abort(404); // the board shows the item name instead
        }

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=2592000, immutable',
        ]);
    }

    /** Unknown icons are not re-requested for a day, so one missing image can't tie up PHP workers. */
    public static function recentlyFailed(string $path): bool
    {
        $marker = $path.'.missing';

        return is_file($marker) && filemtime($marker) > time() - 86400;
    }

    public static function download(string $uniqueName, string $path): bool
    {
        $result = self::fetch($uniqueName, $path);

        if ($result === 'ok') {
            File::delete($path.'.missing');
        } elseif ($result === 'not_found') {
            // Only a definite "no such item" is remembered; timeouts / 5xx are retried next time.
            File::ensureDirectoryExists(dirname($path));
            touch($path.'.missing');
        }

        return $result === 'ok';
    }

    /** @return 'ok'|'not_found'|'error' */
    private static function fetch(string $uniqueName, string $path): string
    {
        try {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                // Only transient failures are worth a retry (the render API intermittently answers 502).
                ->retry(3, 500, fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)), throw: false)
                ->get('https://render.albiononline.com/v1/item/'.$uniqueName.'.png', ['size' => 128, 'quality' => 1]);
        } catch (Throwable $e) {
            report($e);

            return 'error';
        }

        if ($response->status() === 404) {
            return 'not_found';
        }
        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            return 'error';
        }

        File::ensureDirectoryExists(dirname($path));
        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        File::put($tmp, $response->body());
        File::move($tmp, $path); // atomic: concurrent requests never see a half-written file

        return 'ok';
    }
}
