<?php

namespace Tests\Concerns;

use ZipArchive;

trait BuildsXlsx
{
    /**
     * Writes a minimal .xlsx (inline strings) and returns its path.
     *
     * @param  array<string, array<int, array<string, string>>>  $sheets  name => row => column => value
     */
    protected function makeXlsx(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $sheetEntries = '';
        $relEntries = '';
        $i = 0;

        foreach ($sheets as $name => $rows) {
            $i++;
            $sheetEntries .= '<sheet name="'.htmlspecialchars($name, ENT_XML1).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $relEntries .= '<Relationship Id="rId'.$i.'" Type="'.$rel.'/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';

            $xmlRows = '';
            foreach ($rows as $rowNumber => $cells) {
                $xmlRows .= '<row r="'.$rowNumber.'">';
                foreach ($cells as $column => $value) {
                    $xmlRows .= '<c r="'.$column.$rowNumber.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
                }
                $xmlRows .= '</row>';
            }
            $zip->addFromString("xl/worksheets/sheet{$i}.xml", '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.$main.'"><sheetData>'.$xmlRows.'</sheetData></worksheet>');
        }

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.$main.'" xmlns:r="'.$rel.'"><sheets>'.$sheetEntries.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relEntries.'</Relationships>');
        $zip->close();

        return $path;
    }
}
