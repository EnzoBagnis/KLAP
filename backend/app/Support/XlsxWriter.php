<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Classeur Excel (.xlsx) minimal, sans dépendance (#219) : un .xlsx est une archive zip de
 * fichiers XML (format Office Open XML). ext-zip est déjà requis par phpword.
 *
 * - Les nombres sont de vraies cellules numériques : Excel peut trier, filtrer et calculer.
 * - Les textes sont des chaînes « en ligne », jamais des formules : un texte saisi qui commence
 *   par « = » reste un texte (pas d'injection de formule, à la différence d'un CSV).
 * - Les lignes d'en-tête indiquées sont en gras ; la largeur des colonnes suit leur contenu.
 */
final class XlsxWriter
{
    /** @var list<array{name: string, rows: list<list<string|int|float|bool|null>>, bold: list<int>, notes: list<int>}> */
    private array $sheets = [];

    /**
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<int>  $boldRows  indices (à partir de 0) des lignes en gras
     * @param  list<int>  $noteRows  lignes de texte long, ignorées pour la largeur des colonnes
     */
    public function sheet(string $name, array $rows, array $boldRows = [0], array $noteRows = []): self
    {
        // Nom d'onglet (règles d'Excel) : 31 caractères au plus, sans : \ / ? * [ ], sans
        // apostrophe au début ni à la fin, unique sans tenir compte de la casse, pas « History ».
        $name = trim(preg_replace('/[:\\\\\/?*\[\]]/', ' ', self::clean($name)), " '");
        $name = rtrim(mb_substr($name, 0, 31), " '");
        if ($name === '' || mb_strtolower($name) === 'history') {
            throw new InvalidArgumentException("Nom d'onglet refusé par Excel : « {$name} ».");
        }
        foreach ($this->sheets as $sheet) {
            if (mb_strtolower($sheet['name']) === mb_strtolower($name)) {
                throw new InvalidArgumentException("Deux onglets ne peuvent pas s'appeler « {$name} ».");
            }
        }
        $this->sheets[] = ['name' => $name, 'rows' => array_values($rows), 'bold' => $boldRows, 'notes' => $noteRows];

        return $this;
    }

    public function save(string $path): void
    {
        if ($this->sheets === []) {
            throw new RuntimeException('Un classeur doit contenir au moins une feuille.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible d'écrire {$path}.");
        }
        $count = count($this->sheets);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes($count));
        $zip->addFromString('_rels/.rels', self::XML.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels($count));
        $zip->addFromString('xl/styles.xml', self::XML.'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>');
        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($sheet));
        }
        // Un échec d'écriture du zip ne lève qu'un avertissement : on ne livre pas un fichier vide.
        if ($zip->close() !== true) {
            throw new RuntimeException("Impossible d'écrire {$path}.");
        }
    }

    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

    private function contentTypes(int $count): string
    {
        $sheets = '';
        for ($i = 1; $i <= $count; $i++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return self::XML.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets.'</Types>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.self::escape($sheet['name']).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return self::XML.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRels(int $count): string
    {
        $rels = '';
        for ($i = 1; $i <= $count; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $styles = $count + 1;

        return self::XML.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels
            .'<Relationship Id="rId'.$styles.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /** @param array{rows: list<list<string|int|float|null>>, bold: list<int>, notes: list<int>} $sheet */
    private function worksheet(array $sheet): string
    {
        $widths = [];
        $rows = '';
        foreach ($sheet['rows'] as $r => $row) {
            $style = in_array($r, $sheet['bold'], true) ? ' s="1"' : '';
            $cells = '';
            foreach (array_values($row) as $c => $value) {
                if ($value === null || $value === '' || (is_float($value) && ! is_finite($value))) {
                    continue;
                }
                $ref = self::column($c).($r + 1);
                if (is_bool($value)) {
                    $value = $value ? 'Oui' : 'Non';
                }
                if (is_int($value) || is_float($value)) {
                    $number = is_float($value) ? rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') : (string) $value;
                    $cells .= '<c r="'.$ref.'"'.$style.'><v>'.$number.'</v></c>';
                    $length = strlen($number);
                } else {
                    // Une cellule contient au plus 32 767 caractères. « _x0041_ » serait décodé
                    // par Excel (« A ») : le tiret bas est lui-même codé pour rester tel quel.
                    $text = mb_substr(self::clean((string) $value), 0, 32767);
                    $text = preg_replace('/_(x[0-9A-Fa-f]{4}_)/', '_x005F_$1', $text);
                    $cells .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::escape($text).'</t></is></c>';
                    $length = mb_strlen($text);
                }
                if (! in_array($r, $sheet['notes'], true)) {
                    $widths[$c] = max($widths[$c] ?? 8, min(60, $length + 2));
                }
            }
            $rows .= '<row r="'.($r + 1).'">'.$cells.'</row>';
        }
        // Excel exige des <col> dans l'ordre des colonnes.
        ksort($widths);
        $cols = '';
        foreach ($widths as $c => $width) {
            $cols .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        return self::XML.'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .($cols === '' ? '' : '<cols>'.$cols.'</cols>').'<sheetData>'.$rows.'</sheetData></worksheet>';
    }

    /** Colonne 0 → A, 25 → Z, 26 → AA. */
    private static function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Ne garde que les caractères permis en XML 1.0 (un texte collé peut contenir des caractères
     * de contrôle) ; un texte en UTF-8 invalide est d'abord réparé, au lieu d'être perdu.
     */
    private static function clean(string $text): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_scrub($text, 'UTF-8')) ?? '';
    }
}
