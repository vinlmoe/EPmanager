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
 * Liste des syllabus d'un cours.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);
$PAGE->set_url('/mod/syllabus/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_syllabus'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_syllabus'));
$table = new html_table();
$table->head = [get_string('name'), get_string('code', 'mod_syllabus'), get_string('evaluation', 'mod_syllabus')];
foreach (get_all_instances_in_course('syllabus', $course) as $syllabus) {
    $link = html_writer::link(
        new moodle_url('/mod/syllabus/view.php', ['id' => $syllabus->coursemodule]),
        format_string($syllabus->name),
        $syllabus->visible ? [] : ['class' => 'dimmed']
    );
    $table->data[] = [$link, s($syllabus->code), syllabus_eval_badge($syllabus)];
}
echo $table->data ? html_writer::table($table) : $OUTPUT->notification(get_string('noinstances', 'mod_syllabus'), 'info');
echo $OUTPUT->footer();
