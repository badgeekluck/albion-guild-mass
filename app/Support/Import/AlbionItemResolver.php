<?php

namespace App\Support\Import;

use App\Enums\Situation;
use App\Models\GameRole;
use App\Models\SavedBuild;
use Illuminate\Support\Collection;

/**
 * Understands the shorthand callers use in composition spreadsheets
 * ("ASAS HOOD", "JUDI ARMOR", "STALKER/VALOR BOOTS", "ROYAL SHOES(MONKEY->CULTIST SANDALS)")
 * and turns a cell into a primary item, alternatives and situational swaps.
 *
 * Only unambiguous aliases are mapped; anything else is reported as unresolved
 * and kept as text so nothing from the sheet is lost.
 */
class AlbionItemResolver
{
    /**
     * Spreadsheet shorthand => GameRole name. null = known phrase that is not an item
     * (prevents "HELLFIRE POT" from matching the "Hellfire Hands" weapon).
     */
    public const ALIASES = [
        // weapons — tanks
        'MACE' => '1h Mace', '1H MACE' => '1h Mace', 'HEAVY MACE' => 'Heavy Mace',
        'INCIBUS' => 'Incubus Mace', 'INCUBUS' => 'Incubus Mace', 'CAMLANN' => 'Camlann Mace',
        'OATHKEEPER' => 'Oathkeeper', 'DREADSTORM' => 'Dreadstorm Monarch', 'BEDROCK' => 'Bedrock Mace',
        '1H HAMMER' => '1h Hammer', 'POLEHAMMER' => 'Polehammer', 'POLE' => 'Polehammer',
        'GREAT HAMMER' => 'Great Hammer', 'HOJ' => 'Hand of Justice', 'HAND OF JUSTICE' => 'Hand of Justice',
        'GREAT ARCANE' => 'Great Arcane', '1H ARCANE' => '1h Arcane',
        'STAFF OF BALANCE' => 'Staff of Balance', 'BMS' => 'Black Monk Stave', 'BLACK MONK' => 'Black Monk Stave',
        'STEELGAZE' => 'Stillgaze Staff', 'STILLGAZE' => 'Stillgaze Staff', 'GROVEKEEPER' => 'Grovekeeper',
        // weapons — dps
        'SPIKED' => 'Spiked Gauntlets', 'PERMA' => 'Permafrost Prism', 'PERMAFROST' => 'Permafrost Prism',
        'REALM' => 'Realmbreaker', 'REALMBREAKER' => 'Realmbreaker', 'HELLFIRE' => 'Hellfire Hands',
        'LONGBOW' => 'Longbow', 'ENERGY SHAPER' => 'Energy Shaper', 'CARVING' => 'Carving Sword',
        'GALA' => 'Galatine Pair', 'BLOODLETTER' => 'Bloodletter', 'WAILING' => 'Wailing Bow',
        'RIFT' => 'Rift Glaive', 'DEMONFANG' => 'Demonfang', 'DFANG' => 'Demonfang', 'DAWNSONG' => 'Dawnsong',
        'GREAT FROST' => 'Great Frost Staff', 'LIGHTCALLER' => 'Lightcaller', 'WILDFIRE' => 'Wildfire Staff',
        // weapons — support / healer
        'DAMNATION' => 'Damnation Staff', 'DAMNA' => 'Damnation Staff', 'LOCUS' => 'Locus',
        'ENIGMA' => 'Enigmatic Staff', 'ENIG' => 'Enigmatic Staff', 'ROOTBOUND' => 'Rootbound Staff',
        'OCCULT' => 'Occult Staff', 'SHADOWCALLER' => 'Shadowcaller', 'ROTCALLER' => 'Rotcaller Staff',
        'ICICLE' => 'Icicle Staff', 'EVENSONG' => 'Evensong', 'WITCHWORK' => 'Witchwork Staff',
        'EXALTED' => 'Exalted Staff', 'HALLOWFALL' => 'Hallowfall', 'HALLOW' => 'Hallowfall',
        'REDEMPTION' => 'Redemption Staff', 'FALLEN' => 'Fallen Staff', '1H NATURE' => 'Nature Staff',
        // head
        'CLERIC COWL' => 'Cleric Cowl', 'SOLDIER HELMET' => 'Soldier Helmet', 'SOLDIER HELM' => 'Soldier Helmet',
        'ASSASSIN HOOD' => 'Assassin Hood', 'ASAS HOOD' => 'Assassin Hood', 'ASAS H' => 'Assassin Hood',
        'DEMON HELMET' => 'Demon Helmet', 'DEMON HELM' => 'Demon Helmet', 'MISTWALKER HOOD' => 'Mistwalker Hood',
        'HELLION HOOD' => 'Hellion Hood', 'JUDI HELMET' => 'Judicator Helmet', 'JUDI HELM' => 'Judicator Helmet',
        'KNIGHT HELMET' => 'Knight Helmet', 'STALKER HOOD' => 'Stalker Hood', 'FEYSCALE HAT' => 'Feyscale Hat',
        'FEY HAT' => 'Feyscale Hat', 'FEYHAT' => 'Feyscale Hat', 'HOOD OF TENACITY' => 'Hood of Tenacity',
        'HOOD OF TEANCITY' => 'Hood of Tenacity', 'DRUID COWL' => 'Druid Cowl', 'HELMET OF VALOR' => 'Helmet of Valor',
        'GUARDIAN HELM' => 'Guardian Helmet', 'GUARDIAN HELMET' => 'Guardian Helmet',
        // armor
        'JUDI ARMOR' => 'Judicator Armor', 'KNIGHT ARMOR' => 'Knight Armor', 'GUARDIAN ARMOR' => 'Guardian Armor',
        'DEMON ARMOR' => 'Demon Armor', 'JACKET OF TENACITY' => 'Jacket of Tenacity', 'TENA' => 'Jacket of Tenacity',
        'MISTWALKER JACKET' => 'Mistwalker Jacket', 'ROYAL ARMOR' => 'Royal Armor', 'ROYAL JACKET' => 'Royal Jacket',
        'SCHOLAR ROBE' => 'Scholar Robe', 'ARMOR OF VALOR' => 'Armor of Valor', 'DUSKWEAR ARMOR' => 'Duskweaver Armor',
        'DUSKWEAVER ARMOR' => 'Duskweaver Armor', 'SOLDIER ARMOR' => 'Soldier Armor', 'SOLDIER AR' => 'Soldier Armor',
        'HELLION JACKET' => 'Hellion Jacket',
        // shoes
        'ROYAL SHOES' => 'Royal Shoes', 'MERCENARY SHOES' => 'Mercenary Shoes', 'MERC SHOES' => 'Mercenary Shoes',
        'STALKER SHOES' => 'Stalker Shoes', 'STALKER' => 'Stalker Shoes', 'BOOTS OF VALOR' => 'Boots of Valor',
        'VALOR BOOTS' => 'Boots of Valor', 'MAGE SANDALS' => 'Mage Sandals', 'CLERIC SANDALS' => 'Cleric Sandals',
        'FEYSCALE SANDALS' => 'Feyscale Sandals', 'FEYSCALE SAN' => 'Feyscale Sandals', 'MISTWALKER SHOES' => 'Mistwalker Shoes',
        'CULTIST SANDALS' => 'Cultist Sandals', 'DRUID SANDALS' => 'Druid Sandals', 'GUARDIAN BOOTS' => 'Guardian Boots',
        'GG BOOTS' => 'Graveguard Boots', 'DUSKWEAVER BOOTS' => 'Duskweaver Boots',
        // offhands
        'ASTRAL AEGIS' => 'Astral Aegis', 'AEGIS' => 'Astral Aegis', 'LEERING CANE' => 'Leering Cane', 'LEERING C' => 'Leering Cane',
        'BLUEFLAME TORCH' => 'Blueflame Torch', 'BLUEFLAME T' => 'Blueflame Torch', 'FACEBREAKER' => 'Facebreaker',
        'TIMELOCKED' => 'Timelocked Grimoire', 'MISTCALLER' => 'Mistcaller',
        // capes
        'SMUGLER CAPE' => 'Smuggler Cape', 'SMUGGLER CAPE' => 'Smuggler Cape', 'MORGANA CAPE' => 'Morgana Cape',
        'LYM CAPE' => 'Lymhurst Cape', 'LYMHURST CAPE' => 'Lymhurst Cape', 'BRECILIEN CAPE' => 'Brecilien Cape',
        // food & potions
        'BEEF SANDWICH' => 'Beef Sandwich', 'AVALONIAN PORK OMELETTE' => 'Avalonian Pork Omelette',
        'AVA PORK OMELETTE' => 'Avalonian Pork Omelette', 'AVA OMELETTE' => 'Avalonian Pork Omelette',
        'PORK OMELETTE' => 'Pork Omelette', 'OMELETTE' => 'Pork Omelette', 'STEW' => 'Beef Stew',
        'CLEANSE POT' => 'Cleansing Potion', 'RESIST POT' => 'Resistance Potion',
        // battle mounts
        'BEHE' => 'Behemoth', 'BEHEMOTH' => 'Behemoth', 'CHARRIOT' => 'Tower Chariot', 'CHARIOT' => 'Tower Chariot',
        'VENOM BASI' => 'Venom Basilisk', 'VENOM BASILISK' => 'Venom Basilisk',
        'FLAME BASI' => 'Flame Basilisk', 'FLAME BASILISK' => 'Flame Basilisk',
        // known non-items (or ambiguous) — matched so they don't fall through to a shorter alias
        'HELLFIRE POT' => null, 'STICKY POTION' => null, 'CRAB OMELETTE' => null, 'ANY SHOES' => null,
        'SIEGE HAMMER' => null,
    ];

