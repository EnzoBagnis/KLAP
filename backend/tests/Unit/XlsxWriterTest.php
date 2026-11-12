<?php

namespace Tests\Unit;

use App\Support\XlsxWriter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/** Générateur .xlsx sans dépendance (#219) : format accepté par Excel, contenu jamais interprété. */
class XlsxWriterTest extends TestCase
{
    /** Le fichier temporaire du classeur. */
    private string $path;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'test-xlsx-');
    }

    /**
     * On remet en état ce que les tests ont modifié.
     */
    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    /** @return array<string, string> les parties du classeur */
    private function parts(XlsxWriter $writer): array
    {
        $writer->save($this->path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->path) === true);
        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $parts[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();

        return $parts;
    }

    public function test_chaque_partie_est_un_xml_bien_forme(): void
    {
        $parts = $this->parts((new XlsxWriter)
            ->sheet('Âge', [['Tranche d’âge', 'Participants'], ['15-16 ans', 22]])
            ->sheet('Notes', [['<b>&"\'', "ligne\u{0001}avec\u{FFFE}contrôles"]]));

        $this->assertSame(
            ['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'],
            array_keys($parts),
        );
        foreach ($parts as $name => $xml) {
            $this->assertNotFalse(simplexml_load_string($xml), "{$name} doit être un XML bien formé.");
        }
        $this->assertStringContainsString('&lt;b&gt;&amp;&quot;&apos;', $parts['xl/worksheets/sheet2.xml']);
        $this->assertStringContainsString('ligneaveccontrôles', $parts['xl/worksheets/sheet2.xml']);
    }

    public function test_les_nombres_sont_des_nombres_et_les_textes_jamais_des_formules(): void
    {
        $sheet = $this->parts((new XlsxWriter)->sheet('Feuille', [['=1+1', 12, 3.5, 2.0, NAN, true, null, '']]))['xl/worksheets/sheet1.xml'];

        $this->assertStringNotContainsString('<f>', $sheet);
        $this->assertStringContainsString('<c r="A1" t="inlineStr" s="1"><is><t xml:space="preserve">=1+1</t></is></c>', $sheet);
        $this->assertStringContainsString('<c r="B1" s="1"><v>12</v></c>', $sheet);
        $this->assertStringContainsString('<c r="C1" s="1"><v>3.5</v></c>', $sheet);
        $this->assertStringContainsString('<c r="D1" s="1"><v>2</v></c>', $sheet);
        $this->assertStringNotContainsString('NAN', strtoupper($sheet)); // NAN n'est pas écrit
        $this->assertStringContainsString('<t xml:space="preserve">Oui</t>', $sheet);
    }

    public function test_les_colonnes_au_dela_de_z_et_leur_largeur_dans_l_ordre(): void
    {
        $row = array_fill(0, 28, 'x');
        $rows = [[null, null, 'commence en C'], $row];
        $sheet = $this->parts((new XlsxWriter)->sheet('Large', $rows, []))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('r="AA2"', $sheet);
        $this->assertStringContainsString('r="AB2"', $sheet);
        preg_match_all('/<col min="(\d+)"/', $sheet, $cols);
        $sorted = $cols[1];
        sort($sorted, SORT_NUMERIC);
        $this->assertSame($sorted, $cols[1], 'Excel exige des <col> dans l’ordre.');
    }

    public function test_un_texte_de_la_forme_x0041_reste_tel_quel(): void
    {
        $sheet = $this->parts((new XlsxWriter)->sheet('Feuille', [['code _x0041_ ici']]))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('code _x005F_x0041_ ici', $sheet);
    }

    public function test_une_cellule_est_tronquee_a_32767_caracteres(): void
    {
        $sheet = $this->parts((new XlsxWriter)->sheet('Feuille', [[str_repeat('é', 40000)]]))['xl/worksheets/sheet1.xml'];

        preg_match('/<t xml:space="preserve">(.*)<\/t>/u', $sheet, $text);
        $this->assertSame(32767, mb_strlen($text[1]));
    }

    public function test_noms_d_onglets_nettoyes_et_tronques(): void
    {
        $workbook = $this->parts((new XlsxWriter)->sheet("'Projets : 2026/2027 [bilan] avec un nom bien trop long'", [['a']]))['xl/workbook.xml'];

        // Caractères interdits remplacés, apostrophes retirées, 31 caractères au plus.
        $this->assertStringContainsString('<sheet name="Projets   2026 2027  bilan  ave"', $workbook);
    }

    public function test_deux_onglets_de_meme_nom_sont_refuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new XlsxWriter)->sheet('Âge', [])->sheet('âge', []);
    }

    public function test_le_nom_history_est_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new XlsxWriter)->sheet('History', []);
    }

    public function test_un_classeur_vide_est_refuse(): void
    {
        $this->expectException(\RuntimeException::class);
        (new XlsxWriter)->save($this->path);
    }
}
