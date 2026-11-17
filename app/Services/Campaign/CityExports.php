<?php

namespace App\Services\Campaign;

use App\Models\Consent;
use App\Models\SterilizationCase;
use App\Models\SterilizationDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Fisierele pentru Primarie, construite din datele live: desfasuratorul Excel (aceeasi structura XML ca in plugin),
 * desfasuratorul si centralizatorul PDF, fisa pacientului, consimtamantul semnat, ZIP-ul general si ZIP-ul unui dosar.
 * Nimic nu se salveaza permanent: fisierele se genereaza in directorul temporar si se sterg dupa descarcare.
 */
class CityExports
{
    public function __construct(private readonly CityReports $reports, private readonly CityChecks $checks) {}

    // ---------------------------------------------------------------- Excel

    private static function x(mixed $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public static function column(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function cell(string $ref, mixed $value, int $style = 0): string
    {
        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::x($value).'</t></is></c>';
    }

    private static function num(string $ref, mixed $value, int $style = 0): string
    {
        return '<c r="'.$ref.'" s="'.$style.'"><v>'.self::x($value).'</v></c>';
    }

    private static function xrow(int $number, array $cells, float $height = 0): string
    {
        return '<row r="'.$number.'"'.($height > 0 ? ' ht="'.$height.'" customHeight="1"' : '').'>'.implode('', $cells).'</row>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="7"><font><sz val="10"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="20"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF0F766E"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FF0F766E"/><name val="Calibri"/></font><font><u/><sz val="10"/><color rgb="FF1D4ED8"/><name val="Calibri"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font></fonts>'
            .'<fills count="9"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFECFDF5"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFF7ED"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD6DEE3"/></left><right style="thin"><color rgb="FFD6DEE3"/></right><top style="thin"><color rgb="FFD6DEE3"/></top><bottom style="thin"><color rgb="FFD6DEE3"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="13">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="5" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="6" fillId="8" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function summarySheet(array $summary, string $monthLabel): string
    {
        $rows = [
            self::xrow(1, [self::cell('A1', 'Desfășurător intervenții de sterilizare - HiKeVet', 1)], 32),
            self::xrow(2, [self::cell('A2', 'Perioada: '.$monthLabel.' | Intervenții raportate în perioada selectată', 2)], 24),
            self::xrow(4, [self::cell('A4', 'Total intervenții', 4), self::cell('C4', 'Câini', 4), self::cell('E4', 'Pisici', 4), self::cell('G4', 'Femele / Masculi', 4)], 23),
            self::xrow(5, [self::num('A5', $summary['total'], 5), self::num('C5', $summary['dogs'], 5), self::num('E5', $summary['cats'], 5), self::cell('G5', $summary['female'].' / '.$summary['male'], 5)], 30),
            self::xrow(7, [self::cell('A7', 'Distribuție cazuri', 3), self::cell('D7', 'Proceduri efectuate', 3)], 24),
        ];
        $distribution = [['Total intervenții', $summary['total']], ['Femele', $summary['female']], ['Masculi', $summary['male']], ['Câini', $summary['dogs']], ['Pisici', $summary['cats']]];
        $procedures = array_slice($summary['procedures'], 0, 12, true);
        $names = array_keys($procedures);
        $values = array_values($procedures);
        $max = max(count($distribution), count($procedures));
        for ($i = 0; $i < $max; $i++) {
            $n = 8 + $i;
            $style = $i % 2 ? 8 : 7;
            $cells = [];
            if (isset($distribution[$i])) {
                $cells[] = self::cell('A'.$n, $distribution[$i][0], $style);
                $cells[] = self::num('B'.$n, $distribution[$i][1], $style);
            }
            if (isset($names[$i])) {
                $cells[] = self::cell('D'.$n, $names[$i], $style);
                $cells[] = self::num('E'.$n, $values[$i], $style);
            }
            $rows[] = self::xrow($n, $cells, 22);
        }
        $note = 9 + $max;
        $rows[] = self::xrow($note, [self::cell('A'.$note, 'Raport oficial generat automat de HiKeVet pentru Primăria Municipiului Târgu Mureș. Statusurile tehnice și verificările interne nu fac parte din acest document.', 12)], 34);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews>'
            .'<cols><col min="1" max="1" width="28" customWidth="1"/><col min="2" max="2" width="14" customWidth="1"/><col min="3" max="3" width="20" customWidth="1"/>'
            .'<col min="4" max="4" width="38" customWidth="1"/><col min="5" max="5" width="14" customWidth="1"/><col min="6" max="8" width="16" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $rows).'</sheetData><mergeCells count="10"><mergeCell ref="A1:H1"/><mergeCell ref="A2:H2"/>'
            .'<mergeCell ref="A4:B4"/><mergeCell ref="C4:D4"/><mergeCell ref="E4:F4"/><mergeCell ref="G4:H4"/><mergeCell ref="A5:B5"/><mergeCell ref="C5:D5"/><mergeCell ref="E5:F5"/><mergeCell ref="G5:H5"/></mergeCells>'
            .'<pageMargins left="0.45" right="0.45" top="0.55" bottom="0.55" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="1"/></worksheet>';
    }

    /**
     * ABATERE: pluginul scria in antet „Intervenții finalizate cu documente obligatorii”, desi din v2.6.6 desfasuratorul
     * include toate interventiile finalizate; antetul spune acum exact ce contine documentul.
     */
    private static function dataSheet(array $mapped, string $monthLabel): string
    {
        $columns = CityReports::OFFICIAL_COLUMNS;
        $last = self::column(count($columns));
        $rows = [
            self::xrow(1, [self::cell('A1', 'Desfășurător intervenții de sterilizare - HiKeVet', 1)], 32),
            self::xrow(2, [self::cell('A2', 'Perioada: '.$monthLabel.' | Toate intervențiile finalizate din perioada selectată', 2)], 23),
            self::xrow(3, [self::cell('A3', 'Generat la '.now()->format('d.m.Y H:i').' | Total cazuri: '.count($mapped), 12)], 26),
        ];
        $header = [];
        foreach ($columns as $i => [, $label]) {
            $header[] = self::cell(self::column($i + 1).'8', $label, 6);
        }
        $rows[] = self::xrow(8, $header, 34);
        $n = 9;
        foreach ($mapped as $index => $row) {
            $style = $index % 2 ? 8 : 7;
            $cells = [];
            foreach ($columns as $i => [$key]) {
                $ref = self::column($i + 1).$n;
                $value = $row[$key] ?? '';
                if ($key === 'NrCrt' && is_numeric($value)) {
                    $cells[] = self::num($ref, $value, $style);
                } elseif ($key === 'GreutateKg' && $value !== '' && is_numeric(str_replace(',', '.', (string) $value))) {
                    $cells[] = self::num($ref, str_replace(',', '.', (string) $value), $style);
                } else {
                    $cells[] = self::cell($ref, $value, $style);
                }
            }
            $rows[] = self::xrow($n, $cells, 36);
            $n++;
        }
        if (! $mapped) {
            $rows[] = self::xrow(9, [self::cell('A9', 'Nu există cazuri pentru filtrul selectat.', 12)], 28);
        }
        $cols = '';
        foreach ($columns as $i => [, , $width]) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="8" topLeftCell="A9" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/><cols>'.$cols.'</cols><sheetData>'.implode('', $rows).'</sheetData>'
            .'<mergeCells count="3"><mergeCell ref="A1:'.$last.'1"/><mergeCell ref="A2:'.$last.'2"/><mergeCell ref="A3:'.$last.'3"/></mergeCells>'
            .'<autoFilter ref="A8:'.$last.max(9, $n - 1).'"/><pageMargins left="0.25" right="0.25" top="0.45" bottom="0.45" header="0.2" footer="0.2"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    /** build_report_xlsx_file(): foaia „Rezumat” + foaia „Desfasurator” cu cele 25 de coloane oficiale. Intoarce continutul fisierului. */
    public function xlsx(array $mapped, string $monthLabel): string
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');
        $ct = 'application/vnd.openxmlformats-officedocument';
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="'.$ct.'.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="'.$ct.'.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="'.$ct.'.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="'.$ct.'.spreadsheetml.styles+xml"/>'
                .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                .'<Override PartName="/docProps/app.xml" ContentType="'.$ct.'.extended-properties+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                .'<dc:title>Desfășurător intervenții de sterilizare</dc:title><dc:creator>HiKeVet</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created>'
                .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:modified></cp:coreProperties>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
                .'<Application>HiKeVet</Application><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>2</vt:i4></vt:variant></vt:vector></HeadingPairs>'
                .'<TitlesOfParts><vt:vector size="2" baseType="lpstr"><vt:lpstr>Rezumat</vt:lpstr><vt:lpstr>Desfasurator</vt:lpstr></vt:vector></TitlesOfParts></Properties>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Rezumat" sheetId="1" r:id="rId1"/><sheet name="Desfasurator" sheetId="2" r:id="rId2"/></sheets>'
                .'<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
                .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => self::styles(),
            'xl/worksheets/sheet1.xml' => self::summarySheet(CityReports::reportSummary($mapped), $monthLabel),
            'xl/worksheets/sheet2.xml' => self::dataSheet($mapped, $monthLabel),
        ];
        $path = self::tempPath('xlsx');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Fișierul Excel nu a putut fi creat.');
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    // ---------------------------------------------------------------- PDF

    public static function tempPath(string $extension): string
    {
        $dir = storage_path('app/private/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir.'/hv-'.bin2hex(random_bytes(8)).'.'.$extension;
    }

    private function pdf(string $view, array $data, string $orientation = 'portrait'): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', [resource_path()]);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data + ['generatedAt' => now()->format('d.m.Y H:i')])->render(), 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return $dompdf->output();
    }

    /** Desfasuratorul PDF pentru Primarie (previzualizarea de tiparire din plugin, cu 12 coloane si semnaturi). */
    public function desfasuratorPdf(array $mapped, string $monthLabel): string
    {
        return $this->pdf('pdf.campaign.desfasurator', ['rows' => $mapped, 'summary' => CityReports::reportSummary($mapped), 'monthLabel' => $monthLabel, 'compact' => false], 'landscape');
    }

    /** Desfasuratorul din ZIP (build_package_report_pdf: 8 coloane). */
    public function packageReportPdf(array $mapped, string $monthLabel): string
    {
        return $this->pdf('pdf.campaign.desfasurator', ['rows' => $mapped, 'summary' => CityReports::reportSummary($mapped), 'monthLabel' => $monthLabel, 'compact' => true], 'landscape');
    }

    /** build_general_summary_pdf(): blocat daca exista pacienti care nu pot fi incadrati dupa specie si sex. */
    public function centralizatorPdf(array $rows, string $monthLabel): string
    {
        $summary = CityReports::animalSummary($rows);
        if ($summary['unclassified']) {
            throw new RuntimeException('Există pacienți care nu pot fi încadrați în Câine / Cățea / Motan / Pisică: '
                .implode(', ', array_map(fn ($u) => ($u['patient'] ?: $u['code']).' ('.($u['species'] ?: '-').' / '.($u['sex'] ?: '-').')', $summary['unclassified']))
                .'. Corectează Specia și Sexul, apoi generează din nou.');
        }

        return $this->pdf('pdf.campaign.centralizator', ['summary' => $summary, 'monthLabel' => $monthLabel]);
    }

    /** Imagine JPEG pe fundal alb (package_image_bytes_to_jpeg), ca data URI pentru PDF. */
    public static function jpegDataUri(string $bytes, int $max = 1000): ?string
    {
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return null;
        }
        [$w, $h] = [imagesx($image), imagesy($image)];
        $ratio = min(1, $max / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio))];
        $canvas = imagecreatetruecolor($nw, $nh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($canvas);

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }

    private static function signatureUri(?string $dataUrl): ?string
    {
        if (! $dataUrl || ! preg_match('#^data:image/(?:png|jpeg|jpg);base64,(.+)$#s', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[1], true);

        return $bytes === false ? null : self::jpegDataUri($bytes, 900);
    }

    private function documentPath(?SterilizationDocument $doc): ?string
    {
        if (! $doc || $doc->is_missing || ! $doc->stored_path || ! Storage::disk('private')->exists($doc->stored_path)) {
            return null;
        }

        return Storage::disk('private')->path($doc->stored_path);
    }

    /** build_case_sheet_pdf() */
    public function caseSheetPdf(array $row, ?string $photoUri): string
    {
        return $this->pdf('pdf.campaign.case-sheet', ['r' => $row, 'photo' => $photoUri, 'substances' => CityReports::substances($row)]);
    }

    public static function validConsentDate(?string $value): ?string
    {
        return CaseFields::sanitizeDate(substr(trim((string) $value), 0, 10));
    }

    /** build_consent_pdf(): doar consimtamantul semnat; eticheta GDPR spune explicit cum a fost dedusa. */
    public function consentPdf(array $row, Consent $consent): string
    {
        if (! $consent->isSigned()) {
            throw new RuntimeException('Consimțământul semnat nu este disponibil.');
        }
        $payload = $consent->payload ?? [];
        if (is_array($consent->visit_patients) && $consent->visit_patients) {
            $payload['visit_group_mode'] = 1;
            $payload['visit_patients'] = $consent->visit_patients;
        }
        $signedAt = $consent->signed_at?->format('Y-m-d H:i:s');
        $date = self::validConsentDate($payload['consent_date'] ?? '') ?? self::validConsentDate($signedAt);
        if (! $date) {
            foreach (['data_prezentare', 'data_interventie', 'data_programare'] as $field) {
                if ($date = CaseFields::sanitizeDate($row[$field] ?? '')) {
                    break;
                }
            }
        }
        $payload['consent_date'] = $date ?: CaseFields::today();
        $doctorName = trim((string) ($payload['vet_name'] ?? '')) ?: (trim((string) ($row['medic_operator'] ?? '')) ?: trim((string) ($row['responsabil_caz'] ?? '')));

        return $this->pdf('pdf.campaign.consent', [
            'r' => $row, 'p' => $payload, 'consent' => $consent, 'signedAt' => $signedAt,
            'gdpr' => CityChecks::gdprLabel($this->checks->gdprState($consent)),
            'owner' => self::signatureUri($consent->owner_signature), 'doctor' => self::signatureUri($consent->doctor_signature),
            'refusal' => self::signatureUri($consent->refusal_signature), 'doctorName' => $doctorName,
        ]);
    }

    /** Lista de reconciliere a unei luni (build_reconciliation_month_pdf). */
    public function reconciliationPdf(string $month): string
    {
        $all = $this->reports->reconciliation();
        if (! isset($all[$month])) {
            throw new RuntimeException('Luna selectată nu este disponibilă pentru reconciliere.');
        }
        $data = $all[$month];
        $counts = [];
        foreach ($data['rows'] as $r) {
            $counts[$r['_registry_photo_number']] = ($counts[$r['_registry_photo_number']] ?? 0) + 1;
        }
        $lines = [];
        foreach ($data['rows'] as $r) {
            $photo = $r['_registry_photo_number'];
            $obs = [];
            if (($counts[$photo] ?? 0) > 1) {
                $obs[] = 'DUPLICAT';
            }
            if ($r['_recon_void']) {
                $obs[] = 'CONFLICT: SCOS DIN UZ';
            }
            if ($r['_registry_internal_number'] !== '' && $r['_registry_internal_number'] !== $photo) {
                $obs[] = 'INTERN != POZA';
            }
            if ($r['_registry_report_number'] !== '' && $r['_registry_report_number'] !== $photo) {
                $obs[] = 'RAPORT != POZA';
            }
            if ($data['has_submitted_data']) {
                $obs[] = $r['_recon_submitted'] ? 'DEJA RAPORTAT' : 'DE REGULARIZAT';
            }
            $lines[] = ['photo' => $photo, 'internal' => $r['_registry_internal_number'] ?: '-', 'report' => $r['_registry_report_number'] ?: '-',
                'date' => $r['_registry_date'] ?: '-', 'patient' => trim((string) $r['nume_pacient']) ?: '-', 'owner' => trim((string) $r['nume_proprietar']) ?: '-',
                'code' => trim((string) $r['cod_caz']) ?: '-', 'status' => trim((string) $r['status_interventie']) ?: '-', 'obs' => $obs ? implode(' | ', $obs) : 'OK'];
        }

        return $this->pdf('pdf.campaign.reconciliation', ['data' => $data, 'lines' => $lines,
            'duplicates' => count(array_filter($counts, fn ($c) => $c > 1))], 'landscape');
    }

    // ---------------------------------------------------------------- ZIP

    private const MISSING_PHOTO = "Fotografia pacientului lipsește din dosar.\n";

    private const MISSING_CONSENT = "Consimțământul digital semnat lipsește sau nu este finalizat.\n";

    private const MISSING_ID = "Poza / scanul actului de identitate lipsește din dosar.\n";

    /**
     * Fisierele unui dosar: 01 fisa pacient, 02 fotografia, 03 consimtamantul semnat, 04 actul de identitate;
     * pentru documentele absente sau care nu pot fi citite se pune un fisier _LIPSA / _EROARE cu motivul.
     * Documentele se adauga direct de pe disc, iar PDF-urile generate trec prin $tmpDir, ca arhiva sa nu tina
     * sute de fisiere in memorie.
     *
     * @return array{files:array<string,array{path?:string,data?:string}>,errors:list<string>}
     */
    public function caseFiles(array $item, string $tmpDir): array
    {
        $row = $item['row'];
        $id = (int) $row['id'];
        $files = [];
        $errors = [];
        $photoUri = null;
        $write = function (string $content) use ($tmpDir): array {
            $file = $tmpDir.'/'.bin2hex(random_bytes(8));
            file_put_contents($file, $content);

            return ['path' => $file];
        };

        if ($item['photo_ok']) {
            $doc = $this->reports->document($id, SterilizationDocument::PHOTO);
            $path = $this->documentPath($doc);
            if ($path !== null) {
                $files['02_Poza_pacient.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg')] = ['path' => $path];
                $photoUri = self::jpegDataUri((string) file_get_contents($path));
            } else {
                $message = $doc?->is_missing ? 'fotografia lipsea deja din arhiva WordPress (Lipsă în sursă)' : 'fotografia nu poate fi citită';
                $errors[] = 'Poza pacient: '.$message;
                $files['02_Poza_pacient_EROARE.txt'] = ['data' => $message];
            }
        } else {
            $files['02_Poza_pacient_LIPSA.txt'] = ['data' => self::MISSING_PHOTO];
        }

        try {
            $files['01_Fisa_pacient.pdf'] = $write($this->caseSheetPdf($row, $photoUri));
        } catch (Throwable $e) {
            $errors[] = 'Fișa pacientului: '.$e->getMessage();
            $files['01_Fisa_pacient_EROARE.txt'] = ['data' => 'fișa pacientului nu poate fi generată: '.$e->getMessage()];
        }

        if ($item['consent_ok']) {
            try {
                $files['03_Consimtamant_semnat.pdf'] = $write($this->consentPdf($row, $this->checks->consentFor($row)));
            } catch (Throwable $e) {
                $errors[] = 'Consimțământ: '.$e->getMessage();
                $files['03_Consimtamant_EROARE.txt'] = ['data' => $e->getMessage()];
            }
        } else {
            $files['03_Consimtamant_LIPSA.txt'] = ['data' => self::MISSING_CONSENT];
        }

        if ($item['id_ok']) {
            $doc = $this->reports->document($id, SterilizationDocument::ID_CARD);
            $path = $this->documentPath($doc);
            if ($path !== null) {
                $files['04_Act_identitate.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg')] = ['path' => $path];
            } else {
                $message = $doc?->is_missing ? 'actul de identitate lipsea deja din arhiva WordPress (Lipsă în sursă)' : 'actul de identitate nu poate fi citit';
                $errors[] = 'Act identitate: '.$message;
                $files['04_Act_identitate_EROARE.txt'] = ['data' => $message];
            }
        } else {
            $files['04_Act_identitate_LIPSA.txt'] = ['data' => self::MISSING_ID];
        }
        ksort($files);

        return ['files' => $files, 'errors' => $errors];
    }

