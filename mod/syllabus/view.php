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
 * Fiche du module, état de sa rédaction et de son évaluation ; décision de la DEVE sur une
 * version soumise.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

use mod_syllabus\output\fiche;

$id = required_param('id', PARAM_INT);
$show = optional_param('show', '', PARAM_ALPHA);
// Liste d'où vient la DEVE (« Fiches à relire ») : elle y revient après sa décision.
$returnto = optional_param('returnto', '', PARAM_LOCALURL);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init(
    $cm,
    '/mod/syllabus/view.php',
    array_filter(['show' => $show, 'returnto' => $returnto])
);

$published = syllabus_get_published((int) $syllabus->id);
$working = ($rights->canedit || $rights->canmanage) ? syllabus_get_working((int) $syllabus->id) : null;

// Décision de la DEVE sur la version soumise.
$decision = optional_param('decision', '', PARAM_ALPHA);
if ($decision !== '' && data_submitted() && confirm_sesskey()) {
    require_capability('mod/syllabus:manage', $context);
    $comment = trim(optional_param('reviewcomment', '', PARAM_TEXT));
    if (!in_array($decision, ['publish', 'return', 'reject'], true) || !$working) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    if ($decision !== 'publish' && $comment === '') {
        redirect(
            new moodle_url($PAGE->url, ['show' => 'working']),
            get_string('errorcommentrequired', 'mod_syllabus'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    // Date de la commission de validation (champ date du navigateur, AAAA-MM-JJ) : exigée pour
    // publier une fiche qui l'impose (EP académique), facultative sinon.
    $commission = 0;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', optional_param('commissiondate', '', PARAM_RAW_TRIMMED), $m)) {
        $commission = make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], 12);
    }
    if ($decision === 'publish' && !$commission && syllabus_commission_required($syllabus)) {
        redirect(
            new moodle_url($PAGE->url, ['show' => 'working'], 'review'),
            get_string('errorcommissionrequired', 'mod_syllabus'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $done = syllabus_review(
        $syllabus,
        $cm,
        $working,
        (int) $USER->id,
        $decision,
        $comment,
        $decision === 'publish' ? $commission : 0
    );
    redirect(
        $returnto !== '' ? new moodle_url($returnto) : new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]),
        $done ? get_string('reviewdone_' . $decision, 'mod_syllabus') : get_string('reviewchanged', 'mod_syllabus'),
        null,
        $done ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
    );
}

\mod_syllabus\event\course_module_viewed::create(['objectid' => $syllabus->id, 'context' => $context])->trigger();
(new completion_info($course))->set_module_viewed($cm);

echo $OUTPUT->header();
// Fiche d'un EP : retour à l'EP dans son catalogue.
if (!empty($syllabus->component) && ($ownerurl = syllabus_owner_url($syllabus))) {
    echo html_writer::div(html_writer::link($ownerurl, get_string('ownerlink', 'mod_syllabus')), 'mb-2');
}
$state = syllabus_eval_state($syllabus);
$shown = ($show === 'working' && $working) ? $working : $published;
$reviewing = $show === 'working' && $working && $working->status === SYLLABUS_STATUS_SUBMITTED && $rights->canmanage;

// Étudiant : l'invitation à évaluer, en tête et sous la fiche.
$invite = '';
if ($rights->canevaluate && !$rights->canedit) {
    $problem = syllabus_evaluation_problem($syllabus, $context, (int) $USER->id);
    if ($problem === null) {
        $window = syllabus_eval_window_label($syllabus);
        $invite = $OUTPUT->box(html_writer::tag('p', get_string('evalinvite', 'mod_syllabus', $window))
            . $OUTPUT->single_button(
                new moodle_url('/mod/syllabus/evaluate.php', ['id' => $cm->id]),
                get_string('evaluatemodule', 'mod_syllabus'),
                'get',
                ['type' => 'primary']
            ), 'generalbox mb-3');
    } else if ($problem !== 'evalnone') {
        $invite = $OUTPUT->box(html_writer::tag('p', get_string(
            $problem . '_student',
            'mod_syllabus',
            syllabus_eval_window_label($syllabus)
        )), 'generalbox mb-3');
    }
    echo $invite;
}

// Responsables et DEVE : où en est le module, étape par étape, avec le lien de chaque étape.
if ($rights->canedit || $rights->canmanage) {
    $steps = [];
    // Le glyphe est décoratif ; l'état est dit en toutes lettres aux lecteurs d'écran.
    $done = fn(bool $ok) => html_writer::span(
        $ok ? '✓' : '○',
        $ok ? 'text-success mr-2' : 'text-muted mr-2',
        ['aria-hidden' => 'true']
    ) . html_writer::span(get_string($ok ? 'done' : 'todo', 'completion'), 'sr-only');
    // 1. La fiche.
    $text = $published
        ? get_string('publishedversion', 'mod_syllabus', (object) ['version' => $published->version,
            'date' => userdate($published->timereviewed ?: $published->timemodified, get_string('strftimedate', 'langconfig'))])
        : get_string('nopublished', 'mod_syllabus');
    if ($working) {
        $text .= ' ' . get_string('workingversion', 'mod_syllabus', (object) ['version' => $working->version,
            'status' => syllabus_status_label($working->status)]);
    }
    $actions = [];
    if ($rights->canedit && (!$working || $working->status !== SYLLABUS_STATUS_SUBMITTED)) {
        $actions[] = html_writer::link(
            new moodle_url('/mod/syllabus/edit.php', ['id' => $cm->id]),
            get_string($working ? 'continueediting' : ($published ? 'proposeupdate' : 'writefiche'), 'mod_syllabus'),
            ['class' => 'btn btn-sm ' . ($published && !$working ? 'btn-secondary' : 'btn-primary') . ' ml-2']
        );
    }
    if ($working && $show !== 'working') {
        $actions[] = html_writer::link(new moodle_url($PAGE->url, ['show' => 'working']), get_string(
            $working->status === SYLLABUS_STATUS_SUBMITTED && $rights->canmanage ? 'reviewnow' : 'showworking',
            'mod_syllabus'
        ), ['class' => 'btn btn-sm btn-secondary ml-2']);
    } else if ($show === 'working' && $published) {
        $actions[] = html_writer::link(
            new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]),
            get_string('showpublished', 'mod_syllabus'),
            ['class' => 'btn btn-sm btn-secondary ml-2']
        );
    }
    $line = $done((bool) $published) . html_writer::tag('strong', get_string('fiche', 'mod_syllabus')) . ' : ' . s($text)
        . implode('', $actions);
    if ($working && $working->status === SYLLABUS_STATUS_RETURNED && trim((string) $working->reviewcomment) !== '') {
        $line .= html_writer::div(
            get_string('reviewcommentis', 'mod_syllabus', s($working->reviewcomment)),
            'alert alert-warning mt-1 mb-0 py-1'
        );
    }
    $steps[] = $line;
    // 2. Les compétences.
    $competencies = count(syllabus_get_module_competencies($syllabus));
    $steps[] = $done($competencies > 0) . html_writer::tag('strong', get_string('competencies', 'mod_syllabus')) . ' : '
        . get_string($competencies ? 'competenciesn' : 'nocompetencies', 'mod_syllabus', $competencies)
        . html_writer::link(new moodle_url('/mod/syllabus/competencies.php', ['id' => $cm->id]), get_string(
            $competencies ? 'change' : 'linkcompetenciesshort',
            'mod_syllabus'
        ), ['class' => 'btn btn-sm btn-secondary ml-2']);
    // 3. L'évaluation : questions de l'enseignant, fenêtre, réponses.
    $questions = count(syllabus_get_module_questions((int) $syllabus->id, 'teacher'));
    $evaltext = ($syllabus->evalyear !== '' ? s($syllabus->evalyear) . ' : ' : '') . syllabus_eval_badge($syllabus) . ' '
        . s(syllabus_eval_window_label($syllabus)) . ' · ' . get_string('teacherquestionsn', 'mod_syllabus', $questions);
    $pastyears = count(syllabus_get_archives((int) $syllabus->id));
    if ($pastyears) {
        $evaltext .= ' · ' . html_writer::link(
            new moodle_url('/mod/syllabus/results.php', ['id' => $cm->id]),
            get_string('pastyearsn', 'mod_syllabus', $pastyears)
        );
    }
    if ($state !== SYLLABUS_EVAL_NONE) {
        $evaltext .= ' · ' . get_string('responsesrate', 'mod_syllabus', (object) [
            'responses' => syllabus_count_responses((int) $syllabus->id), 'eligible' => syllabus_count_eligible($context)]);
    }
    $evallinks = html_writer::link(new moodle_url('/mod/syllabus/evaluation.php', ['id' => $cm->id]), get_string(
        'evaluationsettings',
        'mod_syllabus'
    ), ['class' => 'btn btn-sm btn-secondary ml-2']);
    if ($rights->canseeresults) {
        $evallinks .= html_writer::link(new moodle_url('/mod/syllabus/results.php', ['id' => $cm->id]), get_string(
            'results',
            'mod_syllabus'
        ), ['class' => 'btn btn-sm btn-secondary ml-2']);
    }
    $steps[] = $done($state !== SYLLABUS_EVAL_NONE) . html_writer::tag('strong', get_string('evaluation', 'mod_syllabus'))
        . ' : ' . $evaltext . $evallinks;
    // Caractéristiques manquantes : elles comptent pour le livret, la frise et la matrice.
    $missing = [];
    if ((float) $syllabus->ects <= 0) {
        $missing[] = get_string('ects', 'mod_syllabus');
    }
    if (trim((string) $syllabus->semester) === '') {
        $missing[] = get_string('semester', 'mod_syllabus');
    }
    if (!(int) $syllabus->studyyear) {
        $missing[] = get_string('studyyear', 'mod_syllabus');
    }
    // Fiche d'un EP : ses caractéristiques sont celles de l'EP, tenues dans son catalogue.
    if ($missing && empty($syllabus->component) && has_capability('moodle/course:manageactivities', $context)) {
        $steps[] = $done(false) . get_string('missingcharacteristics', 'mod_syllabus', implode(', ', $missing))
            . html_writer::link(new moodle_url('/course/modedit.php', ['update' => $cm->id]), get_string(
                'settings'
            ), ['class' => 'btn btn-sm btn-secondary ml-2']);
    }
    $list = html_writer::alist($steps, ['class' => 'list-unstyled mb-0 syllabus-steps']);
    if ($reviewing) {
        $list .= html_writer::div(html_writer::link(
            '#review',
            get_string('reviewgoto', 'mod_syllabus'),
            ['class' => 'btn btn-primary']
        ), 'mt-2');
    }
    echo $OUTPUT->box(html_writer::tag('h4', get_string('modulestate', 'mod_syllabus')) . $list, 'generalbox mb-3');
}

