<?php

namespace App\Services;

use App\Models\GameRole;
use App\Models\PartyTemplate;
use App\Models\SavedBuild;
use App\Support\Import\AlbionItemResolver;
use App\Support\Import\XlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports composition spreadsheets (one sheet = one party template, one row = one slot)
 * into saved builds + party templates.
 *
 * Expected row layout (column positions are detected from the "CAPE" column):
 *   weapon | player name | head | armor | shoes | offhand / swaps / tips | cape | food
 * Rows without gear ("WINTER BEAR !", "TANKS -> MIN T7 SIEGE HAMMER") become the template briefing.
 * The player-name column is ignored. Re-importing the same file updates builds and templates in place.
 */
class RosterSpreadsheetImporter
{
    private const GEAR_SLOTS = ['head' => -4, 'armor' => -3, 'shoe' => -2, 'offhand' => -1, 'cape' => 0, 'food' => 1];

    private const IGNORED_TEXT = ['TOTAL NUMBER', 'OFF HANDS/SWAPS/TIPS'];

    private const TYPE_BY_CATEGORY = ['Tank' => 'tank', 'Healer' => 'heal', 'DPS' => 'dps', 'Support' => 'supp', 'Battle Mount' => 'mount'];

    private AlbionItemResolver $resolver;

    /** @var array<string, string> build name => import key, for names used during this run */
    private array $usedNames = [];

    /** @var array<string, mixed> */
    private array $report = [];

    /** @var array<string, true> build keys already handled in this run */
    private array $seenKeys = [];

    public function __construct(private XlsxReader $reader) {}

