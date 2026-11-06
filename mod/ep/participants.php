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
 * Participants d'un EP du catalogue, pour la DEVE et les responsables de cet EP : la liste des
 * inscriptions, avec la saisie en une fois des notes ou des validations, l'ajout d'étudiants (y
 * compris après la période d'inscription), le retrait d'un participant en attente, l'export CSV
 * (qui sert aussi de modèle d'import des notes), la feuille d'émargement et la clôture de l'EP.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$creditid = optional_param('creditid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

// L'EP est relu dans cette instance : un identifiant d'EP d'une autre activité ne doit pas ouvrir
// ses participants à qui gère ceux d'ici.
$activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
$rights = ep_get_user_rights($ep, $context);
if (!ep_rights_can_manage_participants($rights, $activity)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('participants', 'mod_ep'));
}

$baseurl = new moodle_url('/mod/ep/participants.php', ['id' => $cm->id, 'activityid' => $activity->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('participantsof', 'mod_ep', format_string($activity->name)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$grading = ep_activity_grading($activity);
$graded = $grading->grading === EP_GRADING_GRADE;
$activestatuses = [EP_STATUS_PENDING, EP_STATUS_VALIDATED];
// Séparateur « libellé : valeur » de la langue courante (espace avant les deux-points en français).
$labelsep = get_string('labelsep', 'langconfig');

// Export CSV de la liste. Ses intitulés de colonnes sont ceux que grades_importer reconnaît, et
// les inscriptions retirées n'y figurent pas : complété des notes ou des décisions, le fichier se
// réimporte tel quel.
if ($action === 'export') {
    require_once($CFG->libdir . '/csvlib.class.php');

    // Intitulé de la colonne de l'adresse : celui du cœur s'il est reconnu à l'import, sinon un
    // intitulé qui l'est toujours (certains paquets de langue le formulent autrement).
    $emailheader = get_string('email');
    $emailaliases = \mod_ep\local\grades_importer::HEADERS['email'];
    if (!in_array(\mod_ep\local\grades_importer::normalise($emailheader), $emailaliases, true)) {
        $emailheader = 'Email';
    }

    // Point-virgule et BOM : le fichier s'ouvre directement dans un tableur réglé en français, où
    // la virgule est le séparateur décimal des notes.
    $export = new csv_export_writer('semicolon', '"', 'application/download', true);
    $export->set_filename(clean_filename(format_string($activity->name) . '-participants'));
    $export->add_data([
        get_string('lastname'),
        get_string('firstname'),
        $emailheader,
        get_string('idnumber'),
        get_string('studyyear', 'mod_ep'),
        get_string('status', 'mod_ep'),
        $graded ? get_string('grade', 'mod_ep') : get_string('decision', 'mod_ep'),
        get_string('retainedects', 'mod_ep'),
        get_string('decidedon', 'mod_ep'),
        get_string('validatorcomment', 'mod_ep'),
    ]);

    $credits = ep_get_participants($activity, [EP_STATUS_PENDING, EP_STATUS_VALIDATED, EP_STATUS_REJECTED]);
    $students = ep_get_credit_users($credits);
    foreach ($credits as $credit) {
        $student = $students[$credit->userid] ?? null;
        $status = (int) $credit->status;
        if ($graded) {
            $result = $credit->grade !== null ? ep_format_ects($credit->grade) : '';
        } else if ($status === EP_STATUS_VALIDATED) {
            $result = get_string('decisionvalidate', 'mod_ep');
        } else if ($status === EP_STATUS_REJECTED) {
            $result = get_string('decisionreject', 'mod_ep');
        } else {
            $result = '';
        }
        $export->add_data([
            $student ? $student->lastname : '',
            $student ? $student->firstname : '',
            $student ? $student->email : '',
            $student ? $student->idnumber : '',
            ep_studyyear_label($credit->studyyear),
            ep_status_label($status),
            $result,
            $status === EP_STATUS_VALIDATED ? ep_format_ects($credit->retainedects) : '',
            $status !== EP_STATUS_PENDING && $credit->validatetime
                ? userdate($credit->validatetime, get_string('strftimedatetimeshort')) : '',
            (string) $credit->validatorcomment,
        ]);
    }
    $export->download_file();
    exit;
}

// Feuille d'émargement : les inscriptions qui comptent (en attente ou validées), une ligne par
// étudiant avec une case de signature assez large pour signer à la main.
if ($action === 'sheet') {
    require_once($CFG->libdir . '/pdflib.php');

    $credits = ep_get_participants($activity, $activestatuses);
    $students = ep_get_credit_users($credits);

    $html = html_writer::tag('h2', format_string($activity->name));
    $dates = [];
    $dateformat = get_string('strftimedate');
    if (!empty($activity->datestart)) {
        $dates[] = get_string('activitydatestart', 'mod_ep') . $labelsep . userdate($activity->datestart, $dateformat);
    }
    if (!empty($activity->dateend)) {
        $dates[] = get_string('activitydateend', 'mod_ep') . $labelsep . userdate($activity->dateend, $dateformat);
    }
    if ($dates) {
        $html .= html_writer::tag('p', s(implode(' - ', $dates)));
    }
    $html .= html_writer::tag('p', s(get_string('participantscount', 'mod_ep', count($credits))));

    $head = html_writer::tag(
        'tr',
        html_writer::tag('th', s(get_string('lastname')), ['width' => '30%'])
            . html_writer::tag('th', s(get_string('firstname')), ['width' => '30%'])
            . html_writer::tag('th', s(get_string('signature', 'mod_ep')), ['width' => '40%']),
        ['style' => 'font-weight:bold;background-color:#eeeeee;']
    );
    $body = '';
    foreach ($credits as $credit) {
        $student = $students[$credit->userid] ?? null;
        // TCPDF ignore la hauteur des lignes de tableau : une ligne vide dans la cellule de
        // signature donne la place de signer.
        $body .= html_writer::tag(
            'tr',
            html_writer::tag('td', $student ? s($student->lastname) : '-', ['width' => '30%'])
                . html_writer::tag('td', $student ? s($student->firstname) : '-', ['width' => '30%'])
                . html_writer::tag('td', '<br />', ['width' => '40%']),
            ['nobr' => 'true']
        );
    }
    // Le thead est répété par TCPDF en haut de chaque page.
    $html .= html_writer::tag(
        'table',
        html_writer::tag('thead', $head) . html_writer::tag('tbody', $body),
        ['border' => '1', 'cellpadding' => '6', 'width' => '100%']
    );

    $pdf = new pdf('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetTitle(format_string($activity->name, true, ['escape' => false]));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetFont(PDF_FONT_NAME_MAIN, '', 11);
    $pdf->AddPage();
    $pdf->writeHTML($html);

    $code = trim((string) ($activity->code ?? ''));
    $pdf->Output(clean_filename('emargement-' . ($code !== '' ? $code : format_string($activity->name)) . '.pdf'), 'D');
    exit;
}

// Clôture : une page de confirmation propose le sort des inscriptions encore en attente. Valider
// d'office n'a pas de sens pour un EP noté (une note ne s'invente pas) : le choix y est désactivé,
// et ep_close_activity() le ramène de toute façon à « laisser en attente ».
if ($action === 'close') {
    if ($activity->state !== EP_ACTIVITY_PUBLISHED) {
        redirect($baseurl);
    }
    $closeurl = new moodle_url($baseurl, ['action' => 'close']);
    if (data_submitted() && confirm_sesskey()) {
        $choice = optional_param('pending', 'keep', PARAM_ALPHA);
        if (!in_array($choice, ['keep', 'validate', 'reject'], true) || ($choice === 'validate' && $graded)) {
            $choice = 'keep';
        }
        $result = ep_close_activity($ep, $activity, $rights, $choice);
        redirect($baseurl, get_string('activityclosed', 'mod_ep', $result), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('closeactivity', 'mod_ep') . $labelsep . format_string($activity->name));
    echo $OUTPUT->notification(get_string('closeactivity_help', 'mod_ep'), 'info');
    $pendingcount = count(ep_get_participants($activity, [EP_STATUS_PENDING]));
    echo html_writer::tag('p', get_string('pendingregistrations', 'mod_ep') . $labelsep . $pendingcount);

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $closeurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $choices = [
        'keep' => get_string('closependingkeep', 'mod_ep'),
        'validate' => get_string('closependingvalidate', 'mod_ep'),
        'reject' => get_string('closependingreject', 'mod_ep'),
    ];
    foreach ($choices as $value => $label) {
        $attributes = ['type' => 'radio', 'name' => 'pending', 'value' => $value, 'id' => 'pending_' . $value,
            'class' => 'form-check-input'];
        if ($value === 'keep') {
            $attributes['checked'] = 'checked';
        }
        if ($value === 'validate' && $graded) {
            $attributes['disabled'] = 'disabled';
        }
        echo html_writer::div(
            html_writer::empty_tag('input', $attributes)
                . html_writer::tag('label', $label, ['for' => 'pending_' . $value, 'class' => 'form-check-label']),
            'form-check'
        );
    }
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('closeactivity', 'mod_ep'), 'class' => 'btn btn-primary mt-3 mr-2',
    ]);
    echo html_writer::link($baseurl, get_string('cancel'), ['class' => 'btn btn-secondary mt-3']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// Réouverture d'un EP clos : il accepte de nouveau des inscriptions, dans sa période d'inscription.
if ($action === 'reopen') {
    require_sesskey();
    if ($activity->state === EP_ACTIVITY_CLOSED) {
        $DB->set_field('ep_activity', 'state', EP_ACTIVITY_PUBLISHED, ['id' => $activity->id]);
        $DB->set_field('ep_activity', 'timemodified', time(), ['id' => $activity->id]);
    }
    redirect($baseurl, get_string('activityreopened', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Retrait d'un participant dont l'inscription est encore en attente. Le responsable retire en
// tant que tel (rôle « owner », limité aux inscriptions en attente par ep_cancel_credit()) ; la
// DEVE en tant que DEVE, pour que l'historique dise qui a retiré.
if ($action === 'withdraw') {
    $credit = $DB->get_record('ep_credit', ['id' => $creditid, 'activityid' => $activity->id], '*', MUST_EXIST);
    if ((int) $credit->status !== EP_STATUS_PENDING) {
        redirect($baseurl, get_string('errorcreditchanged', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if (data_submitted() && confirm_sesskey()) {
        $reason = optional_param('reason', '', PARAM_TEXT);
        if (!ep_cancel_credit($credit, $USER->id, $reason, $rights->validatedeve ? EP_ROLE_DEVE : EP_ROLE_OWNER)) {
            redirect($baseurl, get_string('errorcreditchanged', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect($baseurl, get_string('participantwithdrawn', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    $student = $DB->get_record('user', ['id' => $credit->userid], '*', MUST_EXIST);
    $withdrawurl = new moodle_url($baseurl, ['action' => 'withdraw', 'creditid' => $credit->id]);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('withdrawparticipant', 'mod_ep') . $labelsep . format_string($activity->name));
    echo $OUTPUT->notification(get_string('confirmwithdraw', 'mod_ep', fullname($student)), 'warning');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $withdrawurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('label', get_string('withdrawreason', 'mod_ep'), ['for' => 'withdrawreason']);
    echo html_writer::tag('textarea', '', ['name' => 'reason', 'id' => 'withdrawreason', 'rows' => 3, 'class' => 'form-control']);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('withdrawparticipant', 'mod_ep'), 'class' => 'btn btn-danger mt-3 mr-2',
    ]);
    echo html_writer::link($baseurl, get_string('cancel'), ['class' => 'btn btn-secondary mt-3']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

$participants = ep_get_participants($activity);

// Étudiants du cours qui peuvent être ajoutés : ceux sans inscription en cours à cet EP. La même
// liste filtre les identifiants reçus à l'ajout, pour qu'on n'inscrive pas un compte qui n'est
// pas étudiant du cours en forgeant la requête.
$registered = [];
foreach ($participants as $credit) {
    if (in_array((int) $credit->status, $activestatuses, true)) {
        $registered[(int) $credit->userid] = true;
    }
}
$candidates = array_diff_key(ep_get_enrolled_students($context), $registered);

if ($action === 'add' && data_submitted() && confirm_sesskey()) {
    $requested = array_unique(array_filter(optional_param_array('userids', [], PARAM_INT)));
    $userids = array_values(array_intersect($requested, array_keys($candidates)));
    $force = optional_param('force', 0, PARAM_BOOL) && $rights->validatedeve;
    if (empty($userids)) {
        redirect($baseurl);
    }
    $result = ep_add_participants($ep, $activity, $userids, $USER->id, $force);
    $skipped = count($result->skipped) + count($requested) - count($userids);
    if ($skipped > 0) {
        \core\notification::add(get_string('participantsskipped', 'mod_ep', $skipped), \core\output\notification::NOTIFY_WARNING);
    }
    redirect(
        $baseurl,
        get_string('participantsadded', 'mod_ep', count($result->added)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Enregistrement des décisions saisies dans la liste. Chaque ligne est revérifiée par
// ep_apply_participant_decisions() (droits, état du crédit, barème) : ce qui est lu ici n'est
// qu'une intention.
if ($action === 'decide' && data_submitted() && confirm_sesskey()) {
    $grades = optional_param_array('grade', [], PARAM_RAW_TRIMMED);
    $decisions = optional_param_array('decision', [], PARAM_ALPHA);
    $comments = optional_param_array('comment', [], PARAM_TEXT);

    $entries = [];
    foreach (array_unique(array_merge(array_keys($grades), array_keys($decisions))) as $key) {
        $key = (int) $key;
        if ($key <= 0) {
            continue;
        }
        $grade = null;
        $rawgrade = str_replace(',', '.', (string) ($grades[$key] ?? ''));
        if ($rawgrade !== '') {
            if (!is_numeric($rawgrade)) {
                // Saisie illisible : la ligne est laissée telle quelle plutôt que d'en deviner le sens.
                continue;
            }
            $grade = (float) $rawgrade;
        }
        $decision = $decisions[$key] ?? '';
        $entries[$key] = [
            'grade' => $grade,
            'decision' => in_array($decision, ['validate', 'reject'], true) ? $decision : '',
            'comment' => (string) ($comments[$key] ?? ''),
        ];
    }
    $result = ep_apply_participant_decisions($ep, $activity, $entries, $rights);
    if ($result->changed > 0) {
        \core\notification::add(
            get_string('decisionschanged', 'mod_ep', $result->changed),
            \core\output\notification::NOTIFY_WARNING
        );
    }
    redirect($baseurl, get_string('decisionssaved', 'mod_ep', $result), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('participantsof', 'mod_ep', format_string($activity->name)));
echo html_writer::link(new moodle_url('/mod/ep/credits.php', ['id' => $cm->id]), get_string('back'));

// 1. Rappel de ce qui encadre la saisie : état, période, places, responsables, barème.
$taken = ep_get_activity_taken_places($activity->id);
$teachers = ep_get_activity_teachers($activity->id);
if ($graded) {
    $gradinglabel = get_string('grading_grade', 'mod_ep') . ' (' . get_string('gradepass', 'mod_ep') . $labelsep
        . get_string('gradeoutof', 'mod_ep', (object) [
            'grade' => ep_format_ects($grading->gradepass),
            'max' => ep_format_ects($grading->grademax),
        ]) . ')';
} else {
    $gradinglabel = get_string('grading_none', 'mod_ep');
}
$summary = [
    get_string('activitystate', 'mod_ep') => get_string('activitystate_' . $activity->state, 'mod_ep'),
];
$period = ep_activity_registration_label($activity);
if ($period !== '') {
    $summary[get_string('registrationstart', 'mod_ep') . ' / ' . get_string('registrationend', 'mod_ep')] = $period;
}
$summary[get_string('places', 'mod_ep')] = empty($activity->capacity)
    ? get_string('participantscount', 'mod_ep', $taken) . ' (' . get_string('unlimitedplaces', 'mod_ep') . ')'
    : $taken . ' / ' . (int) $activity->capacity;
$summary[get_string('activityteachers', 'mod_ep')] = empty($teachers) ? '-' : implode(', ', array_map('fullname', $teachers));
$summary[get_string('grading', 'mod_ep')] = $gradinglabel;

$dl = '';
foreach ($summary as $label => $value) {
    $dl .= html_writer::tag('dt', $label, ['class' => 'col-sm-3']) . html_writer::tag('dd', s($value), ['class' => 'col-sm-9']);
}
echo html_writer::tag('dl', $dl, ['class' => 'row mt-3']);
echo html_writer::div(get_string('participants_help', 'mod_ep'), 'text-muted mb-3');

// 2. Actions sur l'EP entier.
echo html_writer::div(ep_render_actions([
    get_string('exportparticipants', 'mod_ep') => new moodle_url($baseurl, ['action' => 'export']),
    get_string('attendancesheet', 'mod_ep') => new moodle_url($baseurl, ['action' => 'sheet']),
    get_string('importgrades', 'mod_ep') => new moodle_url(
        '/mod/ep/grades_import.php',
        ['id' => $cm->id, 'activityid' => $activity->id]
    ),
    get_string('closeactivity', 'mod_ep') => $activity->state === EP_ACTIVITY_PUBLISHED
        ? new moodle_url($baseurl, ['action' => 'close']) : null,
    get_string('reopenactivity', 'mod_ep') => $activity->state === EP_ACTIVITY_CLOSED
        ? new moodle_url($baseurl, ['action' => 'reopen', 'sesskey' => sesskey()]) : null,
], 'btn btn-secondary mr-1 mb-1', ''), 'mb-3');

// 3. Liste des participants, saisissable en une fois.
echo $OUTPUT->heading(get_string('participants', 'mod_ep'), 4);
if ($graded && $rights->validatedeve) {
    echo $OUTPUT->notification(get_string('gradecorrectionnotice', 'mod_ep'), 'info');
}

if (empty($participants)) {
    echo html_writer::tag('p', get_string('participantscount', 'mod_ep', 0));
} else {
    $students = ep_get_credit_users($participants);
    // Prérequis manquants des inscrits en attente, calculés en une fois pour toute la liste.
    $pendingusers = array_map(
        fn($credit) => (int) $credit->userid,
        array_filter($participants, fn($credit) => (int) $credit->status === EP_STATUS_PENDING)
    );
    $missingbyuser = ep_get_missing_prerequisites_for_users($ep, $activity, $pendingusers);
    // Auteurs des ajouts, lus en une requête.
    $adderids = array_unique(array_filter(array_map(fn($credit) => (int) $credit->addedby, $participants)));
    $adders = $adderids ? $DB->get_records_list('user', 'id', $adderids) : [];
    $returnurl = $baseurl->out_as_local_url(false);

    $head = [
        get_string('student', 'mod_ep'),
        get_string('studyyear', 'mod_ep'),
        get_string('status', 'mod_ep'),
        get_string('prerequisites', 'mod_ep'),
        $graded
            ? get_string('grade', 'mod_ep') . ' (/ ' . ep_format_ects($grading->grademax) . ')'
            : get_string('decision', 'mod_ep'),
        get_string('validatorcomment', 'mod_ep'),
        get_string('retainedects', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ];
    $decisionoptions = [
        '' => get_string('decisionnone', 'mod_ep'),
        'validate' => get_string('decisionvalidate', 'mod_ep'),
        'reject' => get_string('decisionreject', 'mod_ep'),
    ];

    $rows = [];
    $editable = 0;
    foreach ($participants as $credit) {
        $student = $students[$credit->userid] ?? null;
        $status = (int) $credit->status;
        $pending = $status === EP_STATUS_PENDING;
        $candecide = $pending && ep_rights_can_validate($rights, $credit);
        $name = $student ? fullname($student) : '-';

        $studentcell = html_writer::span(s($name));
        if ($student && !empty($student->email)) {
            $studentcell .= html_writer::div(
                html_writer::link('mailto:' . $student->email, s($student->email)),
                'small'
            );
        }
        if (!empty($credit->addedby) && isset($adders[$credit->addedby])) {
            $studentcell .= html_writer::div(
                get_string('addedby', 'mod_ep', fullname($adders[$credit->addedby])),
                'small text-muted'
            );
        }

        $statuscell = ep_render_credit_status($status);
        if ($pending && !$candecide) {
            $statuscell .= html_writer::div(
                get_string('awaitingotherrole', 'mod_ep', ep_role_label(ep_credit_pending_role($credit))),
                'small text-muted'
            );
        }

        // Les prérequis ne comptent que pour une inscription encore à trancher.
        $prereqcell = '';
        if ($pending) {
            $missing = $missingbyuser[(int) $credit->userid] ?? [];
            if ($missing) {
                $prereqcell = html_writer::span(get_string('prereqmissing', 'mod_ep', implode(', ', $missing)), 'text-muted');
            }
        }

        // Saisie : une inscription en attente sur laquelle l'utilisateur peut statuer ; pour la
        // DEVE et un EP noté, aussi une note déjà tranchée, à corriger.
        $correctable = $graded && $rights->validatedeve
            && ($status === EP_STATUS_VALIDATED || $status === EP_STATUS_REJECTED);
        $inputcell = '-';
        if ($graded && ($candecide || $correctable)) {
            $attributes = [
                'type' => 'number', 'step' => '0.01', 'min' => 0, 'max' => ep_format_ects_input($grading->grademax),
                'name' => 'grade[' . $credit->id . ']', 'class' => 'form-control', 'style' => 'max-width:7em',
                'aria-label' => get_string('grade', 'mod_ep') . ' - ' . $name,
            ];
            if ($credit->grade !== null) {
                // Une note portée à une étape précédente d'une inscription encore en attente n'est
                // montrée qu'en filigrane : préremplie, elle déciderait de l'étape courante au
                // premier enregistrement de la liste, sans que personne l'ait voulu.
                $attributes[$correctable ? 'value' : 'placeholder'] = ep_format_ects_input($credit->grade);
            }
            $inputcell = html_writer::empty_tag('input', $attributes);
        } else if (!$graded && $candecide) {
            $inputcell = html_writer::select(
                $decisionoptions,
                'decision[' . $credit->id . ']',
                '',
                false,
                ['aria-label' => get_string('decision', 'mod_ep') . ' - ' . $name]
            );
        } else if ($graded && $credit->grade !== null) {
            $inputcell = get_string('gradeoutof', 'mod_ep', (object) [
                'grade' => ep_format_ects($credit->grade),
                'max' => ep_format_ects($grading->grademax),
            ]);
        }

        $existingcomment = trim((string) $credit->validatorcomment);
        if ($candecide || $correctable) {
            $editable++;
            $commentcell = html_writer::empty_tag('input', [
                'type' => 'text', 'name' => 'comment[' . $credit->id . ']', 'class' => 'form-control', 'maxlength' => 1000,
                'aria-label' => get_string('validatorcomment', 'mod_ep') . ' - ' . $name,
            ]);
            if ($existingcomment !== '') {
                $commentcell .= html_writer::div(s($existingcomment), 'small text-muted');
            }
        } else {
            $commentcell = $existingcomment !== '' ? s($existingcomment) : '-';
        }

        $actions = ep_render_actions([
            get_string('viewdetails', 'mod_ep') => new moodle_url(
                '/mod/ep/validate.php',
                ['id' => $cm->id, 'creditid' => $credit->id, 'returnurl' => $returnurl]
            ),
        ]) . ep_render_actions([
            get_string('withdrawparticipant', 'mod_ep') => $pending
                ? new moodle_url($baseurl, ['action' => 'withdraw', 'creditid' => $credit->id]) : null,
        ], 'btn btn-sm btn-outline-danger mr-1 mb-1', '');

        $rows[] = [
            $studentcell,
            ep_studyyear_label($credit->studyyear),
            $statuscell,
            $prereqcell,
            $inputcell,
            $commentcell,
            $status === EP_STATUS_VALIDATED ? ep_format_ects($credit->retainedects) : '-',
            $actions,
        ];
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'decide']);
    echo ep_render_paged_table($head, $rows);
    if ($editable > 0) {
        echo html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => get_string('savedecisions', 'mod_ep'), 'class' => 'btn btn-primary mb-4',
        ]);
    }
    echo html_writer::end_tag('form');
}

// 4. Ajout d'étudiants, y compris après la période d'inscription.
echo $OUTPUT->heading(get_string('addparticipants', 'mod_ep'), 4);
if (empty($candidates)) {
    echo $OUTPUT->notification(get_string('nostudentstoadd', 'mod_ep'), 'info');
} else {
    echo html_writer::div(get_string('addparticipants_help', 'mod_ep'), 'text-muted mb-2');
    $options = [];
    foreach ($candidates as $candidate) {
        $label = fullname($candidate);
        if (trim((string) $candidate->idnumber) !== '') {
            $label .= ' (' . $candidate->idnumber . ')';
        }
        $options[$candidate->id] = $label;
    }
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']);
    echo html_writer::tag('label', get_string('student', 'mod_ep'), ['for' => 'addparticipants_userids', 'class' => 'sr-only']);
    echo html_writer::select($options, 'userids[]', [], false, [
        'multiple' => 'multiple',
        'size' => min(15, max(5, count($options))),
        'id' => 'addparticipants_userids',
    ]);
    if ($rights->validatedeve) {
        echo html_writer::div(
            html_writer::checkbox(
                'force',
                1,
                false,
                get_string('addparticipantsforce', 'mod_ep'),
                ['id' => 'addparticipants_force']
            ),
            'mt-2'
        );
    }
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('addparticipants', 'mod_ep'), 'class' => 'btn btn-primary mt-2',
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