    private static function addEntries(ZipArchive $zip, string $dir, array $files): void
    {
        foreach ($files as $name => $entry) {
            $target = $dir.'/'.$name;
            if (isset($entry['path'])) {
                $zip->addFile($entry['path'], $target);
                // Fotografiile, scanurile si PDF-urile sunt deja comprimate.
                $zip->setCompressionName($target, ZipArchive::CM_STORE);
            } else {
                $zip->addFromString($target, $entry['data']);
            }
        }
    }

    private static function tempDir(): string
    {
        $dir = substr(self::tempPath('d'), 0, -2);
        mkdir($dir, 0700, true);

        return $dir;
    }

    public static function removeDir(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** ZIP-ul unui singur dosar (build_single_case_package), cu manifestul complet al situatiei lui. @return array{path:string,name:string} */
    public function singleCaseZip(SterilizationCase $case): array
    {
        $row = $case->row();
        $row['data_raportare_effective'] = CityReports::effectiveDate($row);
        $item = $this->reports->scan([$row])[0];
        $row = $item['row'];
        $number = trim((string) $row['numar_pacient']);
        $code = trim((string) $row['cod_caz']) ?: 'ID_'.$case->id;
        $folder = CityReports::safeName('Dosar_'.($number !== '' ? 'Pacient_'.$number.'_' : '').$code.'_'.($row['nume_pacient'] ?: 'Pacient'), 'Dosar_'.$case->id);
        $tmp = self::tempDir();
        $built = $this->caseFiles($item, $tmp);

        $m = "DOSAR CAMPANIE STERILIZARE\n";
        $m .= 'Cod caz: '.$code."\n";
        $m .= 'Numar pacient: '.(CityChecks::reportNumber($row) ?: '-')."\n";
        $m .= 'Pacient: '.($row['nume_pacient'] ?: '-')."\n";
        $m .= 'Proprietar: '.($row['nume_proprietar'] ?: '-')."\n";
        $m .= 'Data interventie: '.($row['data_interventie'] ?: '-')."\n";
        $m .= 'Status interventie: '.($row['status_interventie'] ?: '-')."\n";
        $m .= 'Dosar complet: '.($item['complete'] ? 'Da' : 'Nu')."\n";
        $m .= 'Poza pacient: '.($item['photo_ok'] ? 'Da' : 'Nu')."\n";
        $m .= 'Consimtamant semnat: '.($item['consent_ok'] ? 'Da' : 'Nu')."\n";
        $m .= 'Act identitate: '.($item['id_ok'] ? 'Da' : 'Nu')."\n";
        if ($item['missing']) {
            $m .= "Lipsuri / neconcordante:\n- ".implode("\n- ", $item['missing'])."\n";
        }
        if ($item['gdpr_warning']) {
            $m .= "Avertizare conformitate: GDPR de verificat\n";
        }
        if ($built['errors']) {
            $m .= "Erori la generare/copiere:\n- ".implode("\n- ", $built['errors'])."\n";
        }

        $path = self::tempPath('zip');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Arhiva ZIP a dosarului nu a putut fi creată.');
        }
        $zip->addEmptyDir($folder);
        $zip->addFromString($folder.'/00_Manifest_dosar.txt', $m);
        self::addEntries($zip, $folder, $built['files']);
        $ok = $zip->close();
        self::removeDir($tmp);
        if (! $ok) {
            @unlink($path);
            throw new RuntimeException('Arhiva ZIP a dosarului nu a putut fi finalizată.');
        }

        return ['path' => $path, 'name' => CityReports::safeName('Dosar_HiKeVet_'.($number !== '' ? 'Pacient_'.$number.'_' : '').$code, 'Dosar_HiKeVet').'.zip'];
    }

