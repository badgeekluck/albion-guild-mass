<?php

use App\Http\Controllers\ItemIconController;
use App\Models\GameRole;
use App\Models\User;
use App\Services\Albion\GameInfoClient;
use App\Services\RosterSpreadsheetImporter;
use Illuminate\Support\Facades\Artisan;

/*
 * Promote / demote a user:
 *   php artisan app:set-role <id|discord_id|name> admin|content-creator|user
 */
Artisan::command('app:set-role {user : User ID, Discord ID or exact name} {role : admin, content-creator or user}', function (string $user, string $role) {
    if (! in_array($role, User::ROLES, true)) {
        $this->error('Role must be one of: '.implode(', ', User::ROLES));

        return 1;
    }

    $matches = User::where('discord_id', $user)
        ->orWhere('name', $user)
        ->when(ctype_digit($user), fn ($q) => $q->orWhere('id', (int) $user))
        ->get();

    if ($matches->count() !== 1) {
        $this->error($matches->isEmpty() ? 'User not found.' : 'Ambiguous: '.$matches->count().' users match, use the ID.');

        return 1;
    }

    $target = $matches->first();
    $target->forceFill(['role' => $role])->save();

    $this->info("{$target->name} (#{$target->id}) is now '{$role}'.");

    return 0;
})->purpose('Change a user\'s role');

/*
 * Import a composition spreadsheet (one sheet = one template, one row = one build):
 *   php artisan app:import-roster storage/app/private/imports/ROAD\ BLOCK.xlsx --dry-run
 */
Artisan::command('app:import-roster {path : Path to the .xlsx file} {--user= : User ID recorded as creator (default: first admin)} {--prefix= : Template name prefix (default: file name)} {--dry-run : Show what would be imported without saving}', function (RosterSpreadsheetImporter $importer) {
    $path = (string) $this->argument('path');
    if (! is_file($path)) {
        $this->error("File not found: {$path}");

        return 1;
    }

    $userId = $this->option('user') ?: User::where('role', User::ROLE_ADMIN)->orderBy('id')->value('id');
    if (! $userId || ! User::whereKey($userId)->exists()) {
        $this->error('No creator user found. Pass --user=<id> or create an admin first (app:set-role).');

        return 1;
    }

    $prefix = $this->option('prefix') ?? pathinfo($path, PATHINFO_FILENAME);
    $report = $importer->import($path, (int) $userId, (string) $prefix, (bool) $this->option('dry-run'));

    $this->table(['Template', 'Slots', 'Status'], array_map(fn ($t) => [$t['name'], $t['slots'], $t['status']], $report['templates']));
    $this->info("Builds created: {$report['builds_created']}, updated: {$report['builds_updated']}".($report['dry_run'] ? ' (dry run, nothing saved)' : ''));

    if ($report['unresolved']) {
        $this->warn('Not recognised (kept as build notes, map them in the build editor):');
        $this->table(['Text', 'Count'], array_map(fn ($text, $count) => [$text, $count], array_keys($report['unresolved']), $report['unresolved']));
    }

    return 0;
})->purpose('Import builds and party templates from a composition spreadsheet');

/*
 * Download every item icon once into public/item-icons (also happens lazily on first view).
 */
Artisan::command('app:cache-item-icons {--force : Re-download icons that are already cached}', function () {
    $items = GameRole::whereNotNull('unique_name')->pluck('unique_name')->unique();
    $failed = [];

    $this->withProgressBar($items, function (string $uniqueName) use (&$failed) {
        $path = public_path(ItemIconController::DIRECTORY.'/'.$uniqueName.'.png');
        if (($this->option('force') || ! is_file($path)) && ! ItemIconController::download($uniqueName, $path)) {
            $failed[] = $uniqueName;
        }
    });

    $this->newLine();
    $this->info(($items->count() - count($failed)).' icons cached.');
    if ($failed) {
        $this->warn('Failed (will be retried on first view): '.implode(', ', $failed));
    }

    return 0;
})->purpose('Cache Albion item icons locally');

/*
 * Find your guild's Albion ID for ALBION_GUILD_ID:
 *   php artisan app:albion-guild "Road Block"
 */
Artisan::command('app:albion-guild {name : Guild name}', function (GameInfoClient $client, string $name) {
    $guilds = $client->searchGuilds($name);
    if (! $guilds) {
        $this->warn('No guild found on the '.config('services.albion.region').' server (ALBION_REGION).');

        return 1;
    }

    $this->table(['Name', 'ALBION_GUILD_ID', 'Alliance'], array_map(fn ($g) => [$g['Name'], $g['Id'], $g['AllianceName'] ?? ''], $guilds));

    return 0;
})->purpose('Look up an Albion guild ID');
