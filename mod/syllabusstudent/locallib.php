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

/**
 * Fonctions de mod_syllabusstudent.
 *
 * @package   mod_syllabusstudent
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

/**
 * Modules d'un étudiant : les syllabus des cours où il est inscrit (inscription active), dans
 * le périmètre, qu'il peut voir ; avec, pour chacun, où il en est de l'évaluation.
 *
 * @param int $userid
 * @param int $categoryid 0 = tout le site.
 * @return array Lignes de syllabus_list_instances() avec evalproblem (null = à évaluer).
 */
function syllabusstudent_get_modules(int $userid, int $categoryid): array {
    $courses = enrol_get_users_courses($userid, true, 'id');
    [$rows] = syllabus_list_instances(['courseids' => array_keys($courses), 'categoryid' => $categoryid]);
    $modules = [];
    foreach ($rows as $row) {
        $cminfo = get_fast_modinfo($row->course, $userid)->get_cm($row->cmid);
        if (!$cminfo->uservisible) {
            continue;
        }
        $row->evalproblem = syllabus_evaluation_problem($row, context_module::instance($row->cmid), $userid);
        $modules[$row->id] = $row;
    }
    return $modules;
}
