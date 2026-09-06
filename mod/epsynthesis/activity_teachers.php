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
 * Affectation des enseignants responsables d'un EP partagé. Ce sont eux qui acceptent les
 * inscriptions à cet EP, puis qui en valident les ECTS à la fin — pour tous les étudiants qui s'y
 * sont inscrits, quelle que soit leur promotion.
 *
 * Les responsables se choisissent parmi les enseignants de ce cours de suivi : un EP partagé
 * s'adressant à plusieurs promotions, son responsable n'a pas de raison d'être enseignant dans
 * l'une d'elles en particulier.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$epsynthesis = $DB->get_record('epsynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:manageactivities', $context);

$activity = ep_get_shared_activity($cm->id, $activityid);
if (!$activity) {
    throw new moodle_exception('errorunknownactivity', 'mod_ep',
        (new moodle_url('/mod/epsynthesis/activities.php', ['id' => $cm->id]))->out(false));
}

$baseurl = new moodle_url('/mod/epsynthesis/activity_teachers.php',
    ['id' => $cm->id, 'activityid' => $activity->id]);
$returnurl = new moodle_url('/mod/epsynthesis/activities.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($epsynthesis->name) . ' - ' . get_string('activityteachers', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$potential = epsynthesis_get_potential_teachers($context);

if (optional_param('save', 0, PARAM_INT) && confirm_sesskey()) {
    // Seuls les enseignants effectivement proposés sont retenus : une valeur ajoutée à la main
    // dans la requête ne doit pas rendre responsable d'un EP quelqu'un qui n'a même pas accès à
    // cette synthèse.
    $selected = array_intersect(optional_param_array('teacherid', [], PARAM_INT), array_keys($potential));
    ep_set_activity_teachers($activity->id, $selected);
    redirect($returnurl, get_string('activityteacherssaved', 'mod_ep'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$assigned = ep_get_activity_teachers($activity->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('activityteachersfor', 'mod_ep', format_string($activity->name)));
echo html_writer::link($returnurl, get_string('back'));
echo html_writer::tag('p', get_string('sharedactivityteachers_help', 'mod_epsynthesis'));

if (empty($potential)) {
    echo $OUTPUT->notification(get_string('nopotentialteachers', 'mod_epsynthesis'), 'warning');
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