    /**
     * ZIP-ul pentru Primarie (handle_export_city_package): toate interventiile finalizate, chiar si cele cu lipsuri,
     * plus desfasuratorul Excel si PDF si centralizatorul. Pentru selectia fixa de 145 se adauga lista de control.
     *
     * @return array{path:string,name:string,cases:int,errors:list<array>}
     */
    public function cityZip(array $rows, ?string $month, bool $fixedSelection = false): array
    {
        if (! $rows) {
            throw new RuntimeException('Nu există intervenții finalizate pentru filtrul selectat.');
        }
        $selected = array_values(array_filter($this->reports->scan($rows), fn ($i) => $i['zip_included']));
        if (! $selected) {
            throw new RuntimeException('Nu există intervenții finalizate care să poată fi incluse în ZIP.');
        }
        if ($fixedSelection && count($selected) !== 145) {
            throw new RuntimeException('Exportul a fost oprit: selecția ZIP nu conține toate cele 145 de dosare.');
        }
        $label = CityReports::monthLabel($month);
        // Centralizatorul se verifica inainte de munca grea, ca o specie / un sex neclar sa nu se descopere dupa minute.
        $centralizator = $this->centralizatorPdf(array_map(fn ($i) => $i['row'], $selected), $label);

        $path = self::tempPath('zip');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Arhiva ZIP nu a putut fi creată.');
        }
        $errors = [];
        $tmp = self::tempDir();
        try {
            $this->fillCityZip($zip, $selected, $label, $centralizator, $fixedSelection, $tmp, $errors);
            $ok = $zip->close();
        } catch (Throwable $e) {
            @$zip->close();
            self::removeDir($tmp);
            @unlink($path);
            throw $e;
        }
        self::removeDir($tmp);
        if (! $ok) {
            @unlink($path);
            throw new RuntimeException('Arhiva ZIP nu a putut fi finalizată.');
        }
        $period = $fixedSelection ? '2026-09-selectie-145' : (CityReports::validMonth($month) ? $month : 'toata-perioada');

