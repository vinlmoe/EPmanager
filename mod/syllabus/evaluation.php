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
 * Évaluation du module, côté responsables et DEVE : fenêtre d'ouverture, questions communes qui
 * s'appliqueront (posées par la DEVE) et questions de l'enseignant. À l'ouverture, les questions
 * sont figées.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$questionid = optional_param('questionid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init($cm, '/mod/syllabus/evaluation.php');
if (!$rights->canedit && !$rights->canmanage) {
    throw new required_capability_exception($context, 'mod/syllabus:edit', 'nopermissions', '');
}
$baseurl = $PAGE->url;

// Une évaluation ouverte fige ses questions dès la première page qui la concerne.
if (syllabus_eval_state($syllabus) !== SYLLABUS_EVAL_NONE && syllabus_eval_state($syllabus) !== SYLLABUS_EVAL_UPCOMING) {
    syllabus_freeze_questions($syllabus);
}
$frozen = !empty($syllabus->evalfrozen);

// Fenêtre.
$windowform = new \mod_syllabus\form\window_form($baseurl);
$windowform->set_data(['id' => $cm->id, 'evalopen' => $syllabus->evalopen, 'evalclose' => $syllabus->evalclose]);
if ($action === 'savewindow' && ($data = $windowform->get_data())) {
    syllabus_set_window($syllabus, (int) ($data->evalopen ?? 0), (int) ($data->evalclose ?? 0));
    redirect($baseurl, get_string('windowsaved', 'mod_syllabus'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Questions de l'enseignant.
if (in_array($action, ['delete', 'up', 'down'], true) && $questionid && confirm_sesskey()) {
    if ($action === 'delete') {
        syllabus_delete_question($questionid, (int) $syllabus->id);
    } else {
        syllabus_move_question($questionid, (int) $syllabus->id, $action === 'up' ? -1 : 1);
    }
    redirect($baseurl);
}
$questionform = null;
if (!$frozen) {
    $formurl = new moodle_url($baseurl, ['action' => 'editquestion', 'questionid' => $questionid]);
    $questionform = new \mod_syllabus\form\question_form($formurl);
    if ($questionform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $questionform->get_data()) {
        $data->id = (int) $data->questionid;
        $data->syllabusid = (int) $syllabus->id;
        syllabus_save_question($data);
        redirect($baseurl, get_string('questionsaved', 'mod_syllabus'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $defaults = ['id' => $cm->id, 'questionid' => 0];
    if ($action === 'editquestion' && $questionid) {
        $question = $DB->get_record('syllabus_question', ['id' => $questionid, 'syllabusid' => $syllabus->id], '*', MUST_EXIST);
        $defaults = ['id' => $cm->id, 'questionid' => $question->id, 'text' => $question->text, 'kind' => $question->kind,
            'options' => $question->options, 'required' => $question->required];
    }
    $questionform->set_data($defaults);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('evaluation', 'mod_syllabus'));
$state = syllabus_eval_state($syllabus);
$resultslink = $rights->canseeresults && $state !== SYLLABUS_EVAL_NONE ? ' ' . html_writer::link(
    new moodle_url('/mod/syllabus/results.php', ['id' => $cm->id]),
    get_string('results', 'mod_syllabus'),
    ['class' => 'btn btn-sm btn-secondary ml-2']
) : '';
echo html_writer::tag('p', syllabus_eval_badge($syllabus) . ' ' . s(syllabus_eval_window_label($syllabus)) . $resultslink);
echo html_writer::tag('p', get_string('evaluation_help', 'mod_syllabus'));
// Un recueil par année universitaire : le rappeler, avec l'année en cours du module.
echo html_writer::tag(
    'p',
    get_string('annualnotice', 'mod_syllabus', (object) ['year' => $syllabus->evalyear !== ''
    ? $syllabus->evalyear : syllabus_academic_year(time()), 'past' => count(syllabus_get_archives((int) $syllabus->id))]),
    ['class' => 'small text-muted']
);

echo $OUTPUT->heading(get_string('evalwindowheading', 'mod_syllabus'), 4);
$windowform->display();

if ($frozen) {
    // Le message suit l'état réel : ouverte, terminée, ou repoussée après une première ouverture.
    $notice = match ($state) {
        SYLLABUS_EVAL_CLOSED => 'frozennotice_closed',
        SYLLABUS_EVAL_OPEN => 'frozennotice',
        default => 'frozennotice_other',
    };
    echo $OUTPUT->notification(get_string($notice, 'mod_syllabus'), 'info');
    echo $OUTPUT->heading(get_string('evalquestions', 'mod_syllabus'), 4);
    echo syllabus_render_questions(syllabus_get_module_questions((int) $syllabus->id));
} else {
    echo $OUTPUT->heading(get_string('part_common', 'mod_syllabus'), 4);
    echo html_writer::tag('p', get_string('commonquestions_help', 'mod_syllabus'), ['class' => 'text-muted']);
    echo syllabus_render_questions(syllabus_applicable_common_questions($syllabus));

    echo $OUTPUT->heading(get_string('part_teacher', 'mod_syllabus'), 4);
    $actions = function ($question, $index, $count) use ($baseurl, $OUTPUT) {
        $links = [];
        $links[] = html_writer::link(
            new moodle_url($baseurl, ['action' => 'editquestion', 'questionid' => $question->id], 'questionform'),
            get_string('edit')
        );
        if ($index > 0) {
            $links[] = html_writer::link(new moodle_url($baseurl, ['action' => 'up', 'questionid' => $question->id,
                'sesskey' => sesskey()]), get_string('moveup', 'mod_syllabus'));
        }
        if ($index < $count - 1) {
            $links[] = html_writer::link(new moodle_url($baseurl, ['action' => 'down', 'questionid' => $question->id,
                'sesskey' => sesskey()]), get_string('movedown', 'mod_syllabus'));
        }
        $links[] = $OUTPUT->action_link(new moodle_url($baseurl, ['action' => 'delete', 'questionid' => $question->id,
            'sesskey' => sesskey()]), get_string('delete'), new confirm_action(get_string(
                'confirmdeletequestion',
                'mod_syllabus',
                format_string($question->text)
            )), ['class' => 'text-danger']);
        return implode(' · ', $links);
    };
    echo syllabus_render_questions(syllabus_get_module_questions((int) $syllabus->id, 'teacher'), $actions);
    echo $OUTPUT->heading(get_string(
        $action === 'editquestion' && $questionid ? 'editquestion' : 'addquestion',
        'mod_syllabus'
    ), 5, '', 'questionform');
    $questionform->display();
}
echo $OUTPUT->footer();
