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
 * Évaluation du module par un étudiant, pendant la fenêtre d'évaluation. Réponse anonyme.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

$id = required_param('id', PARAM_INT);
// Page d'où vient l'étudiant (« Mes modules ») : il y revient après l'envoi pour évaluer le suivant.
$returnto = optional_param('returnto', '', PARAM_LOCALURL);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init(
    $cm,
    '/mod/syllabus/evaluate.php',
    $returnto !== '' ? ['returnto' => $returnto] : []
);
$viewurl = new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]);
$backurl = $returnto !== '' ? new moodle_url($returnto) : $viewurl;

$problem = syllabus_evaluation_problem($syllabus, $context, (int) $USER->id);
if ($problem !== null) {
    redirect(
        $viewurl,
        get_string($problem . '_student', 'mod_syllabus', syllabus_eval_window_label($syllabus)),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}
syllabus_freeze_questions($syllabus);
$questions = array_values(syllabus_get_module_questions((int) $syllabus->id));
if (!$questions) {
    redirect($viewurl, get_string('noquestions', 'mod_syllabus'), null, \core\output\notification::NOTIFY_INFO);
}

$mform = new \mod_syllabus\form\evaluate_form($PAGE->url, ['questions' => $questions]);
$mform->set_data(['id' => $cm->id]);
if ($mform->is_cancelled()) {
    redirect($backurl);
} else if ($data = $mform->get_data()) {
    $answers = [];
    foreach ($questions as $question) {
        $name = 'q' . $question->id;
        if (isset($data->$name)) {
            $answers[$question->id] = \mod_syllabus\form\evaluate_form::answer_value($question, $data->$name);
        }
    }
    $errors = syllabus_submit_response($syllabus, $context, (int) $USER->id, $answers);
    if (!$errors) {
        redirect($backurl, get_string('evalthanks', 'mod_syllabus'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    \core\notification::error(get_string('evalerrors', 'mod_syllabus'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('evaluatemodule', 'mod_syllabus'));
echo html_writer::tag('p', s(syllabus_eval_window_label($syllabus)));
$mform->display();
echo $OUTPUT->footer();
