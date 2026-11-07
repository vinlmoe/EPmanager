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

/**
 * Livret des syllabus d'un semestre, d'une année ou de tout le cursus : page de titre, sommaire,
 * tableau récapitulatif (crédits par période), puis la fiche publiée de chaque module, période par
 * période.
 *
 * Le sommaire repose sur les signets : un par période, un par fiche. TCPDF l'insère après la page
 * de titre une fois toutes les fiches composées (addTOC), ce qui donne aussi le panneau de
 * signets des lecteurs PDF.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class booklet_pdf extends fiche_pdf {
    /**
     * Compose le livret.
     *
     * @param string $title Titre (« Livret des syllabus »).
     * @param array $lines Sous-titre : choix édité, périmètre, date (texte brut).
     * @param array $modules Modules dans l'ordre du cursus (syllabus_list_instances(), triés), chacun avec
     *     published (version publiée ou null) et teachers (comptes).
     */
    public function __construct(string $title, array $lines, array $modules) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

        parent::__construct();
        $this->SetTitle($title);
        $this->title_page($title, $lines);
        $this->summary($modules);
        $byperiod = count(array_unique(array_map(fn($m) => syllabus_period_of($m)[0], $modules))) > 1;
        $current = null;
        foreach ($modules as $module) {
            if (!$module->published) {
                continue;
            }
            [$key, $label] = syllabus_period_of($module);
            if ($byperiod && $key !== $current) {
                // Signet de la période, sur la page où commencera sa première fiche.
                $this->Bookmark($label, 0, 0, $this->getNumPages() + 1);
                $current = $key;
            }
            $this->add_fiche(
                $module,
                $module->published,
                format_string($module->coursename, true, ['escape' => false]),
                $byperiod ? 1 : 0
            );
        }
        $this->headertext = '';
        $this->addTOCPage();
        $this->footertext = '';
        $this->SetFont(self::FONT, 'B', 16);
        $this->MultiCell(0, 0, get_string('bookletcontents', 'mod_syllabus'), 0, 'L', false, 1);
        $this->Ln(4);
        $this->SetFont(self::FONT, '', 10);
        $this->addTOC(2, self::FONT, '.', get_string('bookletcontents', 'mod_syllabus'), '', [0, 0, 0]);
        $this->endTOCPage();
    }

    /**
     * Page de titre.
     *
     * @param string $title
     * @param array $lines
     * @return void
     */
    protected function title_page(string $title, array $lines): void {
        $this->headertext = '';
        $this->AddPage();
        $this->footertext = '';
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
     * Tableau récapitulatif : modules par période, crédits, responsables, fiche publiée ou non,
     * total des crédits de chaque période et du livret.
     *
     * @param array $modules
     * @return void
     */
    protected function summary(array $modules): void {
        $title = get_string('bookletsummary', 'mod_syllabus');
        $this->headertext = '';
        $this->AddPage();
        $this->footertext = '';
        $this->Bookmark($title, 0, 0);
        $this->SetFont(self::FONT, 'B', 16);
        $this->MultiCell(0, 0, $title, 0, 'L', false, 1);
        $this->Ln(3);
        $this->SetFont(self::FONT, '', 9);
        $head = '<tr style="background-color:#e9ecef;font-weight:bold;"><td width="12%">' . s(get_string('code', 'mod_syllabus'))
            . '</td><td width="38%">' . s(get_string('module', 'mod_syllabus')) . '</td><td width="10%" align="right">'
            . s(get_string('ects', 'mod_syllabus')) . '</td><td width="25%">' . s(get_string('teachers', 'mod_syllabus'))
            . '</td><td width="15%">' . s(get_string('fiche', 'mod_syllabus')) . '</td></tr>';
        $groups = [];
        foreach ($modules as $module) {
            [$key, $label] = syllabus_period_of($module);
            $groups[$key] = $groups[$key] ?? (object) ['label' => $label, 'modules' => [], 'ects' => 0.0];
            $groups[$key]->modules[] = $module;
            $groups[$key]->ects += (float) $module->ects;
        }
        $html = '<table border="0.3" cellpadding="3">';
        $total = 0.0;
        foreach ($groups as $group) {
            $html .= '<tr><td colspan="5" style="font-weight:bold;color:#003d64;">' . s($group->label) . '</td></tr>' . $head;
            foreach ($group->modules as $module) {
                $code = trim((string) $module->code) !== '' ? $module->code : $module->courseshortname;
                $fiche = $module->published ? get_string('versionshort', 'mod_syllabus', $module->published->version)
                    : get_string('notpublishedshort', 'mod_syllabus');
                $html .= '<tr><td>' . s($code) . '</td><td>' . s(format_string($module->name, true, ['escape' => false]))
                    . '</td><td align="right">' . s(syllabus_format_number($module->ects)) . '</td><td>'
                    . s(implode(', ', array_map('fullname', $module->teachers))) . '</td><td>' . s($fiche) . '</td></tr>';
            }
            $html .= '<tr><td colspan="2" align="right"><i>' . s(get_string('ectstotal', 'mod_syllabus')) . '</i></td>'
                . '<td align="right"><b>' . s(syllabus_format_number($group->ects)) . '</b></td><td colspan="2"></td></tr>';
            $total += $group->ects;
        }
        if (count($groups) > 1) {
            $html .= '<tr><td colspan="2" align="right"><b>' . s(get_string('ectsgrandtotal', 'mod_syllabus'))
                . '</b></td><td align="right"><b>' . s(syllabus_format_number($total)) . '</b></td><td colspan="2"></td></tr>';
        }
        $this->writeHTML($html . '</table>', true, false, true, false, '');
    }

    /**
     * Signets du livret (sommaire), dans l'ordre des pages.
     *
     * @return array Signets TCPDF : t (titre), l (niveau), p (page).
     */
    public function get_outlines(): array {
        $this->sortBookmarks();
        return $this->outlines;
    }
}
