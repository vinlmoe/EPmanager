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
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init($cm, '/mod/syllabus/view.php', $show ? ['show' => $show] : []);

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
    $done = syllabus_review($syllabus, $cm, $working, (int) $USER->id, $decision, $comment);
    redirect(
        new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]),
        $done ? get_string('reviewdone_' . $decision, 'mod_syllabus') : get_string('reviewchanged', 'mod_syllabus'),
        null,
        $done ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
    );
}

\mod_syllabus\event\course_module_viewed::create(['objectid' => $syllabus->id, 'context' => $context])->trigger();
(new completion_info($course))->set_module_viewed($cm);

echo $OUTPUT->header();

// Évaluation : ce que l'étudiant peut faire, ou l'état pour les responsables et la DEVE.
$state = syllabus_eval_state($syllabus);
$evalhtml = '';
if ($rights->canevaluate && !$rights->canedit) {
    $problem = syllabus_evaluation_problem($syllabus, $context, (int) $USER->id);
    if ($problem === null) {
        $evalhtml = html_writer::tag('p', get_string('evalinvite', 'mod_syllabus', syllabus_eval_window_label($syllabus)))
            . $OUTPUT->single_button(
                new moodle_url('/mod/syllabus/evaluate.php', ['id' => $cm->id]),
                get_string('evaluatemodule', 'mod_syllabus'),
                'get',
                ['type' => 'primary']
            );
    } else if ($problem !== 'evalnone') {
        $evalhtml = html_writer::tag('p', get_string($problem . '_student', 'mod_syllabus', syllabus_eval_window_label($syllabus)));
    }
} else if ($rights->canedit || $rights->canmanage) {
    $a = (object) ['responses' => syllabus_count_responses((int) $syllabus->id), 'eligible' => syllabus_count_eligible($context)];
    $evalhtml = html_writer::tag('p', syllabus_eval_badge($syllabus) . ' ' . s(syllabus_eval_window_label($syllabus)))
        . ($state !== SYLLABUS_EVAL_NONE ? html_writer::tag('p', get_string('responsesrate', 'mod_syllabus', $a)) : '');
    $links = [html_writer::link(
        new moodle_url('/mod/syllabus/evaluation.php', ['id' => $cm->id]),
        get_string('evaluationsettings', 'mod_syllabus'),
        ['class' => 'btn btn-secondary mr-2']
    )];
    if ($rights->canseeresults) {
        $links[] = html_writer::link(
            new moodle_url('/mod/syllabus/results.php', ['id' => $cm->id]),
            get_string('results', 'mod_syllabus'),
            ['class' => 'btn btn-secondary']
        );
    }
    $evalhtml .= html_writer::div(implode('', $links));
}
if ($evalhtml !== '') {
    echo $OUTPUT->box(html_writer::tag('h4', get_string('evaluation', 'mod_syllabus')) . $evalhtml, 'generalbox mb-3');
}

// Rédaction : version publiée, version de travail, actions.
if ($rights->canedit || $rights->canmanage) {
    $lines = [];
    $lines[] = $published
        ? get_string('publishedversion', 'mod_syllabus', (object) ['version' => $published->version,
            'date' => userdate($published->timereviewed ?: $published->timemodified, get_string('strftimedate', 'langconfig'))])
        : get_string('nopublished', 'mod_syllabus');
    if ($working) {
        $lines[] = get_string('workingversion', 'mod_syllabus', (object) ['version' => $working->version,
            'status' => syllabus_status_label($working->status)]);
        if ($working->status === SYLLABUS_STATUS_RETURNED && trim((string) $working->reviewcomment) !== '') {
            $lines[] = get_string('reviewcommentis', 'mod_syllabus', s($working->reviewcomment));
        }
    }
    $buttons = [];
    if ($rights->canedit && (!$working || $working->status !== SYLLABUS_STATUS_SUBMITTED)) {
        $buttons[] = html_writer::link(
            new moodle_url('/mod/syllabus/edit.php', ['id' => $cm->id]),
            get_string($working ? 'continueediting' : ($published ? 'proposeupdate' : 'writefiche'), 'mod_syllabus'),
            ['class' => 'btn btn-primary mr-2']
        );
    }
    if ($working && $show !== 'working') {
        $buttons[] = html_writer::link(
            new moodle_url($PAGE->url, ['show' => 'working']),
            get_string('showworking', 'mod_syllabus'),
            ['class' => 'btn btn-secondary mr-2']
        );
    } else if ($show === 'working') {
        $buttons[] = html_writer::link(
            new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]),
            get_string('showpublished', 'mod_syllabus'),
            ['class' => 'btn btn-secondary mr-2']
        );
    }
    echo $OUTPUT->box(html_writer::tag('h4', get_string('fichestate', 'mod_syllabus'))
        . html_writer::alist(array_map('s', $lines)) . html_writer::div(implode('', $buttons)), 'generalbox mb-3');

    if ($show === 'working' && $working && $working->status === SYLLABUS_STATUS_SUBMITTED && $rights->canmanage) {
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . html_writer::tag('label', get_string('reviewcomment', 'mod_syllabus'), ['for' => 'reviewcomment'])
            . html_writer::tag('textarea', '', ['name' => 'reviewcomment', 'id' => 'reviewcomment', 'rows' => 3,
                'class' => 'form-control mb-2']);
        foreach (['publish' => 'btn-primary', 'return' => 'btn-secondary', 'reject' => 'btn-danger'] as $value => $class) {
            $form .= html_writer::tag('button', get_string('decide_' . $value, 'mod_syllabus'), ['type' => 'submit',
                'name' => 'decision', 'value' => $value, 'class' => 'btn ' . $class . ' mr-2']);
        }
        echo $OUTPUT->box(html_writer::tag('h4', get_string('reviewheading', 'mod_syllabus')) . $form
            . html_writer::end_tag('form'), 'generalbox mb-3');
    }
}

// La fiche.
$shown = ($show === 'working' && $working) ? $working : $published;
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
echo fiche::render($syllabus, $shown);

echo $OUTPUT->footer();
