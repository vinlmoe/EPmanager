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
 * Notation groupée d'un EP du catalogue : pour chaque étudiant dont l'inscription a été acceptée
 * et dont les ECTS restent à valider, le responsable saisit soit une note sur 20 — qui décide
 * elle-même de la validation (à partir de EP_GRADE_PASS_MARK) ou du refus —, soit coche
 * directement la case « Valider » pour retenir la totalité des ECTS sans note. Une ligne laissée
 * vide n'est pas traitée, pour pouvoir y revenir plus tard.
 *
 * Complète la validation au cas par cas (validate.php), plus adaptée à un étudiant isolé ou à une
 * décision qui a besoin d'un commentaire ; cette page-ci sert quand tout un groupe passe le même
 * EP en même temps.
 *
 * Un EP partagé, suivi par des étudiants de plusieurs promotions, se note ici tant que le
 * responsable a accès à cette instance mod_ep ; sinon, la même notation groupée est disponible
 * depuis mod_epsynthesis/activity_validate.php, qui n'exige aucun rôle dans leurs promotions.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

$activity = ep_get_catalog_activity($ep, $activityid);
if (!$activity) {
    throw new moodle_exception('errorunknownactivity', 'mod_ep');
}

$rights = ep_get_user_rights($ep, $context);
$cangrade = $rights->validatedeve || isset($rights->responsibleactivities[$activity->id]);
if (!$cangrade) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('gradebulk', 'mod_ep'));
}

$backurl = $returnurlparam !== ''
    ? new moodle_url($returnurlparam) : new moodle_url('/mod/ep/credits.php', ['id' => $cm->id]);
$pageurl = new moodle_url('/mod/ep/activity_validate.php',
    ['id' => $cm->id, 'activityid' => $activity->id, 'returnurl' => $returnurlparam]);

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('gradebulk', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if (data_submitted() && confirm_sesskey()) {
    $grades = optional_param_array('grade', [], PARAM_RAW);
    $directvalidate = optional_param_array('directvalidate', [], PARAM_INT);
    $processed = ep_process_activity_grading($activity->id, $grades, $directvalidate, $USER->id);
    redirect($backurl, get_string('gradingdone', 'mod_ep', $processed), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradebulk', 'mod_ep') . ' — ' . format_string($activity->name));
echo html_writer::link($backurl, get_string('back'));

$registrations = ep_get_activity_registrations($activity->id, ['status' => EP_STATUS_ENROLLED], 'student', 'ASC');

if (empty($registrations)) {
    echo $OUTPUT->notification(get_string('nogradableregistrations', 'mod_ep'), 'info');
} else {
    echo ep_render_activity_grading_form($pageurl, $registrations);
}

echo $OUTPUT->footer();