    /** Words that carry no information on their own inside an item cell. */
    public const FILLER_WORDS = ['SWAP', 'SWAPS', 'OR', 'AND', 'W', 'OPEN', 'AGAINST', 'ENEMY', 'OFF', 'DEF', 'SETUP', 'ONLY', 'USE'];

    public const SITUATION_PATTERNS = [
        'monkey' => '/\b(MONKEY|MNKY)\b/',
        'kite' => '/\bKITE\b/',
        'melee_clap' => '/\bCLAP\b/',
        'castle' => '/\bCASTLE\b/',
        'brawl' => '/\bBRAWL\b/',
    ];

    /** @var Collection<string, GameRole> lower-case name => item */
    private Collection $items;

    /** @var array<string, string> GameRole category => build slot */
    private array $slotByCategory = [];

    /** @var list<string> aliases sorted longest first */
    private array $aliasOrder;

    public function __construct()
    {
        $this->items = GameRole::all()->keyBy(fn (GameRole $r) => mb_strtolower($r->name));

        foreach (SavedBuild::SLOT_CATEGORIES as $slot => $categories) {
            foreach ($categories as $category) {
                $this->slotByCategory[$category] = $slot;
            }
        }

        $this->aliasOrder = array_keys(self::ALIASES);
        usort($this->aliasOrder, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtoupper($text);
        $text = str_replace(['OMMELETTE', 'OMLET '], ['OMELETTE', 'OMELETTE '], $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    public function slotOf(GameRole $item): ?string
    {
        return $this->slotByCategory[$item->category] ?? null;
    }

    /**
     * Items mentioned in a piece of text, in order of appearance.
     *
     * @return list<GameRole>
     */
    public function itemsIn(string $text, ?string &$leftover = null): array
    {
        $text = ' '.self::normalize($text).' ';
        $found = [];

        foreach ($this->aliasOrder as $alias) {
            $pattern = '/(?<![A-Z0-9])'.preg_quote($alias, '/').'(?![A-Z])/';
            $text = (string) preg_replace_callback($pattern, function ($m) use ($alias, &$found) {
                $name = self::ALIASES[$alias];
                $item = $name ? $this->items->get(mb_strtolower($name)) : null;
                if ($item) {
                    $found[$m[0][1]] = $item;
                }

                return str_repeat('#', strlen($m[0][0])); // consume so shorter aliases don't re-match
            }, $text, flags: PREG_OFFSET_CAPTURE);
        }

        ksort($found);

        // Words that were not part of an item, minus filler ("SWAP", "OR", "OPEN"...).
        $leftover = trim((string) preg_replace(
            ['/#+|[^A-Z ]/', '/\b('.implode('|', self::FILLER_WORDS).')\b/', '/\s+/'],
            [' ', ' ', ' '],
            $text,
        ));

        return array_values($found);
    }

    /** @return list<string> situation values mentioned in the text */
    public function situationsIn(string $text): array
    {
        $text = self::normalize($text);

        return array_keys(array_filter(self::SITUATION_PATTERNS, fn ($pattern) => preg_match($pattern, $text) === 1));
    }

    /**
     * Parses one spreadsheet cell for the given build slot.
     *
     * @return array{primary: GameRole|null, swaps: list<array{situation: string|null, slot: string, item_id: int|null, text: string}>, consumed: bool}
     *                                                                                                                                                  consumed = every non-situational clause was fully understood
     */
    public function parseCell(string $cell, string $slot): array
    {
        $primary = null;
        $swaps = [];
        $consumed = true;

        // "ROYAL SHOES(MONKEY->CULTIST SANDALS)" => ["ROYAL SHOES", "MONKEY->CULTIST SANDALS"]
        $groups = preg_split('/[()]/', $cell) ?: [];

        foreach ($groups as $group) {
            foreach (preg_split('#/#', $group) ?: [] as $clause) {
                $clause = trim($clause, " .-\t");
                if ($clause === '') {
                    continue;
                }

                $situations = $this->situationsIn($clause);

                if ($situations) {
                    // "KITE OR CASTLE->POLE SWAP6.1": the swap is what comes after the arrow.
                    $target = str_contains($clause, '->') ? substr($clause, strpos($clause, '->') + 2) : $clause;
                    $items = $this->itemsIn($target);
                    foreach ($situations as $situation) {
                        if (! $items) {
                            $swaps[] = ['situation' => $situation, 'slot' => '', 'item_id' => null, 'text' => self::titleCase($clause)];
                        }
                        foreach ($items as $item) {
                            $swaps[] = ['situation' => $situation, 'slot' => (string) $this->slotOf($item), 'item_id' => $item->id, 'text' => ''];
                        }
                    }

                    continue;
                }

                $items = $this->itemsIn($clause, $leftover);
                if (mb_strlen((string) $leftover) >= 3) {
                    $consumed = false; // extra words (ability, enchant, unknown item) are worth a note
                }

                foreach ($items as $item) {
                    if ($primary === null && $this->slotOf($item) === $slot) {
                        $primary = $item;
                    } elseif ($item->id !== $primary?->id) {
                        $swaps[] = ['situation' => null, 'slot' => (string) $this->slotOf($item), 'item_id' => $item->id, 'text' => ''];
                    }
                }
            }
        }

        // Enchant / tier hints ("MORGANA CAPE 5.3", "OMELETTE .3") are worth keeping as a note.
        if (preg_match('/(^|\s)\d?\.\d\b/', $cell)) {
            $consumed = false;
        }

        return ['primary' => $primary, 'swaps' => $swaps, 'consumed' => $consumed];
    }

    /** "HEAVY MACE/PURGE GALA OR HOLD ENGAGE" => "Heavy Mace / Purge Gala or Hold Engage" */
    public static function titleCase(string $text): string
    {
        $text = mb_convert_case(mb_strtolower(trim($text)), MB_CASE_TITLE);
        $text = (string) preg_replace_callback('/\b([QWE]\d|1h|2h|W\d|T\d)\b/i', fn ($m) => mb_strtoupper($m[1]), $text);
        $text = (string) preg_replace('/\s*\/\s*/', ' / ', $text);

        return str_replace([' Or ', ' Of ', ' To ', ' For '], [' or ', ' of ', ' to ', ' for '], $text);
    }

    /** "LONGBOW W4", "1H NATURE Q3-W5", "HALLOWFALL W-BEAM" => "W4", "Q3 W5", "W Beam" */
    public static function abilitiesIn(string $text): ?string
    {
        $text = self::normalize($text);
        preg_match_all('/\b([QWE]\d)\b|\bW[ -]?(BEAM|PIERCE|HOLY ORB|RUNE)\b|\bWRUNE\b/', $text, $matches, PREG_SET_ORDER);

        $abilities = [];
        foreach ($matches as $m) {
            $abilities[] = match (true) {
                ! empty($m[1]) => $m[1],
                ! empty($m[2]) => 'W '.self::titleCase($m[2]),
                default => 'W Rune',
            };
        }

        return $abilities ? implode(' · ', array_unique($abilities)) : null;
    }
}
