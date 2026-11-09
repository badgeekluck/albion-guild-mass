<?php

namespace App\Support\Import;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal, dependency-free .xlsx reader: cell values only (cached values for formulas).
 */
class XlsxReader
{
    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const NS_PACKAGE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * @return array<string, array<int, array<string, string>>> sheet name => row => column letter => value
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Dosya bir Excel (.xlsx) dosyası olarak açılamadı.');
        }

        try {
            $shared = $this->sharedStrings($zip);
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $relations = $this->xml($zip, 'xl/_rels/workbook.xml.rels');

            $targets = [];
            foreach ($relations->children(self::NS_PACKAGE) as $relation) {
                $attributes = $relation->attributes();
                $targets[(string) $attributes['Id']] = ltrim((string) $attributes['Target'], '/');
            }

            $sheets = [];
            foreach ($workbook->children(self::NS_MAIN)->sheets->sheet as $sheet) {
                $relationId = (string) $sheet->attributes(self::NS_REL)['id'];
                $target = $targets[$relationId] ?? null;
                if (! $target) {
                    continue;
                }
                $target = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                $sheets[(string) $sheet->attributes()['name']] = $this->rows($this->xml($zip, $target), $shared);
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $strings = [];
        foreach ($this->xml($zip, 'xl/sharedStrings.xml')->children(self::NS_MAIN)->si as $item) {
            $strings[] = $this->text($item);
        }

        return $strings;
    }

    /**
     * @param  list<string>  $shared
     * @return array<int, array<string, string>>
     */
    private function rows(SimpleXMLElement $sheet, array $shared): array
    {
        $rows = [];
        foreach ($sheet->children(self::NS_MAIN)->sheetData->row as $row) {
            foreach ($row->children(self::NS_MAIN)->c as $cell) {
                $attributes = $cell->attributes();
                if (! preg_match('/^([A-Z]+)(\d+)$/', (string) $attributes['r'], $m)) {
                    continue;
                }
                $type = (string) $attributes['t'];
                $children = $cell->children(self::NS_MAIN);
                $value = match ($type) {
                    's' => $shared[(int) $children->v] ?? '',
                    'inlineStr' => $this->text($children->is),
                    default => isset($children->v) ? (string) $children->v : '',
                };
                $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
                if ($value !== '') {
                    $rows[(int) $m[2]][$m[1]] = $value;
                }
            }
        }
        ksort($rows);

        return $rows;
    }

    private function text(SimpleXMLElement $node): string
    {
        $node->registerXPathNamespace('m', self::NS_MAIN);

        return implode('', array_map('strval', $node->xpath('.//m:t') ?: []));
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $content = $zip->getFromName($name);
        if ($content === false) {
            throw new RuntimeException("Excel dosyasında {$name} bulunamadı.");
        }

        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) {
            throw new RuntimeException("Excel dosyasındaki {$name} okunamadı.");
        }

        return $xml;
    }
}
