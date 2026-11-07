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
 * Rentrée (DEVE) : passage de l'activité à l'année d'étude suivante, après un rappel des
 * étudiants dont l'année courante n'est pas validée ; transfert du dossier d'un étudiant vers une
 * autre activité « Enseignement personnalisé » du site (redoublement, changement de promotion).
 *
 * Les deux opérations changent durablement le dossier : chacune passe par une page de
 * confirmation, et la rentrée rappelle d'abord ce qui reste à décider pour l'année qui se ferme.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);
// EP de type stage à jour avant tout décompte (voir ep_sync_stage_credits_if_due()).
ep_sync_stage_credits_if_due($ep);

$baseurl = new moodle_url('/mod/ep/rollover.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('rollover', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$nextyear = min(6, (int) $ep->currentstudyyear + 1);

if ($action === 'rollover' && $nextyear !== (int) $ep->currentstudyyear) {
    if ($confirm) {
        require_sesskey();
        $year = ep_rollover($ep, required_param('from', PARAM_INT));
        if ($year === false) {
            redirect($baseurl, get_string('rolloverstale', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect(
            $baseurl,
            get_string('rolloverdone', 'mod_ep', ep_studyyear_label($year)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    ep_set_secondary_page_header();
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('rollover', 'mod_ep'));
    echo $OUTPUT->confirm(
        get_string('rolloverconfirm', 'mod_ep', (object) [
            'from' => ep_studyyear_label($ep->currentstudyyear),
            'to' => ep_studyyear_label($nextyear),
        ]),
        new single_button(
            new moodle_url($baseurl, ['action' => 'rollover', 'confirm' => 1, 'from' => $ep->currentstudyyear,
                'sesskey' => sesskey()]),
            get_string('rollover', 'mod_ep'),
            'post',
            single_button::BUTTON_PRIMARY
        ),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

$targets = ep_get_managed_instances((int) $cm->id);

if ($action === 'transfer') {
    // Le choix lui-même est soumis en POST avec la clé de session, comme la confirmation : la
    // page de confirmation ne s'affiche que pour une demande émise depuis cette page.
    require_sesskey();
    $userid = required_param('userid', PARAM_INT);
    $targetcmid = required_param('targetcmid', PARAM_INT);
    if (!isset($targets[$targetcmid])) {
        throw new moodle_exception('invalidcoursemodule', 'error');
    }
    if (ep_count_enrolled_students($context, '', [$userid]) !== 1) {
        throw new moodle_exception('invaliduserid', 'error');
    }
    $targetcm = get_coursemodule_from_id('ep', $targetcmid, 0, false, MUST_EXIST);
    $target = $DB->get_record('ep', ['id' => $targetcm->instance], '*', MUST_EXIST);
    $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    // L'étudiant doit déjà être inscrit dans le cours de destination : son dossier et ses
    // justificatifs n'y vont que s'il y est étudiant.
    if (ep_count_enrolled_students(context_module::instance($targetcm->id), '', [$userid]) !== 1) {
        redirect(
            $baseurl,
            get_string('transfernotenrolled', 'mod_ep', fullname($student)),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    if ($confirm) {
        $result = ep_transfer_student($ep, $target, $userid);
        redirect($baseurl, get_string('transferdone', 'mod_ep', $result), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    ep_set_secondary_page_header();
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('transferstudent', 'mod_ep'));
    echo $OUTPUT->confirm(
        get_string('transferconfirm', 'mod_ep', (object) [
            'student' => fullname($student),
            'target' => $targets[$targetcmid],
        ]),
        new single_button(
            new moodle_url($baseurl, [
                'action' => 'transfer', 'userid' => $userid, 'targetcmid' => $targetcmid,
                'confirm' => 1, 'sesskey' => sesskey(),
            ]),
            get_string('transferstudent', 'mod_ep'),
            'post',
            single_button::BUTTON_DANGER
        ),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('rollover', 'mod_ep'), 'rollover', 'mod_ep');
echo html_writer::link(new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]), get_string('back'));

// 1. Rentrée : année courante, et ce qui reste à décider pour elle.
echo html_writer::tag(
    'p',
    get_string('currentstudyyear', 'mod_ep') . get_string('labelsep', 'langconfig')
        . html_writer::tag('strong', ep_studyyear_label($ep->currentstudyyear)),
    ['class' => 'mt-3']
);

$built = ep_enrolled_students_sql($context);
$userids = [];
if ($built !== null) {
    [$sql, $params] = $built;
    $userids = array_map('intval', $DB->get_fieldset_sql("SELECT u.id $sql", $params));
}
if ((int) $ep->currentstudyyear > 0 && $userids) {
    // Sans décision, ou décision « non validée » : les deux appellent un regard avant la rentrée.
    $validations = ep_get_year_validations($ep->id, $userids);
    $pending = 0;
    foreach ($userids as $userid) {
        $validation = $validations[$userid][(int) $ep->currentstudyyear] ?? null;
        if (!$validation || (int) $validation->decision !== 1) {
            $pending++;
        }
    }
    if ($pending > 0 && has_capability('mod/ep:validatedeve', $context)) {
        echo $OUTPUT->notification(
            get_string('rolloverpending', 'mod_ep', $pending) . ' '
                . html_writer::link(
                    new moodle_url('/mod/ep/year_validation.php', ['id' => $cm->id, 'studyyear' => $ep->currentstudyyear]),
                    get_string('yearvalidation', 'mod_ep')
                ),
            \core\output\notification::NOTIFY_WARNING
        );
    } else if ($pending > 0) {
        echo $OUTPUT->notification(get_string('rolloverpending', 'mod_ep', $pending), \core\output\notification::NOTIFY_WARNING);
    }
}

// En sixième année, il n'y a pas d'année suivante : le bouton disparaît plutôt que de ne rien faire.
if ($nextyear !== (int) $ep->currentstudyyear) {
    echo $OUTPUT->single_button(
        new moodle_url($baseurl, ['action' => 'rollover']),
        get_string('rollover', 'mod_ep'),
        'get',
        ['type' => single_button::BUTTON_PRIMARY]
    );
}

// 2. Transfert d'un étudiant vers une autre activité du site.
echo $OUTPUT->heading_with_help(get_string('transferstudent', 'mod_ep'), 'transferstudent', 'mod_ep', '', '', 3);
if (empty($targets)) {
    echo $OUTPUT->notification(get_string('transfernotarget', 'mod_ep'), \core\output\notification::NOTIFY_INFO);
} else if (empty($userids)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_ep'), \core\output\notification::NOTIFY_INFO);
} else {
    $students = [];
    foreach (ep_get_enrolled_students($context) as $student) {
        $label = fullname($student);
        if (trim((string) $student->idnumber) !== '') {
            $label .= ' (' . $student->idnumber . ')';
        }
        // html_writer::select() n'échappe pas les libellés : idnumber est un texte libre.
        $students[$student->id] = s($label);
    }
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'mt-2']);
    foreach (['sesskey' => sesskey(), 'action' => 'transfer'] as $name => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    echo html_writer::div(
        html_writer::label(get_string('student', 'mod_ep'), 'ep-transfer-userid', true, ['class' => 'mr-2'])
        . html_writer::select($students, 'userid', '', ['' => 'choosedots'], [
            'id' => 'ep-transfer-userid', 'class' => 'custom-select', 'required' => 'required',
        ]),
        'form-group'
    );
    echo html_writer::div(
        html_writer::label(get_string('transfertarget', 'mod_ep'), 'ep-transfer-target', true, ['class' => 'mr-2'])
        . html_writer::select($targets, 'targetcmid', '', ['' => 'choosedots'], [
            'id' => 'ep-transfer-target', 'class' => 'custom-select', 'required' => 'required',
        ]),
        'form-group'
    );
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('transferstudent', 'mod_ep'), 'class' => 'btn btn-secondary',
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
