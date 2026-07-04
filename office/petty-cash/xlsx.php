<?php
/**
 * Minimal XLSX writer — no dependencies, uses ZipArchive.
 * Usage:
 *   $xls = new XlsxWriter();
 *   $xls->addSheet('Data', [['col1','col2',...], [val,val,...], ...], ['header','number','text',...]);
 *   $xls->output('filename.xlsx');   // sends to browser
 *   $xls->save('/path/file.xlsx');   // saves to disk
 */
class XlsxWriter {
    private array $sheets = [];

    public function addSheet(string $name, array $rows, array $types = []): void {
        $this->sheets[] = ['name' => $name, 'rows' => $rows, 'types' => $types];
    }

    public function output(string $filename): void {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        echo $this->build();
        exit;
    }

    public function save(string $path): void {
        file_put_contents($path, $this->build());
    }

    private function build(): string {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        unlink($tmp);
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE);

        // [Content_Types].xml
        $ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $ct .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $ct .= '<Default Extension="xml" ContentType="application/xml"/>';
        $ct .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $ct .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.stylesheet+xml"/>';
        $ct .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        foreach ($this->sheets as $i => $_) {
            $n = $i + 1;
            $ct .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ct .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);

        // _rels/.rels
        $rels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $rels .= '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // xl/_rels/workbook.xml.rels
        $wbr  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $wbr .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $wbr .= '<Relationship Id="rId99" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wbr .= '<Relationship Id="rId98" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        foreach ($this->sheets as $i => $_) {
            $n = $i + 1;
            $wbr .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }
        $wbr .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbr);

        // xl/workbook.xml
        $wb  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $wb .= '<sheets>';
        foreach ($this->sheets as $i => $sh) {
            $n = $i + 1;
            $wb .= '<sheet name="'.htmlspecialchars($sh['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
        }
        $wb .= '</sheets></workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // Shared strings — collect all string cells
        $strings = []; $smap = [];
        $sheetXmls = [];
        foreach ($this->sheets as $si => $sh) {
            $sheetXmls[$si] = $this->buildSheet($sh['rows'], $sh['types'], $strings, $smap);
        }

        $ss  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $ss .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
        foreach ($strings as $s) {
            $ss .= '<si><t xml:space="preserve">'.htmlspecialchars($s, ENT_XML1).'</t></si>';
        }
        $ss .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ss);

        foreach ($sheetXmls as $i => $xml) {
            $zip->addFromString('xl/worksheets/sheet'.($i+1).'.xml', $xml);
        }

        // xl/styles.xml — minimal with header bold + number format
        $styles  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $styles .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $styles .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>';
        $styles .= '<fonts count="3">';
        $styles .= '<font><sz val="10"/><name val="Calibri"/></font>'; // 0: normal
        $styles .= '<font><b/><sz val="10"/><name val="Calibri"/></font>'; // 1: bold
        $styles .= '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'; // 2: bold white
        $styles .= '</fonts>';
        $styles .= '<fills count="3">';
        $styles .= '<fill><patternFill patternType="none"/></fill>';
        $styles .= '<fill><patternFill patternType="gray125"/></fill>';
        $styles .= '<fill><patternFill patternType="solid"><fgColor rgb="FFFF3D33"/></fgColor></fill>'; // 2: red header
        $styles .= '</fills>';
        $styles .= '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>';
        $styles .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $styles .= '<cellXfs count="4">';
        $styles .= '<xf numFmtId="0"  fontId="0" fillId="0" borderId="0" xfId="0"/>'; // 0: normal
        $styles .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0"/>'; // 1: number 2dp
        $styles .= '<xf numFmtId="0"  fontId="2" fillId="2" borderId="0" xfId="0"/>'; // 2: header (red bg, white bold)
        $styles .= '<xf numFmtId="0"  fontId="1" fillId="0" borderId="0" xfId="0"/>'; // 3: bold
        $styles .= '</cellXfs>';
        $styles .= '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        $zip->close();
        $data = file_get_contents($tmp);
        unlink($tmp);
        return $data;
    }

    private function buildSheet(array $rows, array $types, array &$strings, array &$smap): string {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        foreach ($rows as $ri => $row) {
            $rowNum = $ri + 1;
            $isHeader = ($ri === 0);
            $xml .= '<row r="'.$rowNum.'">';
            foreach ($row as $ci => $val) {
                $col  = $this->colName($ci);
                $ref  = $col . $rowNum;
                $type = $types[$ci] ?? ($isHeader ? 'header' : 'auto');
                if ($type === 'header') {
                    $idx = $this->sharedStr((string)$val, $strings, $smap);
                    $xml .= '<c r="'.$ref.'" t="s" s="2"><v>'.$idx.'</v></c>';
                } elseif ($type === 'number' || (($type === 'auto') && is_numeric($val) && $val !== '')) {
                    $xml .= '<c r="'.$ref.'" s="1"><v>'.($val === '' ? '' : (float)$val).'</v></c>';
                } else {
                    $idx = $this->sharedStr((string)$val, $strings, $smap);
                    $xml .= '<c r="'.$ref.'" t="s" s="'.($isHeader ? '2' : '0').'"><v>'.$idx.'</v></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function sharedStr(string $s, array &$strings, array &$smap): int {
        if (!isset($smap[$s])) {
            $smap[$s] = count($strings);
            $strings[] = $s;
        }
        return $smap[$s];
    }

    private function colName(int $idx): string {
        $name = '';
        $idx++;
        while ($idx > 0) {
            $idx--;
            $name = chr(65 + ($idx % 26)) . $name;
            $idx = intdiv($idx, 26);
        }
        return $name;
    }
}
