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
 * Déclaration par l'étudiant d'un enseignement personnalisé hors catalogue (engagement étudiant,
 * expérience professionnelle, sport, académique externe), sa modification tant qu'aucune décision
 * n'a été prise, et son retrait. Les EP académiques internes se prennent au catalogue
 * (catalog.php) et ceux de type stage sont attribués automatiquement : ni les uns ni les autres
 * ne se déclarent ici.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->dirroot . '/mod/ep/classes/form/credit_form.php');

use mod_ep\form\credit_form;

$id = required_param('id', PARAM_INT);
$creditid = optional_param('creditid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:submit', $context);

$returnurl = new moodle_url('/mod/ep/view.php', ['id' => $cm->id]);
$baseurl = new moodle_url('/mod/ep/declare.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('declarecredit', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Un étudiant ne manipule que ses propres demandes : la propriété est vérifiée dans la requête
// elle-même, pas après coup.
$credit = null;
if ($creditid) {
    $credit = $DB->get_record(
        'ep_credit',
        ['id' => $creditid, 'epid' => $ep->id, 'userid' => $USER->id],
        '*',
        MUST_EXIST
    );
    if (!ep_student_can_cancel($credit)) {
        throw new moodle_exception('errorcreditdecided', 'mod_ep', $returnurl->out(false));
    }
    // Une inscription au catalogue porte l'intitulé et les ECTS de l'EP : elle ne se modifie pas
    // ici (l'étudiant les choisirait alors lui-même), elle se retire seulement.
    if (!empty($credit->activityid) && $action !== 'cancel') {
        throw new moodle_exception('errorcreditnoteditable', 'mod_ep', $returnurl->out(false));
    }
}

// Retrait d'une demande (déclaration comme inscription au catalogue) tant qu'elle est en attente.
if ($action === 'cancel' && $credit) {
    require_sesskey();
    ep_cancel_credit($credit, $USER->id, get_string('cancelledbystudent', 'mod_ep'));
    redirect($returnurl, get_string('requestcancelled', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$types = ep_get_declarable_types($ep->id);
if (empty($types)) {
    throw new moodle_exception('errornodeclarabletype', 'mod_ep', $returnurl->out(false));
}
// Une demande créée sur un type depuis désactivé reste modifiable sur ce type : la retirer de la
// liste ferait basculer silencieusement la demande sur un autre type à l'enregistrement.
if ($credit && !isset($types[$credit->typeid])) {
    $existingtype = $DB->get_record('ep_type', ['id' => $credit->typeid, 'epid' => $ep->id]);
    if ($existingtype) {
        $types[$existingtype->id] = $existingtype;
    }
}

$fileoptions = [
    'subdirs' => 0,
    'maxfiles' => 10,
    'accepted_types' => ['document', 'image', 'archive'],
];

$mform = new credit_form(new moodle_url($baseurl, ['creditid' => $creditid]), [
    'ep' => $ep,
    'types' => $types,
    'fileoptions' => $fileoptions,
]);

$draftitemid = file_get_submitted_draft_itemid('evidence');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'mod_ep',
    EP_EVIDENCE_FILEAREA,
    $credit ? $credit->id : null,
    $fileoptions
);

if ($credit) {
    $mform->set_data([
        'id' => $cm->id,
        'creditid' => $credit->id,
        'typeid' => $credit->typeid,
        'name' => $credit->name,
        'description' => $credit->description,
        'studyyear' => $credit->studyyear,
        'claimedects' => (float) $credit->claimedects,
        'evidence' => $draftitemid,
    ]);
} else {
    $mform->set_data(['id' => $cm->id, 'creditid' => 0, 'evidence' => $draftitemid]);
}

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    // Le type soumis est revérifié contre la liste réellement proposée : une valeur forgée ne
    // doit pas permettre de déclarer un EP sur un type du catalogue ou attribué automatiquement,
    // qui court-circuiterait respectivement l'inscription et la validation.
    if (!isset($types[$data->typeid])) {
        throw new moodle_exception('errorinvalidtype', 'mod_ep', $returnurl->out(false));
    }
    $type = $types[$data->typeid];

    if ($credit) {
        $credit->typeid = $type->id;
        $credit->studyyear = (int) $data->studyyear;
        $credit->name = $data->name;
        $credit->description = $data->description;
        $credit->claimedects = round((float) $data->claimedects, 2);
        $credit->timemodified = time();
        $DB->update_record('ep_credit', $credit);
        $savedid = $credit->id;
    } else {
        $savedid = ep_create_credit($ep, $USER->id, $type, [
            'studyyear' => (int) $data->studyyear,
            'name' => $data->name,
            'description' => $data->description,
            'claimedects' => $data->claimedects,
            'source' => EP_SOURCE_STUDENT,
        ]);
    }

    file_save_draft_area_files(
        $data->evidence,
        $context->id,
        'mod_ep',
        EP_EVIDENCE_FILEAREA,
        $savedid,
        $fileoptions
    );

    redirect($returnurl, get_string('creditsubmitted', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('declarecredit', 'mod_ep'));
echo html_writer::link($returnurl, get_string('back'));

$referents = ep_get_student_referents($ep, $USER->id);
echo $OUTPUT->notification(
    empty($referents)
        ? get_string('declarenoreferent', 'mod_ep')
        : get_string('declarereferent', 'mod_ep', implode(', ', array_map('fullname', $referents))),
    empty($referents) ? 'warning' : 'info'
);

$mform->display();

echo $OUTPUT->footer();
