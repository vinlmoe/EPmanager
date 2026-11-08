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
 * Mes modules : ceux dont l'utilisateur est responsable, dans le périmètre de l'activité.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init($cm, '/mod/syllabusstaff/view.php');

\mod_syllabusstaff\event\course_module_viewed::create(['objectid' => $instance->id, 'context' => $context])->trigger();
(new completion_info($course))->set_module_viewed($cm);

[$rows] = syllabus_list_instances(['teacherid' => $USER->id, 'categoryid' => $instance->categoryid]);
// Modules qui attendent le responsable : sans fiche publiée (et sans version soumise), ou renvoyés.
$todo = [];
foreach (syllabus_get_overview(array_keys($rows)) as $syllabusid => $info) {
    $waiting = $info->working && $info->working->status === SYLLABUS_STATUS_SUBMITTED;
    if ((!$info->published && !$waiting) || ($info->working && $info->working->status === SYLLABUS_STATUS_RETURNED)) {
        $todo[] = (int) $syllabusid;
    }
}

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'view', $canmanageall);
echo $OUTPUT->heading(get_string('mymodules', 'mod_syllabusstaff'), 3);
echo html_writer::tag('p', get_string('mymodules_help', 'mod_syllabusstaff'));
if ($todo) {
    echo $OUTPUT->notification(get_string('mymodulestodo', 'mod_syllabusstaff', count($todo)), 'warning');
}
echo syllabusstaff_render_modules($rows, false, $todo);
echo $OUTPUT->footer();
