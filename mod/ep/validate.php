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
 * Fiche d'un enseignement personnalisé porté au crédit d'un étudiant : le rappel de la demande et
 * de ses justificatifs, et — pour qui a le droit de statuer dessus — la validation (avec le
 * nombre d'ECTS effectivement retenu) ou le refus motivé. La même page sert de détail en lecture
 * seule à l'étudiant concerné, pour qu'il y retrouve la décision et son motif.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$creditid = required_param('creditid', PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

$credit = $DB->get_record('ep_credit', ['id' => $creditid, 'epid' => $ep->id], '*', MUST_EXIST);
$rights = ep_get_user_rights($ep, $context);

$isowner = (int) $credit->userid === (int) $USER->id;
$canvalidate = ep_rights_can_validate($rights, $credit);
if (!$isowner && !$rights->viewall && !$canvalidate) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('creditdetail', 'mod_ep'));
}

// Écran de retour : la liste d'où l'on vient (validation, pilotage, tableau de bord étudiant) si
// elle a été transmise, sinon la page d'accueil de l'activité.
$backurl = $returnurlparam !== '' ? new moodle_url($returnurlparam) : new moodle_url('/mod/ep/view.php', ['id' => $cm->id]);
$pageurl = new moodle_url(
    '/mod/ep/validate.php',
    ['id' => $cm->id, 'creditid' => $credit->id, 'returnurl' => $returnurlparam]
);

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('creditdetail', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$student = $DB->get_record('user', ['id' => $credit->userid], '*', MUST_EXIST);
$type = $DB->get_record('ep_type', ['id' => $credit->typeid]);
$activity = !empty($credit->activityid) ? $DB->get_record('ep_activity', ['id' => $credit->activityid]) : null;

// Retrait d'un crédit par la DEVE : possible à tout moment, y compris sur un crédit déjà validé
// (une pièce justificative peut se révéler fausse après coup). Les crédits attribués
// automatiquement en sont exclus : ils seraient recréés à la synchronisation suivante, c'est le
// stage d'origine qu'il faut alors reprendre dans mod_stage.
if (
    $rights->validatedeve && $credit->source !== EP_SOURCE_STAGE
        && optional_param('cancelcredit', 0, PARAM_INT) && confirm_sesskey()
) {
    ep_cancel_credit($credit, $USER->id, optional_param('validatorcomment', '', PARAM_TEXT));
    redirect($backurl, get_string('creditcancelled', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($canvalidate && (int) $credit->status === EP_STATUS_PENDING && data_submitted() && confirm_sesskey()) {
    $comment = optional_param('validatorcomment', '', PARAM_TEXT);
    if (optional_param('rejectcredit', '', PARAM_RAW) !== '') {
        ep_reject_credit($credit, $USER->id, $comment);
        redirect($backurl, get_string('creditrejected', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if (optional_param('validatecredit', '', PARAM_RAW) !== '') {
        $retained = optional_param('retainedects', 0, PARAM_FLOAT);
        ep_validate_credit($credit, $USER->id, $retained, $comment);
        redirect($backurl, get_string('creditvalidated', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('creditdetail', 'mod_ep'));
echo html_writer::link($backurl, get_string('back'));

echo ep_render_credit_summary($credit, $type, $isowner ? null : $student);

if ($activity) {
    $teachers = ep_get_activity_teachers($activity->id);
    echo html_writer::div(
        get_string('fromcatalogactivity', 'mod_ep', (object) [
            'name' => format_string($activity->name),
            'teachers' => empty($teachers) ? '-' : implode(', ', array_map('fullname', $teachers)),
        ]),
        'text-muted mb-3'
    );
}

echo $OUTPUT->heading(get_string('evidencefiles', 'mod_ep'), 4);
echo ep_render_evidence_files($cm, $context, $credit);

if ($credit->source === EP_SOURCE_STAGE) {
    // Rien à valider ici : le crédit suit le stage d'origine, y compris s'il est dévalidé.
    echo $OUTPUT->notification(get_string('automaticcreditnotice', 'mod_ep'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if ($canvalidate && (int) $credit->status === EP_STATUS_PENDING) {
    echo $OUTPUT->heading(get_string('decision', 'mod_ep'), 4);

    // Le nombre d'ECTS retenu est proposé à la valeur demandée — celle de l'EP du catalogue, ou
    // celle avancée par l'étudiant — et reste modifiable : le validateur peut n'en retenir
    // qu'une partie sans avoir à refuser toute la demande.
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('label', get_string('retainedects', 'mod_ep'), ['for' => 'retainedects']);
    echo html_writer::empty_tag('input', [
        'type' => 'number', 'step' => '0.25', 'min' => 0, 'name' => 'retainedects', 'id' => 'retainedects',
        'value' => ep_format_ects_input($credit->claimedects), 'class' => 'form-control',
    ]);
    echo html_writer::tag('label', get_string('validatorcomment', 'mod_ep'), ['for' => 'validatorcomment']);
    echo html_writer::tag(
        'textarea',
        '',
        ['name' => 'validatorcomment', 'id' => 'validatorcomment', 'rows' => 4, 'class' => 'form-control']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'name' => 'validatecredit', 'value' => get_string('validate', 'mod_ep'),
        'class' => 'btn btn-primary mt-2 mr-2',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'name' => 'rejectcredit', 'value' => get_string('reject', 'mod_ep'),
        'class' => 'btn btn-danger mt-2',
    ]);
    echo html_writer::end_tag('form');
} else if ($canvalidate) {
    echo $OUTPUT->notification(get_string('creditalreadydecided', 'mod_ep'), 'info');
}

// Retrait par la DEVE, isolé en fin de page et sous confirmation : c'est l'action qui défait ce
// que les autres ont fait, elle n'a pas à côtoyer les boutons du travail courant.
if ($rights->validatedeve && (int) $credit->status !== EP_STATUS_CANCELLED) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false), 'class' => 'mt-4']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cancelcredit', 'value' => 1]);
    echo html_writer::tag('label', get_string('cancelreason', 'mod_ep'), ['for' => 'cancelcomment']);
    echo html_writer::tag(
        'textarea',
        '',
        ['name' => 'validatorcomment', 'id' => 'cancelcomment', 'rows' => 2, 'class' => 'form-control']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('cancelcredit', 'mod_ep'),
        'class' => 'btn btn-outline-danger mt-2',
        'onclick' => "return confirm('" . get_string('confirmcancelcredit', 'mod_ep') . "');",
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
