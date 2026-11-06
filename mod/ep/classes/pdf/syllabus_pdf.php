<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_ep\pdf;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Fiche PDF du syllabus d'un EP (syllabus_pdf.php) et livret de tous les EP du catalogue
 * (booklet.php) : une fiche par page, avec l'en-tête, le pied de page et le logo choisis par la
 * DEVE (pdf_settings.php).
 *
 * Le contenu de chaque fiche est composé en HTML puis rendu par writeHTML() : les tableaux à
 * hauteur de ligne variable (rubriques longues, intitulés de séance) y sont gérés par TCPDF, saut
 * de page et répétition de l'en-tête de tableau compris. Tout texte saisi passe par s() avant
 * d'y entrer, pour qu'un « < » dans une bibliographie ne soit pas lu comme une balise.
 *
 * Le sommaire du livret repose sur les signets (Bookmark) posés en tête de chaque fiche : TCPDF
 * le compose une fois toutes les fiches écrites, quand leurs numéros de page sont connus, puis
 * l'insère à sa place (addTOC), ce qui donne aussi le panneau de signets des lecteurs PDF.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class syllabus_pdf extends \pdf {
    /** @var string Police Unicode livrée avec Moodle : elle couvre les accents et les guillemets français. */
    const FONT = 'freesans';

    /** @var array Fond des cellules d'intitulé (RGB, en hexadécimal pour le HTML). */
    const LABEL_BACKGROUND = '#e1e8ee';

    /** @var float Hauteur du logo dans l'en-tête, en mm. */
    const LOGO_HEIGHT = 14;

    /** @var \stdClass Instance de l'activité. */
    protected $ep;

    /** @var \context_module Contexte du module (logo, filtres des intitulés). */
    protected $context;

    /** @var string|null Copie locale du logo : TCPDF ne lit qu'un chemin de fichier, pas un stored_file. */
    protected $logopath = null;

    /** @var string Texte d'en-tête choisi par la DEVE. */
    protected $headertext = '';

    /** @var string Texte de pied de page choisi par la DEVE. */
    protected $footertext = '';

    /**
     * Prépare le document : métadonnées, marges selon la présence d'un en-tête, police.
     *
     * @param \stdClass $ep Instance de l'activité (champs pdfheader et pdffooter).
     * @param \context_module $context
     */
    public function __construct(\stdClass $ep, \context_module $context) {
        parent::__construct('P', 'mm', 'A4');
        $this->ep = $ep;
        $this->context = $context;
        $this->headertext = trim((string) ($ep->pdfheader ?? ''));
        $this->footertext = trim((string) ($ep->pdffooter ?? ''));
        $this->logopath = $this->copy_logo();

        $this->SetCreator('Moodle mod_ep');
        $this->SetTitle($this->plain($ep->name));
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
        // La marge haute ne s'agrandit que s'il y a un en-tête à loger : sans logo ni texte, la
        // fiche commence en haut de la page au lieu de laisser une bande blanche.
        $hasheader = $this->logopath !== null || $this->headertext !== '';
        $this->SetMargins(15, $hasheader ? 30 : 15, 15);
        $this->setHeaderMargin(8);
        $this->setFooterMargin(15);
        $this->SetAutoPageBreak(true, 22);
        $this->SetFont(self::FONT, '', 10);
    }

    /**
     * Copie le logo de l'instance (zone pdflogo) dans un dossier temporaire de la requête, que
     * Moodle supprime de lui-même en fin de requête.
     *
     * @return string|null Chemin du fichier, ou null s'il n'y a pas de logo exploitable.
     */
    protected function copy_logo(): ?string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_ep', EP_PDFLOGO_FILEAREA, 0, 'itemid, filepath, filename', false);
        foreach ($files as $file) {
            // TCPDF ne sait lire que le PNG et le JPEG sans extension PHP supplémentaire ; un autre
            // format déposé avant une restriction du formulaire est ignoré plutôt que de faire
            // échouer toute l'édition.
            $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
            $extension = $extensions[$file->get_mimetype()] ?? null;
            if ($extension === null) {
                continue;
            }
            $path = make_request_directory() . '/logo.' . $extension;
            $file->copy_content_to($path);
            return $path;
        }
        return null;
    }

    /**
     * Intitulé saisi dans Moodle (nom d'EP, de type, d'activité), filtres appliqués, en texte brut :
     * il est ensuite échappé par s() pour le HTML, ou utilisé tel quel dans un signet.
     *
     * @param string $text
     * @return string
     */
    protected function plain($text): string {
        return trim(strip_tags(format_string((string) $text, true, ['context' => $this->context, 'escape' => false])));
    }

    /**
     * En-tête de page : logo à gauche, texte de la DEVE à droite, filet de séparation. Surcharge
     * de TCPDF::Header(), dont le nom en majuscule est imposé par la classe parente.
     *
     * @return void
     */
    public function Header() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        if ($this->logopath === null && $this->headertext === '') {
            return;
        }
        $left = $this->lMargin;
        $textleft = $left;
        if ($this->logopath !== null) {
            $this->Image($this->logopath, $left, 8, 0, self::LOGO_HEIGHT);
            $textleft = $this->getImageRBX() + 5;
        }
        if ($this->headertext !== '') {
            $this->SetFont(self::FONT, '', 8);
            $this->SetTextColor(80, 80, 80);
            $width = $this->getPageWidth() - $this->rMargin - $textleft;
            $this->MultiCell($width, 4, $this->headertext, 0, 'R', false, 1, $textleft, 9, true, 0, false, true, 15, 'T', true);
            $this->SetTextColor(0, 0, 0);
        }
        $this->SetDrawColor(180, 180, 180);
        $this->Line($left, 25, $this->getPageWidth() - $this->rMargin, 25);
    }

    /**
     * Pied de page : texte de la DEVE à gauche, « n / N » à droite. Surcharge de TCPDF::Footer().
     *
     * @return void
     */
    public function Footer() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $y = $this->getPageHeight() - 17;
        $left = $this->lMargin;
        $right = $this->getPageWidth() - $this->rMargin;
        $this->SetDrawColor(180, 180, 180);
        $this->Line($left, $y, $right, $y);
        $this->SetFont(self::FONT, '', 8);
        $this->SetTextColor(80, 80, 80);
        if ($this->footertext !== '') {
            $width = $right - $left - 25;
            $this->MultiCell($width, 4, $this->footertext, 0, 'L', false, 0, $left, $y + 1.5, true, 0, false, true, 12, 'T', true);
        }
        // Le décalage d'alias corrige l'alignement à droite des numéros de page avec une police
        // Unicode : sans lui, TCPDF mesure le texte de l'alias au lieu du numéro final.
        $this->SetXY($right - 25, $y + 1.5);
        $this->Cell(25, 4, $this->getAliasRightShift() . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * Page de titre du livret.
     *
     * @param string $title Titre principal.
     * @param string[] $lines Lignes de sous-titre (activité, cours, date), en texte brut.
     * @return void
     */
    public function add_title_page(string $title, array $lines): void {
        $this->AddPage();
        $this->SetY($this->getPageHeight() / 3);
        $this->SetFont(self::FONT, 'B', 22);
        $this->MultiCell(0, 10, $title, 0, 'C', false, 1);
        $this->Ln(6);
        $this->SetFont(self::FONT, '', 13);
        foreach ($lines as $line) {
            $this->MultiCell(0, 7, $line, 0, 'C', false, 1);
        }
        $this->SetFont(self::FONT, '', 10);
    }

    /**
     * Sommaire du livret, composé à partir des signets des fiches et inséré à la position voulue.
     * À appeler une fois toutes les fiches ajoutées : c'est alors seulement que leurs numéros de
     * page sont connus. Les numéros affichés tiennent compte des pages du sommaire lui-même.
     *
     * @param string $title Titre du sommaire.
     * @param int $position Numéro de la page où le sommaire doit commencer (2 : après la page de titre).
     * @return void
     */
    public function add_contents_page(string $title, int $position = 2): void {
        $this->addTOCPage();
        $this->SetFont(self::FONT, 'B', 16);
        $this->MultiCell(0, 0, $title, 0, 'L', false, 1);
        $this->Ln(4);
        $this->SetFont(self::FONT, '', 10);
        // Le nom du signet du sommaire n'apparaît que dans le panneau du lecteur PDF.
        $this->addTOC($position, self::FONT, '.', $title, '', [0, 0, 0]);
        $this->endTOCPage();
    }

    /**
     * Ajoute la fiche d'un EP sur une nouvelle page.
     *
     * @param \stdClass $activity EP du catalogue.
     * @param \stdClass|null $syllabus Version du syllabus à éditer, ou null s'il n'y en a pas.
     * @param \stdClass|null $type Type de l'EP, s'il est connu.
     * @param array $teachers Responsables de l'EP (comptes utilisateur).
     * @return void
     */
    public function add_activity(\stdClass $activity, ?\stdClass $syllabus, ?\stdClass $type, array $teachers): void {
        $this->AddPage();
        $name = $this->plain($activity->name);
        $code = trim((string) $activity->code);
        $this->Bookmark($code !== '' ? $code . ' - ' . $name : $name, 0, 0);

        $html = '<h1 style="font-size:16pt;color:#003d64;">' . s($name)
            . ($code !== '' ? ' <span style="color:#6c757d;font-size:12pt;">(' . s($code) . ')</span>' : '')
            . '</h1>';
        $html .= $this->characteristics_html($activity, $syllabus, $type, $teachers);
        $this->write_block($html, 0);

        if ($syllabus === null) {
            $this->write_block('<p><i>' . s(get_string('nosyllabus', 'mod_ep')) . '</i></p>', 0);
            return;
        }

        $labels = [
            'objectives' => 'syllabusobjectives',
            'outcomes' => 'syllabusoutcomes',
            'prerequisitestext' => 'syllabusprerequisites',
            'content' => 'syllabuscontent',
            'methods' => 'syllabusmethods',
            'assessment' => 'syllabusassessment',
            'resit' => 'syllabusresit',
            'bibliography' => 'syllabusbibliography',
        ];
        foreach (ep_syllabus_text_fields() as $field) {
            $text = trim((string) ($syllabus->$field ?? ''));
            // Une rubrique vide est omise : un titre sans texte laisserait croire à un oubli
            // d'impression plutôt qu'à une rubrique non renseignée.
            if ($text === '') {
                continue;
            }
            $this->write_block($this->heading_html(get_string($labels[$field] ?? $field, 'mod_ep'))
                . '<p>' . nl2br(s($text)) . '</p>', 20);
        }
        if (trim((string) $syllabus->language) !== '') {
            $this->write_block($this->heading_html(get_string('syllabuslanguage', 'mod_ep'))
                . '<p>' . s(trim($syllabus->language)) . '</p>', 20);
        }

        $sessions = ep_get_syllabus_sessions($syllabus->id);
        if ($sessions) {
            $this->write_block($this->heading_html(get_string('sessions', 'mod_ep')) . $this->sessions_html($sessions), 35);
        }
        $hours = $this->hours_html($activity, $syllabus, $sessions);
        if ($hours !== '') {
            $this->write_block($hours, 35);
        }
    }

    /**
     * Écrit un bloc de la fiche (titre de rubrique et son contenu), sur une nouvelle page s'il
     * reste moins de $minheight mm : un titre ne doit pas rester seul en bas de page, séparé du
     * texte ou du tableau qu'il annonce.
     *
     * @param string $html
     * @param float $minheight Place minimale, en mm, pour commencer le bloc sur la page courante.
     * @return void
     */
    protected function write_block(string $html, float $minheight): void {
        if ($minheight > 0 && $this->GetY() + $minheight > $this->getPageHeight() - $this->getBreakMargin()) {
            $this->AddPage();
        } else if ($minheight > 0) {
            // Chaque appel à writeHTML() repart sans la marge qui suit le paragraphe précédent :
            // l'espace entre deux rubriques est rétabli ici.
            $this->Ln(3);
        }
        $this->writeHTML($html, true, false, true, false, '');
    }

    /**
     * Titre de rubrique.
     *
     * @param string $label
     * @return string
     */
    protected function heading_html(string $label): string {
        return '<h3 style="font-size:12pt;color:#003d64;">' . s($label) . '</h3>';
    }

    /**
     * Tableau des caractéristiques de l'EP : type, ECTS, années, places, responsables, version.
     *
     * @param \stdClass $activity
     * @param \stdClass|null $syllabus
     * @param \stdClass|null $type
     * @param array $teachers
     * @return string
     */
    protected function characteristics_html(\stdClass $activity, ?\stdClass $syllabus, ?\stdClass $type, array $teachers): string {
        $rows = [];
        if ($type) {
            $rows[get_string('type', 'mod_ep')] = $this->plain($type->name);
        }
        $rows[get_string('ects', 'mod_ep')] = ep_format_ects($activity->ects);
        $rows[get_string('studyyearrange', 'mod_ep')] = ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear);
        $rows[get_string('places', 'mod_ep')] = empty($activity->capacity)
            ? get_string('unlimitedplaces', 'mod_ep') : (string) (int) $activity->capacity;
        if ($teachers) {
            $rows[get_string('activityteachers', 'mod_ep')] = implode(', ', array_map('fullname', $teachers));
        }
        if ($syllabus) {
            $version = get_string('syllabusversion', 'mod_ep', (int) $syllabus->version);
            // La date de publication situe la fiche imprimée par rapport à une version plus
            // récente ; une version de travail porte son état, pour qu'on ne la prenne pas pour
            // celle en vigueur.
            if ($syllabus->status === 'published' && !empty($syllabus->timereviewed)) {
                $version .= ' (' . userdate($syllabus->timereviewed, get_string('strftimedate', 'langconfig')) . ')';
            } else if ($syllabus->status !== 'published') {
                $version .= ' (' . get_string('syllabusstatus_' . $syllabus->status, 'mod_ep') . ')';
            }
            $rows[get_string('syllabus', 'mod_ep')] = $version;
        }

        $html = '<table border="1" cellpadding="4" cellspacing="0">';
        foreach ($rows as $label => $value) {
            $html .= '<tr nobr="true"><td width="30%" style="background-color:' . self::LABEL_BACKGROUND . ';"><b>' . s($label)
                . '</b></td><td width="70%">' . s($value) . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * Tableau des séances. Les intervenants qui ont un compte sont lus en une requête pour toute la
     * fiche, plutôt qu'une par séance.
     *
     * @param array $sessions
     * @return string
     */
    protected function sessions_html(array $sessions): string {
        global $DB;

        $teacherids = array_filter(array_unique(array_map(function ($session) {
            return (int) $session->teacherid;
        }, $sessions)));
        $users = [];
        if ($teacherids) {
            $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
            $users = $DB->get_records_list('user', 'id', $teacherids, '', $fields);
        }
        $kinds = ep_session_kind_options();
        $dateformat = get_string('strftimedate', 'langconfig');

        $head = '<tr style="background-color:' . self::LABEL_BACKGROUND . ';font-weight:bold;">'
            . '<th width="38%">' . s(get_string('sessiontitle', 'mod_ep')) . '</th>'
            . '<th width="17%">' . s(get_string('sessionkind', 'mod_ep')) . '</th>'
            . '<th width="10%" align="right">' . s(get_string('sessionduration', 'mod_ep')) . '</th>'
            . '<th width="20%">' . s(get_string('sessionteacher', 'mod_ep')) . '</th>'
            . '<th width="15%">' . s(get_string('sessiondate', 'mod_ep')) . '</th></tr>';
        // Le thead est répété par TCPDF en haut de chaque page quand le programme déborde.
        $html = '<table border="1" cellpadding="3" cellspacing="0"><thead>' . $head . '</thead>';
        foreach ($sessions as $session) {
            $teacher = '';
            if (!empty($session->teacherid) && isset($users[$session->teacherid])) {
                $teacher = fullname($users[$session->teacherid]);
            } else {
                $teacher = (string) $session->teachername;
            }
            $html .= '<tr nobr="true">'
                . '<td width="38%">' . s($session->title) . '</td>'
                . '<td width="17%">' . s($kinds[$session->kind] ?? $session->kind) . '</td>'
                . '<td width="10%" align="right">' . s(format_float((float) $session->duration, 2, true, true)) . '</td>'
                . '<td width="20%">' . s($teacher) . '</td>'
                . '<td width="15%">' . (empty($session->sessiondate) ? '' : s(userdate($session->sessiondate, $dateformat)))
                . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * Volume horaire par nature de séance, puis séances, travail personnel, charge totale et
     * heures par ECTS (repère du guide ECTS : 25 à 30 heures de travail par crédit).
     *
     * @param \stdClass $activity
     * @param \stdClass $syllabus
     * @param array $sessions
     * @return string
     */
    protected function hours_html(\stdClass $activity, \stdClass $syllabus, array $sessions): string {
        $hours = ep_syllabus_hours($sessions, (float) $syllabus->personalhours);
        if ($hours->total <= 0) {
            return '';
        }
        $format = function ($value) {
            return format_float((float) $value, 2, true, true);
        };
        $html = $this->heading_html(get_string('hourstotal', 'mod_ep'));
        if ($hours->bykind) {
            $html .= '<table border="1" cellpadding="3" cellspacing="0">';
            // Ordre des natures de la liste de référence, pas celui d'apparition dans le programme :
            // d'une fiche à l'autre, les volumes se comparent ligne à ligne.
            foreach (ep_session_kind_options() as $kind => $label) {
                if (!isset($hours->bykind[$kind])) {
                    continue;
                }
                $html .= '<tr nobr="true"><td width="40%">' . s($label) . '</td><td width="20%" align="right">'
                    . s($format($hours->bykind[$kind])) . ' h</td></tr>';
            }
            $html .= '</table>';
        }
        $lines = [get_string('hourssessions', 'mod_ep', $format($hours->sessions))];
        if ($hours->personal > 0) {
            $lines[] = get_string('hourspersonal', 'mod_ep', $format($hours->personal));
        }
        $lines[] = get_string('hoursworkload', 'mod_ep', $format($hours->total));
        if ((float) $activity->ects > 0) {
            $lines[] = get_string('hoursperects', 'mod_ep', $format($hours->total / (float) $activity->ects));
        }
        return $html . '<p>' . implode('<br />', array_map('s', $lines)) . '</p>';
    }
}
