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

namespace mod_syllabus\pdf;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/pdflib.php');

use mod_syllabus\output\fiche;

/**
 * Fiche PDF d'un module : la fiche HTML (voir mod_syllabus\output\fiche) composée par TCPDF,
 * avec le nom du cours en en-tête et la version en pied de page.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fiche_pdf extends \pdf {
    /** @var string Police Unicode livrée avec Moodle. */
    const FONT = 'freesans';

    /** @var string Texte de l'en-tête. */
    protected $headertext = '';

    /** @var string Texte du pied de page. */
    protected $footertext = '';

    /**
     * Prépare le document et, si un module est donné, y compose sa fiche.
     *
     * @param \stdClass|null $syllabus
     * @param \stdClass|null $version
     * @param string $coursename
     */
    public function __construct(?\stdClass $syllabus = null, ?\stdClass $version = null, string $coursename = '') {
        parent::__construct('P', 'mm', 'A4');
        $this->SetCreator('Moodle mod_syllabus');
        $this->tcpdflink = false;
        $this->SetMargins(15, 22, 15);
        $this->setHeaderMargin(8);
        $this->setFooterMargin(12);
        $this->SetAutoPageBreak(true, 20);
        $this->SetFont(self::FONT, '', 10);
        if ($syllabus) {
            $this->SetTitle(format_string($syllabus->name));
            $this->add_fiche($syllabus, $version, $coursename);
        }
    }

    /**
     * Ajoute la fiche d'un module, sur une nouvelle page : le nom du cours en en-tête et sa
     * version en pied de page, pour toutes les pages de la fiche.
     *
     * @param \stdClass $syllabus
     * @param \stdClass|null $version
     * @param string $coursename
     * @param int $bookmarklevel Niveau du signet de la fiche (sommaire du livret), -1 pour aucun.
     * @return void
     */
    public function add_fiche(\stdClass $syllabus, ?\stdClass $version, string $coursename, int $bookmarklevel = -1): void {
        // L'en-tête est dessiné à l'ouverture de la page, le pied de page à sa fermeture : le pied
        // de la page précédente doit garder son texte jusqu'à AddPage().
        $this->headertext = $coursename;
        $this->AddPage();
        $this->footertext = $version
            ? get_string('pdfversion', 'mod_syllabus', (object) ['version' => $version->version,
                'date' => userdate($version->timereviewed ?: $version->timemodified, get_string('strftimedate', 'langconfig'))])
            : '';
        $name = format_string($syllabus->name, true, ['escape' => false]);
        if ($bookmarklevel >= 0) {
            $code = trim((string) $syllabus->code);
            $this->Bookmark($code !== '' ? $code . ' - ' . $name : $name, $bookmarklevel, 0);
        }
        $this->SetFont(self::FONT, 'B', 15);
        $this->Write(0, $name, '', false, 'L', true);
        $this->Ln(3);
        $this->SetFont(self::FONT, '', 10);
        $this->writeHTML(fiche::render($syllabus, $version), true, false, true, false, '');
    }

    /**
     * En-tête : nom du cours.
     */
    public function Header() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $this->SetFont(self::FONT, '', 8);
        $this->SetTextColor(110, 110, 110);
        $this->Cell(0, 6, $this->headertext, 'B', 0, 'L');
    }

    /**
     * Pied de page : version et numéro de page.
     */
    public function Footer() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $this->SetY(-14);
        $this->SetFont(self::FONT, '', 8);
        $this->SetTextColor(110, 110, 110);
        $this->Cell(0, 6, $this->footertext, 'T', 0, 'L');
        $this->Cell(0, 6, $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}
