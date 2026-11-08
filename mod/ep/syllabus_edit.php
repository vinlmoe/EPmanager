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
 * Proposition d'un nouvel EP par un enseignant : ses caractéristiques (intitulé, type, ECTS,
 * places, années), puis la rédaction de sa fiche dans son activité « Syllabus du module »
 * (mod_syllabus), où elle est soumise à la DEVE. Pour un EP existant (activityid), cette adresse
 * mène à la rédaction de sa fiche, créée au besoin ; un EP encore en brouillon ou proposé garde
 * ici ses caractéristiques modifiables.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\form\syllabus_form;

$id = required_param('id', PARAM_INT);
$activityid = optional_param('activityid', 0, PARAM_INT);
$details = optional_param('details', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$activity = null;
if ($activityid) {
    // L'EP est relu dans cette instance : un identifiant d'une autre activité ne doit pas ouvrir
    // sa fiche à qui rédige celles d'ici.
    $activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
    if (!ep_can_edit_syllabus($activity, $context)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('editsyllabus', 'mod_ep'));
    }
    $isnew = in_array($activity->state, [EP_ACTIVITY_DRAFT, EP_ACTIVITY_PROPOSED], true);
    if (!$details || !$isnew) {
        $syllabus = ep_ensure_activity_syllabus($ep, $activity);
        redirect(new moodle_url('/mod/syllabus/edit.php', ['id' => $syllabus->cmid]));
    }
} else if (!has_capability('mod/ep:propose', $context) && !has_capability('mod/ep:manage', $context)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('proposenew', 'mod_ep'));
}

$baseurl = new moodle_url('/mod/ep/syllabus_edit.php', ['id' => $cm->id, 'activityid' => $activityid, 'details' => $details]);
$proposalsurl = new moodle_url('/mod/ep/proposals.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('proposenew', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$types = ep_get_catalogable_types($ep->id);
if (empty($types)) {
    redirect($proposalsurl, get_string('errornotypes', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
}

$mform = new syllabus_form($baseurl, ['types' => $types]);
$formdata = ['id' => $cm->id, 'activityid' => $activityid, 'typeid' => (int) reset($types)->id];
if ($activity) {
    $formdata = array_merge($formdata, [
        'name' => $activity->name,
        'typeid' => $activity->typeid,
        'ects' => (float) $activity->ects,
        'capacity' => (int) $activity->capacity,
        'minstudyyear' => (int) $activity->minstudyyear,
        'maxstudyyear' => (int) $activity->maxstudyyear,
        'description' => $activity->description,
    ]);
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($proposalsurl);
} else if ($data = $mform->get_data()) {
    $activitydata = (object) [
        'name' => trim($data->name),
        'typeid' => (int) $data->typeid,
        'ects' => (float) $data->ects,
        'capacity' => max(0, (int) $data->capacity),
        'minstudyyear' => (int) $data->minstudyyear,
        'maxstudyyear' => (int) $data->maxstudyyear,
        'description' => (string) $data->description,
    ];
    if ($activity) {
        ep_update_proposal_activity($ep, $activity, $activitydata);
        $activity = $DB->get_record('ep_activity', ['id' => $activity->id], '*', MUST_EXIST);
        $syllabus = ep_ensure_activity_syllabus($ep, $activity);
        $cmid = $syllabus->cmid;
    } else {
        $cmid = ep_create_proposal($ep, $activitydata, $USER->id)->syllabuscmid;
    }
    // L'enseignant enchaîne sur la fiche, qu'il soumettra à la DEVE.
    redirect(
        new moodle_url('/mod/syllabus/edit.php', ['id' => $cmid]),
        get_string('proposalcreated', 'mod_ep'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('proposenew', 'mod_ep'));
echo html_writer::link($proposalsurl, get_string('back'));
echo html_writer::div(get_string('proposalsteps', 'mod_ep'), 'text-muted mt-2 mb-3');
$mform->display();
echo $OUTPUT->footer();
