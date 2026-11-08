<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Pas Behat de mod_syllabusstaff : pages d'une activité « Suivi des modules ».
 *
 * @package    mod_syllabusstaff
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_syllabusstaff extends behat_base {
    /**
     * Pages d'une activité, désignée par son nom ou son idnumber.
     *
     * @param string $type My modules, All modules, To review, Results, Common referential, Frameworks,
     *     Matrix, Progression, Booklet.
     * @param string $identifier
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        $pages = ['my modules' => 'view.php', 'all modules' => 'all.php', 'to review' => 'review.php',
            'results' => 'results.php', 'common referential' => 'referential.php', 'frameworks' => 'frameworks.php',
            'matrix' => 'competencies.php', 'progression' => 'progression.php', 'booklet' => 'booklet.php'];
        $key = strtolower($type);
        if (!isset($pages[$key])) {
            throw new Exception('Unrecognised mod_syllabusstaff page type "' . $type . '"');
        }
        $cm = $this->get_cm_by_activity_name('syllabusstaff', $identifier);
        return new moodle_url('/mod/syllabusstaff/' . $pages[$key], ['id' => $cm->id]);
    }
}
