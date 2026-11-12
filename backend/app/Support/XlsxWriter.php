<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Classeur Excel (.xlsx) minimal, qu'on écrit sans dépendance (#219) : un .xlsx est une
 * archive zip de fichiers XML (format Office Open XML). ext-zip est déjà requis par phpword.
 *
 * - On écrit les nombres en vraies cellules numériques : Excel peut trier, filtrer et calculer.
 * - On écrit les textes en chaînes « en ligne », jamais en formules : un texte saisi qui commence
 *   par « = » reste un texte (pas d'injection de formule, à la différence d'un CSV).
 * - On met en gras les lignes d'en-tête indiquées ; la largeur des colonnes suit leur contenu.
 */
final class XlsxWriter
{
    /**
     * Les feuilles du classeur, dans l'ordre des onglets.
     *
     * @var list<array{name: string, rows: list<array<int|string, string|int|float|bool|null>>, bold: list<int>, notes: list<int>}>
     */
    private array $sheets = [];

    /**
     * On ajoute une feuille au classeur.
     *
     * @param  array<int|string, array<int|string, string|int|float|bool|null>>  $rows  les lignes, cellule par cellule
     * @param  list<int>  $boldRows  les indices (à partir de 0) des lignes en gras
     * @param  list<int>  $noteRows  les lignes de texte long, qu'on ignore pour la largeur des colonnes
     *
     * @throws InvalidArgumentException si Excel refuserait le nom de l'onglet
     */
    public function sheet(string $name, array $rows, array $boldRows = [0], array $noteRows = []): self
    {
        // On applique les règles d'Excel pour un nom d'onglet : 31 caractères au plus, sans
        // : \ / ? * [ ], sans apostrophe au début ni à la fin, unique sans tenir compte de la
        // casse, et pas « History ».
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

    /**
     * On écrit le classeur à l'emplacement donné.
     *
     * @throws RuntimeException si le classeur est vide ou si le fichier ne s'écrit pas
     */
    public function save(string $path): void
    {
        if ($this->sheets === []) {
            throw new RuntimeException('Un classeur doit contenir au moins une feuille.');
        }
        // On vérifie le dossier nous-mêmes : sinon l'archive ne se plaint qu'à la fermeture,
        // par un simple avertissement.
        if (! is_dir(dirname($path)) || ! is_writable(dirname($path))) {
            throw new RuntimeException("Impossible d'écrire {$path}.");
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
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');
        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($sheet));
        }
        // Un échec d'écriture du zip ne lève qu'un avertissement : on le transforme en
        // exception, pour ne pas livrer un fichier vide.
        if ($zip->close() !== true) {
            throw new RuntimeException("Impossible d'écrire {$path}.");
        }
    }

    /** L'en-tête de chaque fichier XML de l'archive. */
    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

    /**
     * Le sommaire de l'archive : le type de chaque fichier qu'elle contient.
     *
     * @param  int  $count  le nombre de feuilles
     */
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

    /**
     * La liste des onglets du classeur.
     */
    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.self::escape($sheet['name']).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return self::XML.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>';
    }

    /**
     * Les liens du classeur vers ses feuilles et sa feuille de styles.
     *
     * @param  int  $count  le nombre de feuilles
     */
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

    /**
     * Le XML d'une feuille : ses cellules et la largeur de ses colonnes.
     *
     * @param  array{name: string, rows: list<array<int|string, string|int|float|bool|null>>, bold: list<int>, notes: list<int>}  $sheet
     */
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
                    // Une cellule contient au plus 32 767 caractères. Excel décoderait
                    // « _x0041_ » en « A » : on code le tiret bas lui-même pour qu'il reste tel quel.
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

    /**
     * Le nom d'une colonne : 0 → A, 25 → Z, 26 → AA.
     */
    private static function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }

    /**
     * On échappe un texte pour l'écrire dans du XML.
     */
    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * On ne garde que les caractères permis en XML 1.0 (un texte collé peut contenir des
     * caractères de contrôle). Un texte en UTF-8 invalide, on le répare d'abord, au lieu de le perdre.
     */
    private static function clean(string $text): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_scrub($text, 'UTF-8')) ?? '';
    }
}
