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
     * Compose le document.
     *
     * @param \stdClass $syllabus
     * @param \stdClass|null $version
     * @param string $coursename
     */
    public function __construct(\stdClass $syllabus, ?\stdClass $version, string $coursename) {
        parent::__construct('P', 'mm', 'A4');
        $this->headertext = $coursename;
        $this->footertext = $version
            ? get_string('pdfversion', 'mod_syllabus', (object) ['version' => $version->version,
                'date' => userdate($version->timereviewed ?: $version->timemodified, get_string('strftimedate', 'langconfig'))])
            : '';
        $this->SetCreator('Moodle mod_syllabus');
        $this->SetTitle(format_string($syllabus->name));
        $this->tcpdflink = false;
        $this->SetMargins(15, 22, 15);
        $this->setHeaderMargin(8);
        $this->setFooterMargin(12);
        $this->SetAutoPageBreak(true, 20);
        $this->SetFont(self::FONT, '', 10);
        $this->AddPage();
        $this->SetFont(self::FONT, 'B', 15);
        $this->Write(0, format_string($syllabus->name, true, ['escape' => false]), '', false, 'L', true);
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
