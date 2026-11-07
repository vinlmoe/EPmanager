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
 * Rédaction du syllabus d'un EP : proposition d'un nouvel EP par un enseignant (activityid = 0),
 * ou mise à jour de la fiche d'un EP existant par son responsable ou la DEVE. La version en
 * vigueur reste au catalogue tant que la DEVE n'a pas publié la nouvelle.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->dirroot . '/mod/ep/classes/form/syllabus_form.php');

use mod_ep\form\syllabus_form;

$id = required_param('id', PARAM_INT);
$activityid = optional_param('activityid', 0, PARAM_INT);

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
} else if (!has_capability('mod/ep:propose', $context) && !has_capability('mod/ep:manage', $context)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('proposenew', 'mod_ep'));
}

$baseurl = new moodle_url('/mod/ep/syllabus_edit.php', ['id' => $cm->id, 'activityid' => $activityid]);
$proposalsurl = new moodle_url('/mod/ep/proposals.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - '
    . ($activity ? get_string('syllabusof', 'mod_ep', format_string($activity->name)) : get_string('proposenew', 'mod_ep')));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$labelsep = get_string('labelsep', 'langconfig');

// Types auxquels rattacher l'EP. Un EP déjà créé sur un type depuis désactivé le garde : le
// retirer de la liste ferait basculer l'EP sur un autre type à l'enregistrement.
$types = ep_get_catalogable_types($ep->id);
if ($activity && !isset($types[$activity->typeid])) {
    $alltypes = ep_get_types($ep->id);
    if (isset($alltypes[$activity->typeid])) {
        $types[$activity->typeid] = $alltypes[$activity->typeid];
    }
}
if (!$activity && empty($types)) {
    redirect($proposalsurl, get_string('errornotypes', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
}

$working = $activity ? ep_get_working_syllabus($activity->id) : null;
$published = $activity ? ep_get_syllabus($activity->id) : null;
$syllabusurl = $activity ? new moodle_url('/mod/ep/syllabus.php', ['id' => $cm->id, 'activityid' => $activity->id]) : null;

// Une version soumise attend la DEVE : elle ne se modifie plus, la page le dit et renvoie à la
// fiche, où la DEVE statue.
if ($working && $working->status === 'submitted') {
    ep_set_secondary_page_header();
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('syllabusof', 'mod_ep', format_string($activity->name)));
    echo $OUTPUT->notification(get_string('pendingrevision', 'mod_ep', (object) [
        'version' => (int) $working->version,
        'status' => get_string('syllabusstatus_submitted', 'mod_ep'),
    ]), 'info');
    echo html_writer::link($syllabusurl, get_string('syllabus', 'mod_ep'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

// Les caractéristiques de l'EP ne se rédigent ici que tant qu'il n'est pas publié : ensuite, elles
// relèvent de la DEVE, dans la gestion du catalogue.
$activityfields = !$activity || in_array($activity->state, [EP_ACTIVITY_DRAFT, EP_ACTIVITY_PROPOSED], true);

// Préremplissage : la version de travail si elle existe, sinon la version publiée (la version de
// travail n'est créée qu'au premier enregistrement, pour qu'ouvrir la page ne laisse pas de
// brouillon vide derrière soi).
$source = $working ?: $published;
$sessions = $source ? array_values(ep_get_syllabus_sessions($source->id)) : [];

// Intervenants proposés : les enseignants du cours, et ceux déjà cités dans le programme qui ne le
// seraient plus (sinon la liste ferait perdre l'intervenant au prochain enregistrement).
$teachers = [];
foreach (ep_get_potential_teachers($context) as $teacher) {
    $teachers[(int) $teacher->id] = fullname($teacher);
}
$missingteachers = array_diff(
    array_unique(array_filter(array_map(fn($session) => (int) $session->teacherid, $sessions))),
    array_keys($teachers)
);
if ($missingteachers) {
    foreach ($DB->get_records_list('user', 'id', $missingteachers) as $teacher) {
        $teachers[(int) $teacher->id] = fullname($teacher);
    }
}

$mform = new syllabus_form($baseurl, [
    'activityfields' => $activityfields,
    'types' => $types,
    'teachers' => $teachers,
    // Deux lignes vides de plus que le programme actuel, pour ajouter des séances sans recharger.
    'sessioncount' => max(3, count($sessions) + 2),
]);

$formdata = ['id' => $cm->id, 'activityid' => $activityid];
if ($activity && $activityfields) {
    $formdata += [
        'name' => $activity->name,
        'typeid' => $activity->typeid,
        'ects' => (float) $activity->ects,
        'capacity' => (int) $activity->capacity,
        'minstudyyear' => (int) $activity->minstudyyear,
        'maxstudyyear' => (int) $activity->maxstudyyear,
        'description' => $activity->description,
    ];
} else if (!$activity) {
    // Premier type qui s'acquiert par le catalogue, dans l'ordre choisi par la DEVE.
    $formdata['typeid'] = (int) reset($types)->id;
}
if ($source) {
    foreach (ep_syllabus_text_fields() as $field) {
        $formdata[$field] = (string) $source->$field;
    }
    $formdata['language'] = $source->language;
    $formdata['personalhours'] = (float) $source->personalhours;
}
foreach ($sessions as $index => $session) {
    $formdata['sessionrow'][$index] = [
        'sessiontitle' => $session->title,
        'sessionkind' => $session->kind,
        'sessionduration' => (float) $session->duration,
        'sessionteacher' => (int) $session->teacherid,
        'sessionteachername' => $session->teachername,
    ];
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($syllabusurl ?: $proposalsurl);
} else if ($data = $mform->get_data()) {
    // Séances : les lignes du formulaire, dans l'ordre, au format de ep_set_syllabus_sessions()
    // (qui ignore les lignes sans intitulé).
    $sessiondata = [];
    foreach ($data->sessionrow ?? [] as $index => $row) {
        $sessiondata[] = [
            'title' => $row['sessiontitle'] ?? '',
            'kind' => $row['sessionkind'] ?? 'other',
            'duration' => (float) ($row['sessionduration'] ?? 0),
            'teacherid' => (int) ($row['sessionteacher'] ?? 0),
            'teachername' => $row['sessionteachername'] ?? '',
        ];
    }
    $textdata = (object) [
        'language' => $data->language ?? '',
        'personalhours' => (float) ($data->personalhours ?? 0),
    ];
    foreach (ep_syllabus_text_fields() as $field) {
        $textdata->$field = $data->$field ?? '';
    }
    $activitydata = null;
    if ($activityfields) {
        $activitydata = (object) [
            'name' => trim($data->name),
            'typeid' => (int) $data->typeid,
            'ects' => (float) $data->ects,
            'capacity' => max(0, (int) $data->capacity),
            'minstudyyear' => (int) $data->minstudyyear,
            'maxstudyyear' => (int) $data->maxstudyyear,
            'description' => (string) $data->description,
        ];
    }

    if (!$activity) {
        $created = ep_create_proposal($ep, $activitydata, $USER->id);
        $activity = $DB->get_record('ep_activity', ['id' => $created->activityid], '*', MUST_EXIST);
        $syllabus = $DB->get_record('ep_syllabus', ['id' => $created->syllabusid], '*', MUST_EXIST);
    } else {
        if ($activitydata) {
            ep_update_proposal_activity($ep, $activity, $activitydata);
            $activity = $DB->get_record('ep_activity', ['id' => $activity->id], '*', MUST_EXIST);
        }
        $syllabus = ep_start_syllabus_revision($activity, $USER->id);
    }
    $syllabusurl = new moodle_url('/mod/ep/syllabus.php', ['id' => $cm->id, 'activityid' => $activity->id]);

    // La version a pu être soumise ou tranchée depuis l'affichage du formulaire (autre onglet,
    // autre rédacteur) : rien n'est alors écrit.
    if (!ep_save_syllabus($syllabus, $textdata, $sessiondata)) {
        redirect($syllabusurl, get_string('errorsyllabusnoteditable', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }

    if (!empty($data->submittodeve)) {
        $syllabus = $DB->get_record('ep_syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $result = ep_submit_syllabus($ep, $activity, $syllabus, $USER->id);
        if ($result === null) {
            redirect($syllabusurl, get_string('errorsyllabusnoteditable', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $message = $result === 'published' ? 'syllabuspublisheddirect' : 'syllabussubmitted';
        redirect($syllabusurl, get_string($message, 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    redirect(
        new moodle_url('/mod/ep/syllabus_edit.php', ['id' => $cm->id, 'activityid' => $activity->id]),
        get_string('syllabussaved', 'mod_ep'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
if ($activity) {
    echo $OUTPUT->heading(get_string('syllabusof', 'mod_ep', format_string($activity->name)));
    echo html_writer::link($syllabusurl, get_string('back'));
} else {
    echo $OUTPUT->heading(get_string('proposenew', 'mod_ep'));
    echo html_writer::link($proposalsurl, get_string('back'));
}

// État de la version en cours de rédaction, et le commentaire de la DEVE si elle l'a renvoyée :
// c'est ce que l'auteur doit corriger avant de la soumettre de nouveau.
if ($working) {
    echo html_writer::div(
        get_string('syllabusversion', 'mod_ep', (int) $working->version) . ' - '
            . get_string('syllabusstatus_' . $working->status, 'mod_ep'),
        'text-muted mt-2'
    );
    $comment = trim((string) $working->reviewcomment);
    if ($working->status === 'returned' && $comment !== '') {
        echo $OUTPUT->notification(
            html_writer::tag('strong', get_string('reviewcomment', 'mod_ep') . $labelsep) . s($comment),
            'warning'
        );
    }
} else if ($published) {
    // Pas encore de version de travail : elle sera reprise de la version publiée au premier
    // enregistrement, qui reste en vigueur jusqu'à la décision de la DEVE.
    echo $OUTPUT->notification(get_string('proposals_help', 'mod_ep'), 'info');
}

$mform->display();
echo $OUTPUT->footer();
