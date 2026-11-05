<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ScheduledSession;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Service de génération des pré-bilans d'ateliers pour KLAP Kelemenis & cie.
 * Exigences strictes de Mariette Travard :
 * 1. Export Word (.docx) et Excel/CSV (.csv).
 * 2. PROHIBITION STRICTE DES EXPORTS PDF (les documents doivent rester modifiables par l'administration).
 * 3. Règle d'effectif MAX dédoublonné respectée via la vue SQL v_project_participants.
 */
class ReportExportService
{
    /**
     * Génère un document Microsoft Word (.docx) nativement sans dépendances externes lourdes.
     */
    public function generateWordDocument(Project $project): string
    {
        $project->load(['subProjects', 'funders', 'leadArtist', 'scheduledSessions.location', 'scheduledSessions.logbookEntry']);

        $totalMaxParticipants = (int) (DB::table('v_project_participants')
            ->where('project_id', $project->id)
            ->value('total_participants_max') ?? 0);

        $subProjectMetrics = DB::table('v_sub_project_participants')
            ->where('project_id', $project->id)
            ->get();

        $fundersList = $project->funders->pluck('name')->implode(', ');
        if (empty($fundersList)) {
            $fundersList = 'Aucun financeur spécifique rattaché';
        }

        $leadArtist = $project->leadArtist->first();

        // Construction du contenu WordprocessingML
        $paragraphs = [];

        // Titre principal
        $paragraphs[] = $this->buildParagraph("KLAP Maison pour la danse / Kelemenis & cie", true, 32, "8B1E41");
        $paragraphs[] = $this->buildParagraph("PRÉ-BILAN D'ACTIVITÉ : " . mb_strtoupper($project->name, 'UTF-8'), true, 26, "222222");
        $paragraphs[] = $this->buildParagraph("Généré le " . date('d/m/Y') . " pour l'administration et les financeurs", false, 18, "666666");
        $paragraphs[] = $this->buildParagraph("", false, 12);

        // Section Informations Générales
        $paragraphs[] = $this->buildParagraph("1. SYNTHÈSE DU PROJET", true, 22, "8B1E41");
        $projectCode = 'KLAP-' . str_pad((string) $project->id, 3, '0', STR_PAD_LEFT);
        $paragraphs[] = $this->buildParagraph("• Référence projet : " . $projectCode);
        $paragraphs[] = $this->buildParagraph("• Période : Du " . ($project->start_date?->format('d/m/Y') ?? 'N/A') . " au " . ($project->end_date?->format('d/m/Y') ?? 'N/A'));
        $paragraphs[] = $this->buildParagraph("• Artiste intervenant principal : " . ($leadArtist?->name ?? 'Non assigné'));
        $paragraphs[] = $this->buildParagraph("• Financeurs partenaires : " . $fundersList);
        $paragraphs[] = $this->buildParagraph("• Nombre total de séances planifiées : " . $project->scheduledSessions->count());
        $paragraphs[] = $this->buildParagraph("• Effectif total réel (Règle MAX dédoublonnée) : " . $totalMaxParticipants . " participants uniques");
        $paragraphs[] = $this->buildParagraph("", false, 12);

        // Section Détail par groupe / classe
        $paragraphs[] = $this->buildParagraph("2. RÉPARTITION PAR GROUPE / ÉTABLISSEMENT", true, 22, "8B1E41");
        foreach ($project->subProjects as $sp) {
            $metric = $subProjectMetrics->firstWhere('sub_project_id', $sp->id);
            $maxAtt = (int) ($metric->unique_participants_max ?? 0);
            $sessionsCount = (int) ($metric->sessions_count ?? 0);

            $partner = $sp->organization ? " (Établissement : {$sp->organization})" : "";
            $audience = $sp->age_range ? " - Public : {$sp->age_range}" : "";
            $paragraphs[] = $this->buildParagraph("• {$sp->name}{$partner}{$audience} : {$maxAtt} participants réels, {$sessionsCount} séances renseignées.");
        }
        $paragraphs[] = $this->buildParagraph("", false, 12);

        // Section Bilans et Observations qualitatives
        $paragraphs[] = $this->buildParagraph("3. OBSERVATIONS QUALITATIVES & TEMPS FORTS", true, 22, "8B1E41");
        $entries = $project->scheduledSessions->pluck('logbookEntry')->filter()->values();

        if ($entries->isEmpty()) {
            $paragraphs[] = $this->buildParagraph("Aucune fiche de carnet de bord n'a encore été complétée pour ce projet.");
        } else {
            foreach ($entries as $idx => $entry) {
                $sessionDate = $entry->scheduledSession?->session_date?->format('d/m/Y') ?? 'Date inconnue';
                $paragraphs[] = $this->buildParagraph("Séance du {$sessionDate} (Effectif : {$entry->attendees_count} participants) :", true, 18, "333333");

                if ($entry->what_works) {
                    $paragraphs[] = $this->buildParagraph("  - Ce qui a fonctionné : " . $entry->what_works);
                }
                if ($entry->learnings) {
                    $paragraphs[] = $this->buildParagraph("  - Acquis constatés : " . $entry->learnings);
                }
                if ($entry->next_steps) {
                    $paragraphs[] = $this->buildParagraph("  - Perspectives / À reprendre : " . $entry->next_steps);
                }
                if ($entry->additional_notes) {
                    $paragraphs[] = $this->buildParagraph("  - Notes : " . $entry->additional_notes);
                }
            }
        }

        // Assemblage XML du document Word
        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>'
            . implode('', $paragraphs)
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
            . '</w:body>'
            . '</w:document>';

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>';

        $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';

        // Création de l'archive ZIP (.docx) en mémoire
        $tempFile = tempnam(sys_get_temp_dir(), 'klap_docx_');
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('_rels/.rels', $relsXml);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    /**
     * Génère un fichier CSV UTF-8 avec séparateurs point-virgule et BOM pour ouverture directe dans Excel.
     */
    public function generateCsvReport(Project $project): string
    {
        $project->load(['subProjects', 'funders', 'leadArtist', 'scheduledSessions.location', 'scheduledSessions.logbookEntry']);

        $totalMaxParticipants = (int) (DB::table('v_project_participants')
            ->where('project_id', $project->id)
            ->value('total_participants_max') ?? 0);

        $output = fopen('php://temp', 'r+');

        // BOM UTF-8 pour forcer Excel à interpréter les accents français sans boîte de dialogue
        fwrite($output, "\xEF\xBB\xBF");

        // En-têtes du projet
        $this->writeCsvRow($output, ['KLAP Maison pour la danse / Kelemenis & cie - Pré-bilan d’activité']);
        $projectCode = 'KLAP-' . str_pad((string) $project->id, 3, '0', STR_PAD_LEFT);
        $this->writeCsvRow($output, ['Projet', $project->name, 'Réf', $projectCode]);
        $this->writeCsvRow($output, ['Intervenant principal', $project->leadArtist->first()?->name ?? 'N/A']);
        $this->writeCsvRow($output, ['Effectif total MAX (dédoublonné)', $totalMaxParticipants]);
        $this->writeCsvRow($output, []);

        // Tableau des séances
        $this->writeCsvRow($output, [
            'Date séance',
            'Début',
            'Fin',
            'Lieu / Studio',
            'Structure partenaire',
            'Type séance',
            'Effectif séance',
            'Hommes',
            'Femmes',
            'Statut fiche',
            'Ce qui a fonctionné',
            'Notes complémentaires',
        ]);

        foreach ($project->scheduledSessions as $session) {
            $entry = $session->logbookEntry;

            $this->writeCsvRow($output, [
                $session->session_date?->format('d/m/Y') ?? '',
                $session->start_time ?? '',
                $session->end_time ?? '',
                $session->location?->name ?? '',
                $session->location?->structure ?? '',
                $session->is_transversal ? 'Transversale tous groupes' : 'Atelier groupe',
                $entry?->attendees_count ?? 0,
                $entry?->attendees_male ?? 0,
                $entry?->attendees_female ?? 0,
                $entry ? ($entry->isLocked() ? 'Verrouillée' : 'Validée') : 'Non remplie',
                $entry?->what_works ?? '',
                $entry?->additional_notes ?? '',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Écrit une ligne CSV en neutralisant les risques d'injection de formules (OWASP CWE-1236).
     */
    private function writeCsvRow($handle, array $row): void
    {
        $sanitized = array_map([$this, 'sanitizeCsvCell'], $row);
        fputcsv($handle, $sanitized, ';');
    }

    /**
     * Protège une valeur contre l'injection de formules dans les tableurs (OWASP CWE-1236).
     * Si la cellule débute par un caractère interprétable par Excel (=, +, -, @),
     * elle est préfixée d'une apostrophe pour forcer l'affichage en texte pur.
     */
    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^[=\+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Aide pour créer un paragraphe WordprocessingML avec mise en forme optionnelle.
     */
    private function buildParagraph(string $text, bool $bold = false, int $sizeHalfPoints = 22, string $colorHex = "000000"): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1, 'UTF-8');

        $rPr = '<w:rPr>';
        if ($bold) {
            $rPr .= '<w:b/>';
        }
        if ($sizeHalfPoints) {
            $rPr .= "<w:sz w:val=\"{$sizeHalfPoints}\"/>";
        }
        if ($colorHex !== "000000") {
            $rPr .= "<w:color w:val=\"{$colorHex}\"/>";
        }
        $rPr .= '<w:rFonts w:ascii="Arial" w:hAnsi="Arial"/></w:rPr>';

        return "<w:p>{$rPr}<w:r>{$rPr}<w:t xml:space=\"preserve\">{$escaped}</w:t></w:r></w:p>";
    }
}