        return ['path' => $path, 'name' => 'Interventii_Finalizate_Primarie_'.$period.'.zip', 'cases' => count($selected), 'errors' => $errors];
    }

    private function fillCityZip(ZipArchive $zip, array $selected, string $label, string $centralizator, bool $fixedSelection, string $tmp, array &$errors): void
    {
        $zip->addEmptyDir('Dosare');
        foreach ($selected as $item) {
            $row = $item['row'];
            $dir = 'Dosare/'.$item['folder'];
            $zip->addEmptyDir($dir);
            try {
                $built = $this->caseFiles($item, $tmp);
                self::addEntries($zip, $dir, $built['files']);
                if ($built['errors']) {
                    $errors[] = ['cod' => $row['cod_caz'], 'pacient' => $row['nume_pacient'], 'eroare' => implode(' | ', $built['errors'])];
                }
            } catch (Throwable $e) {
                // O eroare neasteptata nu elimina pacientul: folderul ramane, cu motivul.
                $zip->addFromString($dir.'/99_EROARE_GENERARE.txt', $e->getMessage());
                $errors[] = ['cod' => $row['cod_caz'], 'pacient' => $row['nume_pacient'], 'eroare' => $e->getMessage()];
            }
            $m = "DOSAR CAMPANIE STERILIZARE\n";
            $m .= 'Cod caz: '.($row['cod_caz'] ?: '-')."\n";
            $m .= 'Numar pacient: '.(CityChecks::reportNumber($row) ?: '-')."\n";
            $m .= 'Pacient: '.($row['nume_pacient'] ?: '-')."\n";
            $m .= 'Proprietar: '.($row['nume_proprietar'] ?: '-')."\n";
            $m .= 'Data interventie: '.($row['data_interventie'] ?: '-')."\n";
            $m .= 'Procedura: '.(($row['procedura_efectuata'] ?: $row['procedura_programata']) ?: '-')."\n";
            $m .= 'Medic operator: '.($row['medic_operator'] ?: '-')."\n";
            $zip->addFromString($dir.'/00_Manifest_dosar.txt', $m);
        }

        $mapped = $this->reports->reportMap(array_map(fn ($i) => $i['row'], $selected));
        $zip->addFromString('00_Desfasurator_HiKeVet.xlsx', $this->xlsx($mapped, $label));
        $zip->addFromString('01_Desfasurator_HiKeVet.pdf', $this->packageReportPdf($mapped, $label));
        $zip->addFromString('02_Centralizator_General_HiKeVet.pdf', $centralizator);
        if ($fixedSelection) {
            $csv = fopen('php://temp', 'w+');
            fputcsv($csv, ['Cod dosar', 'Numar pacient', 'Data interventiei']);
            foreach ($selected as $item) {
                fputcsv($csv, [$item['row']['cod_caz'], CityChecks::reportNumber($item['row']), $item['row']['data_interventie']]);
            }
            rewind($csv);
            $zip->addFromString('03_Lista_selectie_145.csv', (string) stream_get_contents($csv));
            fclose($csv);
            if ($errors) {
                $zip->addFromString('04_Erori_documente.txt', implode("\n", array_map(fn ($e) => $e['cod'].': '.$e['eroare'], $errors)));
            }
        }
    }
}