    /**
     * @return array{templates: list<array{name: string, slots: int, status: string}>, builds_created: int, builds_updated: int, unresolved: array<string, int>, dry_run: bool}
     */
    public function import(string $path, int $userId, string $prefix, bool $dryRun = false): array
    {
        $sheets = $this->reader->read($path);
        $this->resolver = new AlbionItemResolver;
        $this->usedNames = [];
        $this->seenKeys = [];
        $this->report = ['templates' => [], 'builds_created' => 0, 'builds_updated' => 0, 'unresolved' => [], 'dry_run' => $dryRun];

        DB::beginTransaction();
        try {
            foreach ($sheets as $sheetName => $rows) {
                $this->importSheet(trim($sheetName), $rows, $userId, $prefix);
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        arsort($this->report['unresolved']);

        return $this->report;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function importSheet(string $sheetName, array $rows, int $userId, string $prefix): void
    {
        $capeColumn = $this->detectCapeColumn($rows);
        if ($capeColumn === null) {
            $this->report['templates'][] = ['name' => $sheetName, 'slots' => 0, 'status' => 'skipped (no CAPE column found)'];

            return;
        }

        $weaponColumn = $capeColumn - 6;
        $nameColumn = $weaponColumn + 1;
        $slots = [];
        $briefing = [];

        foreach ($rows as $rowNumber => $cells) {
            $get = fn (int $col) => $col >= 1 ? ($cells[self::letter($col)] ?? '') : '';
            $weaponText = $get($weaponColumn);
            $gear = array_map(fn ($offset) => $get($capeColumn + $offset), self::GEAR_SLOTS);
            // Briefing lines may also sit in gear columns ("TANKS -> MIN T7 SIEGE HAMMER"), so a gear row
            // needs a cape or at least one recognised head/armor/shoe item.
            $hasGear = preg_match('/\bCAPE\b/i', $gear['cape']) === 1
                || collect(['head', 'armor', 'shoe'])->contains(fn ($slot) => $gear[$slot] !== '' && $this->resolver->parseCell($gear[$slot], $slot)['primary'] !== null);
            $isMount = $weaponText !== '' && $this->isMountText($weaponText);
            $weaponItems = $weaponText !== '' ? $this->resolver->itemsIn($weaponText) : [];

            if ($hasGear || $isMount || ($weaponItems && $this->resolver->slotOf($weaponItems[0]) === 'weapon')) {
                $location = "{$sheetName}!".self::letter($weaponColumn).$rowNumber;
                $build = $this->upsertBuild($weaponText, $gear, $isMount, $get($nameColumn), $sheetName, $location, $userId);
                $slots[] = $this->templateSlot($build, $weaponText);

                continue;
            }

            foreach ($cells as $text) {
                if (! is_numeric($text) && ! in_array(AlbionItemResolver::normalize($text), self::IGNORED_TEXT, true)) {
                    $briefing[] = $text;
                }
            }
        }

        if (! $slots) {
            $this->report['templates'][] = ['name' => $sheetName, 'slots' => 0, 'status' => 'skipped (no slots)'];

            return;
        }

        $name = mb_substr(trim($prefix) !== '' ? trim($prefix).' · '.$sheetName : $sheetName, 0, 255);
        $structure = [];
        foreach ($slots as $i => $slot) {
            $structure[$i + 1] = $slot;
        }

        $template = PartyTemplate::firstOrNew(['name' => $name]);
        $status = $template->exists ? 'updated' : 'created';
        $template->fill([
            'size' => count($structure),
            'structure' => $structure,
            'briefing' => $briefing ? implode("\n", array_values(array_unique($briefing))) : null,
        ]);
        $template->created_by ??= $userId;
        $template->save();

        $this->report['templates'][] = ['name' => $name, 'slots' => count($structure), 'status' => $status];
    }

    /**
     * @param  array<string, string>  $gear
     */
    private function upsertBuild(string $weaponText, array $gear, bool $isMount, string $nameCell, string $sheetName, string $location, int $userId): SavedBuild
    {
        $primarySlot = $isMount ? 'mount' : 'weapon';
        $parsed = [$primarySlot => $this->resolver->parseCell($weaponText, $primarySlot)];
        foreach ($gear as $slot => $text) {
            if ($text !== '') {
                $parsed[$slot] = $this->resolver->parseCell($text, $slot);
            }
        }

        $items = [];
        $swaps = [];
        $notes = [];
        foreach ($parsed as $slot => $result) {
            if ($result['primary']) {
                $items[$slot] = $result['primary'];
            }
            array_push($swaps, ...$result['swaps']);
        }

        // An alternative mentioned elsewhere fills an empty slot ("1H HAMMER-LEERING C" => offhand Leering Cane).
        foreach ($swaps as $i => $swap) {
            if ($swap['situation'] === null && $swap['item_id'] && $swap['slot'] !== '' && ! isset($items[$swap['slot']])) {
                $items[$swap['slot']] = GameRole::find($swap['item_id']);
                unset($swaps[$i]);
            }
        }

        foreach ($gear as $slot => $text) {
            $understood = ($parsed[$slot]['consumed'] ?? false) && ($slot === 'offhand' || isset($items[$slot]));
            if ($text === '' || $understood) {
                continue;
            }
            $notes[] = $slot === 'offhand' ? $text : SavedBuild::SLOT_LABELS[$slot].': '.$text;

            $isIntentionallyEmpty = str_contains(AlbionItemResolver::normalize($text), 'ANY SHOES');
            if (! isset($items[$slot]) && $slot !== 'offhand' && ! $isIntentionallyEmpty) {
                $this->unresolved(SavedBuild::SLOT_LABELS[$slot].': '.$text);
            }
        }
        if (! isset($items[$primarySlot])) {
            $this->unresolved(($isMount ? 'Mount' : 'Weapon').': '.($weaponText ?: '(empty)')." ({$location})");
        }

        // Caller notes typed in upper case in the player column ("FAKE ENGAGE OR JUDI HELM ENGAGE").
        if ($nameCell !== '' && mb_strtoupper($nameCell) === $nameCell && str_contains($nameCell, ' ') && mb_strlen($nameCell) > 12) {
            $notes[] = $nameCell;
        }

        $key = sha1(implode('|', array_map([AlbionItemResolver::class, 'normalize'], [$weaponText, ...array_values($gear)])));
        $build = SavedBuild::firstOrNew(['import_key' => $key]);
        $isNew = ! $build->exists;

        $weapon = $items['weapon'] ?? null;
        $mount = $items['mount'] ?? null;
        $category = $weapon?->category ?? ($mount ? 'Support' : 'DPS');

        $attributes = [
            'name' => $isNew ? $this->uniqueName($weaponText ?: 'Unnamed', $key, $gear['armor'], $sheetName) : $build->name,
            'role_category' => in_array($category, SavedBuild::CATEGORIES, true) ? $category : 'DPS',
            'abilities' => AlbionItemResolver::abilitiesIn($weaponText),
            'notes' => $notes ? implode("\n", array_unique($notes)) : null,
            'swaps' => array_values($this->uniqueSwaps($swaps)) ?: null,
        ];
        foreach (array_keys(SavedBuild::SLOT_CATEGORIES) as $slot) {
            $attributes[$slot.'_id'] = isset($items[$slot]) ? $items[$slot]->id : null;
        }

        $build->fill($attributes);
        if ($isNew) {
            $build->created_by = $userId;
        }
        $build->save();
        $this->usedNames[$build->name] = $key;
        if (! isset($this->seenKeys[$key])) {
            $this->report[$isNew ? 'builds_created' : 'builds_updated']++;
            $this->seenKeys[$key] = true;
        }

        return $build;
    }

    /**
     * @return array{role: string, note: string, type: string, build_id: int}
     */
    private function templateSlot(SavedBuild $build, string $weaponText): array
    {
        $item = $build->weapon_id ? GameRole::find($build->weapon_id) : ($build->mount_id ? GameRole::find($build->mount_id) : null);
        $role = $item?->name ?? AlbionItemResolver::titleCase(Str::before($weaponText, '/'));

        if (preg_match('/\((OFF|DEF|CLEANSE)\)|\bOFF SETUP\b/i', $weaponText, $m)) {
            $role .= ' ('.ucfirst(strtolower($m[1] ?? 'Off')).')';
        }

        $type = $build->mount_id && ! $build->weapon_id ? 'mount' : (self::TYPE_BY_CATEGORY[$build->role_category] ?? 'any');
        $note = (string) $build->abilities;

        return ['role' => mb_substr($role, 0, 100), 'note' => mb_substr($note, 0, 255), 'type' => $type, 'build_id' => $build->id];
    }

    private function uniqueName(string $weaponText, string $key, string $armor, string $sheetName): string
    {
        $base = mb_substr(AlbionItemResolver::titleCase($weaponText), 0, 200);
        $candidates = [$base, $base.' · '.AlbionItemResolver::titleCase($armor), $base.' · '.$sheetName];

        foreach ($candidates as $candidate) {
            $taken = ($this->usedNames[$candidate] ?? $key) !== $key
                || SavedBuild::where('name', $candidate)->where(fn ($q) => $q->whereNull('import_key')->orWhere('import_key', '!=', $key))->exists();
            if (! $taken) {
                return $candidate;
            }
        }

        return $base.' · '.substr($key, 0, 6);
    }

    /**
     * @param  array<int, array{situation: string|null, slot: string, item_id: int|null, text: string}>  $swaps
     * @return array<string, array{situation: string|null, slot: string, item_id: int|null, text: string}>
     */
    private function uniqueSwaps(array $swaps): array
    {
        $unique = [];
        foreach ($swaps as $swap) {
            $unique[implode('|', [$swap['situation'], $swap['slot'], $swap['item_id'], $swap['text']])] = $swap;
        }

        return $unique;
    }

    private function unresolved(string $text): void
    {
        $this->report['unresolved'][$text] = ($this->report['unresolved'][$text] ?? 0) + 1;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function detectCapeColumn(array $rows): ?int
    {
        $counts = [];
        foreach ($rows as $cells) {
            foreach ($cells as $column => $text) {
                if (preg_match('/\bCAPE\b/i', $text)) {
                    $counts[$column] = ($counts[$column] ?? 0) + 1;
                }
            }
        }
        if (! $counts) {
            return null;
        }
        arsort($counts);
        $column = self::number((string) array_key_first($counts));

        return $column >= 7 ? $column : null;
    }

    private function isMountText(string $text): bool
    {
        $items = $this->resolver->itemsIn($text);
        if ($items && $items[0]->category === 'Battle Mount') {
            return true;
        }

        return ! $items && preg_match('/\b(BASI|BASILISK|BEHE|CHAR+IOT|MAMMOTH|ENT)\b/i', $text) === 1;
    }

    private static function letter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $column = intdiv($column - 1, 26);
        }

        return $letter;
    }

    private static function number(string $letter): int
    {
        $n = 0;
        foreach (str_split($letter) as $char) {
            $n = $n * 26 + ord($char) - 64;
        }

        return $n;
    }
}
