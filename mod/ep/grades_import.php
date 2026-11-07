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
 * Import des notes ou des validations des participants d'un EP du catalogue depuis un fichier
 * CSV, en deux temps : le fichier déposé est lu et présenté en prévisualisation (ce qui sera
 * validé, refusé, corrigé ou ignoré), puis appliqué sur confirmation, avec les mêmes contrôles que
 * la saisie dans la liste des participants.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\form\grades_import_form;
use mod_ep\local\grades_importer;

$id = required_param('id', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

$activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
$rights = ep_get_user_rights($ep, $context);
if (!ep_rights_can_manage_participants($rights, $activity)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('importgrades', 'mod_ep'));
}

$pageurl = new moodle_url('/mod/ep/grades_import.php', ['id' => $cm->id, 'activityid' => $activity->id]);
$participantsurl = new moodle_url('/mod/ep/participants.php', ['id' => $cm->id, 'activityid' => $activity->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('importgrades', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$grading = ep_activity_grading($activity);
$graded = $grading->grading === EP_GRADING_GRADE;
// Séparateur « libellé : valeur » de la langue courante (espace avant les deux-points en français).
$labelsep = get_string('labelsep', 'langconfig');
// En texte brut pour le résultat de chaque ligne, échappé par s().
$plainsep = html_entity_decode($labelsep, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Prévisualisation en attente de confirmation : un fichier temporaire propre à l'utilisateur et à
// l'EP plutôt que la session, qu'une longue liste alourdirait. Il ne contient que des lignes déjà
// rapprochées des inscriptions, est remplacé à chaque dépôt et supprimé à la confirmation, si
// bien qu'une actualisation de la page ne rejoue pas l'import.
$previewpath = make_temp_directory('mod_ep/gradesimport') . '/' . (int) $USER->id . '-' . (int) $activity->id . '.json';

if ($action === 'confirm' && data_submitted() && confirm_sesskey()) {
    $stored = null;
    if (is_readable($previewpath) && filemtime($previewpath) >= time() - DAYSECS) {
        $stored = json_decode((string) file_get_contents($previewpath), true);
    }
    if (file_exists($previewpath)) {
        unlink($previewpath);
    }
    if (!is_array($stored) || empty($stored)) {
        redirect($pageurl, get_string('importgradesexpired', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }

    // Les valeurs sont retypées : le fichier a été écrit par cette page, mais c'est la dernière
    // barrière avant l'écriture en base. ep_apply_participant_decisions() revérifie ensuite les
    // droits et l'état de chaque inscription, qui ont pu changer depuis la prévisualisation.
    $entries = [];
    foreach ($stored as $row) {
        $key = (int) ($row['creditid'] ?? 0);
        if ($key <= 0) {
            continue;
        }
        $decision = (string) ($row['decision'] ?? '');
        $entries[$key] = [
            'grade' => isset($row['grade']) && is_numeric($row['grade']) ? (float) $row['grade'] : null,
            'decision' => in_array($decision, ['validate', 'reject'], true) ? $decision : '',
            'comment' => clean_param((string) ($row['comment'] ?? ''), PARAM_TEXT),
        ];
    }
    $result = ep_apply_participant_decisions($ep, $activity, $entries, $rights);
    if ($result->changed > 0) {
        \core\notification::add(
            get_string('decisionschanged', 'mod_ep', $result->changed),
            \core\output\notification::NOTIFY_WARNING
        );
    }
    redirect($participantsurl, get_string('decisionssaved', 'mod_ep', $result), null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform = new grades_import_form($pageurl);
$mform->set_data(['id' => $cm->id, 'activityid' => $activity->id]);

if ($mform->is_cancelled()) {
    redirect($participantsurl);
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importgrades', 'mod_ep') . $labelsep . format_string($activity->name));
echo html_writer::link($participantsurl, get_string('back'));

if (!$mform->get_data()) {
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

$participants = ep_get_participants($activity);
$users = ep_get_credit_users($participants);
$read = grades_importer::read((string) $mform->get_file_content('csvfile'), $participants, $users, $grading);

echo $OUTPUT->heading(get_string('importgradespreview', 'mod_ep'), 4);
if ($read->errors) {
    // Les messages d'erreur échappent déjà les valeurs lues dans le fichier.
    echo $OUTPUT->notification(html_writer::alist($read->errors), 'warning');
}

if (empty($read->rows)) {
    // Une prévisualisation antérieure ne doit pas pouvoir être confirmée depuis un autre onglet
    // après le dépôt d'un fichier qui la remplace.
    if (file_exists($previewpath)) {
        unlink($previewpath);
    }
    echo $OUTPUT->notification(get_string('importgradesnone', 'mod_ep'), 'warning');
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Écriture dans un fichier voisin puis renommage : une confirmation simultanée ne lit jamais un
// fichier à moitié écrit.
$tmppath = $previewpath . '.' . random_string(8);
file_put_contents($tmppath, json_encode(array_map(fn($row) => [
    'creditid' => $row->creditid,
    'grade' => $row->grade,
    'decision' => $row->decision,
    'comment' => $row->comment,
], $read->rows), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
rename($tmppath, $previewpath);

// Ce qui se passera pour chaque ligne, selon les règles de ep_apply_participant_decisions() :
// une inscription en attente est tranchée par qui peut statuer à son étape ; une inscription déjà
// tranchée n'est reprise que par une correction de note de la DEVE ; tout le reste est ignoré.
$types = ep_get_types($ep->id);
$outcome = function (stdClass $credit, stdClass $row) use ($rights, $graded, $grading, $types, $plainsep) {
    $status = (int) $credit->status;
    $skipped = get_string('skipped');
    if ($status === EP_STATUS_VALIDATED || $status === EP_STATUS_REJECTED) {
        if (!$graded || !$rights->validatedeve || $row->grade === null) {
            return $skipped;
        }
        if ($credit->grade !== null && (float) $credit->grade === (float) $row->grade) {
            return get_string('nochange');
        }
        $passed = $row->grade >= $grading->gradepass;
        return get_string('decision_corrected', 'mod_ep') . $plainsep
            . get_string($passed ? 'decisionvalidate' : 'decisionreject', 'mod_ep');
    }
    if ($status !== EP_STATUS_PENDING) {
        return $skipped;
    }
    if (!ep_rights_can_validate($rights, $credit)) {
        return $skipped . ' - ' . get_string('awaitingotherrole', 'mod_ep', ep_role_label(ep_credit_pending_role($credit)));
    }
    $passed = $graded ? $row->grade >= $grading->gradepass : $row->decision === 'validate';
    $label = get_string($passed ? 'decisionvalidate' : 'decisionreject', 'mod_ep');
    // Une validation à une étape intermédiaire du circuit ne tranche pas : la demande passe au
    // rôle suivant, ce que la prévisualisation doit dire.
    $role = ep_rights_deciding_role($rights, $credit);
    if ($passed && $role !== EP_ROLE_DEVE) {
        $type = $types[$credit->typeid] ?? null;
        $steps = $type ? ep_flow_steps($type->validationflow) : [];
        $next = $steps[(int) $credit->step + 1] ?? null;
        if ($next !== null) {
            $label .= ' - ' . get_string('nextstepnotice', 'mod_ep', ep_role_label($next));
        }
    }
    return $label;
};

$head = [
    get_string('line', 'debug'),
    get_string('student', 'mod_ep'),
    get_string('status', 'mod_ep'),
    $graded ? get_string('grade', 'mod_ep') : get_string('importgradesfile', 'mod_ep'),
    get_string('validatorcomment', 'mod_ep'),
    get_string('decision', 'mod_ep'),
];
$rows = [];
foreach ($read->rows as $row) {
    $credit = $participants[$row->creditid];
    $user = $users[$row->userid] ?? null;

    $statuscell = ep_render_credit_status($credit->status);
    if ($graded && $credit->grade !== null) {
        $statuscell .= html_writer::div(get_string('gradeoutof', 'mod_ep', (object) [
            'grade' => ep_format_ects($credit->grade),
            'max' => ep_format_ects($grading->grademax),
        ]), 'small text-muted');
    }
    if ($graded) {
        $value = get_string('gradeoutof', 'mod_ep', (object) [
            'grade' => ep_format_ects($row->grade),
            'max' => ep_format_ects($grading->grademax),
        ]);
    } else {
        $value = get_string($row->decision === 'validate' ? 'decisionvalidate' : 'decisionreject', 'mod_ep');
    }

    $rows[] = [
        $row->line,
        $user ? s(fullname($user)) : '-',
        $statuscell,
        $value,
        $row->comment !== '' ? s($row->comment) : '-',
        s($outcome($credit, $row)),
    ];
}
echo ep_render_paged_table($head, $rows);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirm']);
echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('importgradesconfirm', 'mod_ep'), 'class' => 'btn btn-primary mr-2',
]);
echo html_writer::link($participantsurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
