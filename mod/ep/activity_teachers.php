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
 * Affectation des enseignants responsables d'un EP du catalogue : ce sont eux, et eux seuls, qui
 * valident les inscriptions à cet EP (voir ep_can_validate_credit()).
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);

$baseurl = new moodle_url('/mod/ep/activity_teachers.php', ['id' => $cm->id, 'activityid' => $activity->id]);
$returnurl = new moodle_url('/mod/ep/activities.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('activityteachers', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$potential = ep_get_potential_teachers($context);

if (optional_param('save', 0, PARAM_INT) && confirm_sesskey()) {
    // Seuls les enseignants effectivement proposés sont retenus : une valeur ajoutée à la main
    // dans la requête ne doit pas rendre responsable d'un EP quelqu'un qui n'a même pas le droit
    // de valider dans cette activité.
    $selected = array_intersect(optional_param_array('teacherid', [], PARAM_INT), array_keys($potential));
    ep_set_activity_teachers($activity->id, $selected);
    redirect($returnurl, get_string('activityteacherssaved', 'mod_ep'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$assigned = ep_get_activity_teachers($activity->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('activityteachersfor', 'mod_ep', format_string($activity->name)));
echo html_writer::link($returnurl, get_string('back'));
echo html_writer::tag('p', get_string('activityteachers_help', 'mod_ep'));

if (empty($potential)) {
    echo $OUTPUT->notification(get_string('nopotentialteachers', 'mod_ep'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

$table = new html_table();
$table->head = [get_string('responsible', 'mod_ep'), get_string('teacher', 'mod_ep'), get_string('email')];
foreach ($potential as $teacher) {
    $table->data[] = [
        html_writer::checkbox('teacherid[]', $teacher->id, isset($assigned[$teacher->id]), '',
            ['id' => 'teacherid_' . $teacher->id]),
        fullname($teacher),
        s($teacher->email),
    ];
}
echo html_writer::table($table);

echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
