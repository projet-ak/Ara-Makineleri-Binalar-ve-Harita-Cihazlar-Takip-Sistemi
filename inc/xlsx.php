<?php
/**
 * ErnXlsx — kurumsal biçimli XLSX üretici (harici kütüphane gerektirmez, ZipArchive yeterli).
 *
 * Stil indeksleri:
 *  0 varsayılan
 *  1 başlık bandı — büyük beyaz kalın, koyu yeşil zemin
 *  2 başlık bandı — küçük beyaz, koyu yeşil zemin
 *  3 kolon başlığı — beyaz kalın, orta yeşil zemin, kenarlık
 *  4 metin hücre — kenarlık
 *  5 sayı hücre — #.##0,00 (negatif kırmızı), kenarlık
 *  6 toplam sayı — kalın, gri zemin, kenarlık
 *  7 toplam metin — kalın, gri zemin, kenarlık
 *  8 metin küçük gri (dipnot)
 *  9 tam sayı hücre — #.##0, kenarlık
 */
class ErnXlsx {
    private array $rows = [];
    private array $merges = [];
    private array $cols = [];
    private ?string $logoPath = null;
    private int $logoW = 195, $logoH = 120;
    private string $sheetName;

    public function __construct(string $sheetName = 'Rapor') {
        $this->sheetName = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $sheetName), 0, 31);
    }
    public function setCols(array $widths): void { $this->cols = $widths; }
    /** $cells: her eleman ya skaler (stil 4) ya da [deger, stilIdx] */
    public function addRow(array $cells, float $height = 0): void { $this->rows[] = [$cells, $height]; }
    public function merge(string $range): void { $this->merges[] = $range; }
    public function setLogo(string $path, int $w, int $h): void { $this->logoPath = $path; $this->logoW = $w; $this->logoH = $h; }

    private static function colAd(int $i): string {
        $s = '';
        while ($i >= 0) { $s = chr(65 + $i % 26) . $s; $i = intdiv($i, 26) - 1; }
        return $s;
    }
    private static function xml($s): string { return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

    public function indir(string $dosyaAdi): void {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $z = new ZipArchive();
        $z->open($tmp, ZipArchive::OVERWRITE);

        $hasLogo = $this->logoPath && is_file($this->logoPath);
        $z->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            ($hasLogo ? '<Default Extension="png" ContentType="image/png"/>' : '') .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            ($hasLogo ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '') .
            '</Types>');
        $z->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $z->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="' . self::xml($this->sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');

        $z->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<numFmts count="2">' .
            '<numFmt numFmtId="164" formatCode="#,##0.00;[Red]\-#,##0.00"/>' .
            '<numFmt numFmtId="165" formatCode="#,##0"/></numFmts>' .
            '<fonts count="7">' .
            '<font><sz val="10"/><name val="Calibri"/></font>' .                                                    // 0
            '<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .                          // 1
            '<font><sz val="10"/><color rgb="FFB8E8E0"/><name val="Calibri"/></font>' .                              // 2
            '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .                          // 3
            '<font><b/><sz val="10"/><name val="Calibri"/></font>' .                                                 // 4
            '<font><sz val="8"/><color rgb="FF7A8B88"/><name val="Calibri"/></font>' .                               // 5
            '<font><b/><sz val="12"/><color rgb="FF00584E"/><name val="Calibri"/></font></fonts>' .                  // 6
            '<fills count="5">' .
            '<fill><patternFill patternType="none"/></fill>' .
            '<fill><patternFill patternType="gray125"/></fill>' .
            '<fill><patternFill patternType="solid"><fgColor rgb="FF00584E"/></patternFill></fill>' .                // 2 koyu yeşil
            '<fill><patternFill patternType="solid"><fgColor rgb="FF007A6A"/></patternFill></fill>' .                // 3 orta yeşil
            '<fill><patternFill patternType="solid"><fgColor rgb="FFEAF1EF"/></patternFill></fill></fills>' .        // 4 açık gri-yeşil
            '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' .
            '<border><left style="thin"><color rgb="FFC9D5D2"/></left><right style="thin"><color rgb="FFC9D5D2"/></right>' .
            '<top style="thin"><color rgb="FFC9D5D2"/></top><bottom style="thin"><color rgb="FFC9D5D2"/></bottom><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="10">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' .
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>' .
            '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>' .
            '<xf numFmtId="0" fontId="3" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' .
            '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' .
            '<xf numFmtId="164" fontId="4" fillId="4" borderId="1" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' .
            '<xf numFmtId="0" fontId="4" fillId="4" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' .
            '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" applyFont="1"/>' .
            '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' .
            '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');

        // Sayfa
        $sx = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews>' .
            '<sheetFormatPr defaultRowHeight="16"/>';
        if ($this->cols) {
            $sx .= '<cols>';
            foreach ($this->cols as $i => $w)
                $sx .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
            $sx .= '</cols>';
        }
        $sx .= '<sheetData>';
        foreach ($this->rows as $ri => [$cells, $h]) {
            $sx .= '<row r="' . ($ri + 1) . '"' . ($h > 0 ? ' ht="' . $h . '" customHeight="1"' : '') . '>';
            foreach ($cells as $ci => $cell) {
                if ($cell === null) continue;
                [$v, $s] = is_array($cell) ? $cell : [$cell, 4];
                $ref = self::colAd($ci) . ($ri + 1);
                if (is_int($v) || is_float($v)) {
                    $sx .= '<c r="' . $ref . '" s="' . $s . '"><v>' . $v . '</v></c>';
                } elseif ($v === '') {
                    $sx .= '<c r="' . $ref . '" s="' . $s . '"/>';
                } else {
                    $sx .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml($v) . '</t></is></c>';
                }
            }
            $sx .= '</row>';
        }
        $sx .= '</sheetData>';
        if ($this->merges) {
            $sx .= '<mergeCells count="' . count($this->merges) . '">';
            foreach ($this->merges as $m) $sx .= '<mergeCell ref="' . $m . '"/>';
            $sx .= '</mergeCells>';
        }
        $sx .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>';
        if ($hasLogo) $sx .= '<drawing r:id="rIdD1"/>';
        $sx .= '</worksheet>';
        $z->addFromString('xl/worksheets/sheet1.xml', $sx);

        if ($hasLogo) {
            $z->addFromString('xl/worksheets/_rels/sheet1.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship Id="rIdD1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>');
            // Logo: A1 hücresinden başlayıp piksel ölçüsüyle serbest yerleşim (EMU = px * 9525)
            $emuW = $this->logoW * 9525; $emuH = $this->logoH * 9525;
            $z->addFromString('xl/drawings/drawing1.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
                '<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>76200</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>76200</xdr:rowOff></xdr:from>' .
                '<xdr:ext cx="' . $emuW . '" cy="' . $emuH . '"/>' .
                '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="ERN Logo"/><xdr:cNvPicPr/></xdr:nvPicPr>' .
                '<xdr:blipFill><a:blip r:embed="rIdImg1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>' .
                '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $emuW . '" cy="' . $emuH . '"/></a:xfrm>' .
                '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>');
            $z->addFromString('xl/drawings/_rels/drawing1.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship Id="rIdImg1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/></Relationships>');
            $z->addFile($this->logoPath, 'xl/media/image1.png');
        }
        $z->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($dosyaAdi) . '.xlsx"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }
}