// La fiche (et, à la relecture, les rubriques modifiées depuis la version publiée).
if ($shown) {
    echo html_writer::div(
        html_writer::link(new moodle_url('/mod/syllabus/pdf.php', ['id' => $cm->id,
        'versionid' => $shown->id]), get_string('downloadpdf', 'mod_syllabus'), ['class' => 'btn btn-outline-secondary']),
        'mb-3'
    );
}
if ($show === 'working' && $working) {
    echo $OUTPUT->notification(get_string('workingnotice', 'mod_syllabus', syllabus_status_label($working->status)), 'info');
}
$compare = ($show === 'working' && $working && $published) ? $published : null;
echo fiche::render($syllabus, $shown, $compare, false);

// Décision de la DEVE, sous la fiche qu'elle vient de lire.
if ($reviewing) {
    $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
        . html_writer::tag('label', get_string('reviewcomment', 'mod_syllabus'), ['for' => 'reviewcomment'])
        . html_writer::tag('textarea', '', ['name' => 'reviewcomment', 'id' => 'reviewcomment', 'rows' => 3,
            'class' => 'form-control mb-2', 'required' => 'required'])
        . html_writer::div(
            html_writer::tag('label', get_string(syllabus_commission_required($syllabus) ? 'commissiondaterequired'
                : 'commissiondate', 'mod_syllabus'), ['for' => 'commissiondate', 'class' => 'mr-2'])
            . html_writer::empty_tag('input', ['type' => 'date', 'name' => 'commissiondate', 'id' => 'commissiondate',
                'class' => 'form-control', 'value' => date('Y-m-d')]),
            'form-inline mb-2'
        );
    foreach (['publish' => 'btn-primary', 'return' => 'btn-secondary', 'reject' => 'btn-danger'] as $value => $class) {
        // Publier n'exige pas de commentaire : le navigateur ne le réclame que pour renvoyer ou refuser.
        $attributes = ['type' => 'submit', 'name' => 'decision', 'value' => $value, 'class' => 'btn ' . $class . ' mr-2'];
        if ($value === 'publish') {
            $attributes['formnovalidate'] = 'formnovalidate';
        }
        $form .= html_writer::tag('button', get_string('decide_' . $value, 'mod_syllabus'), $attributes);
    }
    $form .= html_writer::tag('p', get_string('decisionhelp', 'mod_syllabus'), ['class' => 'small text-muted mt-2 mb-0']);
    echo html_writer::div($OUTPUT->box(html_writer::tag('h4', get_string('reviewheading', 'mod_syllabus')) . $form
        . html_writer::end_tag('form'), 'generalbox mt-3'), '', ['id' => 'review']);
}
echo $invite;

echo $OUTPUT->footer();
