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
 * Responsables du module : ceux qui rédigent la fiche, posent les questions d'évaluation et en
 * voient les résultats. Choisis parmi les inscrits qui peuvent l'être (mod/syllabus:edit).
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init($cm, '/mod/syllabus/teachers.php');
if (!$rights->canedit && !$rights->canmanage) {
    throw new required_capability_exception($context, 'mod/syllabus:manage', 'nopermissions', '');
}

if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    $count = syllabus_set_teachers($syllabus, $context, optional_param_array('teacherids', [], PARAM_INT));
    redirect($PAGE->url, get_string('teacherssaved', 'mod_syllabus', $count), null, \core\output\notification::NOTIFY_SUCCESS);
}

$current = syllabus_get_teachers((int) $syllabus->id);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('teachers', 'mod_syllabus'));
echo html_writer::tag('p', get_string('teachers_help', 'mod_syllabus'));

$potential = syllabus_potential_teachers($context);
if (!$potential) {
    echo $OUTPUT->notification(get_string('noteachers', 'mod_syllabus'), 'info');
} else {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);
    $table = new html_table();
    $table->head = [get_string('teacherselect', 'mod_syllabus'), get_string('fullname'), get_string('email')];
    foreach ($potential as $user) {
        $table->data[] = [
            html_writer::checkbox(
                'teacherids[]',
                $user->id,
                isset($current[$user->id]),
                '',
                ['id' => 'teacher' . $user->id, 'aria-label' => fullname($user)]
            ),
            html_writer::label(fullname($user), 'teacher' . $user->id),
            s($user->email),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges'),
        'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
