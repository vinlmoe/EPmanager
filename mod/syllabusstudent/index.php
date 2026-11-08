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
 * Activités « Mes modules » d'un cours.
 *
 * @package   mod_syllabusstudent
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);
$PAGE->set_url('/mod/syllabusstudent/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_syllabusstudent'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_syllabusstudent'));
$items = [];
foreach (get_all_instances_in_course('syllabusstudent', $course) as $instance) {
    $items[] = html_writer::link(
        new moodle_url('/mod/syllabusstudent/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name)
    );
}
echo $items ? html_writer::alist($items) : $OUTPUT->notification(get_string('nothingtodisplay'), 'info');
echo $OUTPUT->footer();
