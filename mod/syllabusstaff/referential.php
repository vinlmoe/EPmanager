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
 * Référentiel commun à tous les modules du site, tenu par la DEVE : réglages, types de module,
 * questions d'évaluation communes (tous les modules, ou un type de module).
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$questionid = optional_param('questionid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init($cm, '/mod/syllabusstaff/referential.php');
require_capability('mod/syllabusstaff:manageall', $context);
$baseurl = $PAGE->url;

// Réglages.
if ($action === 'savesettings' && confirm_sesskey()) {
    syllabus_save_settings((object) [
        'reviewrequired' => optional_param('reviewrequired', 0, PARAM_BOOL),
        'resultsthreshold' => optional_param('resultsthreshold', 3, PARAM_INT),
        'teacherresults' => optional_param('teacherresults', 'afterclose', PARAM_ALPHA),
    ]);
    redirect($baseurl, get_string('settingssaved', 'mod_syllabusstaff'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Types de module.
if ($action === 'savetype' && confirm_sesskey()) {
    $name = trim(optional_param('typename', '', PARAM_TEXT));
    if ($name !== '') {
        syllabus_save_type($name, optional_param('typeid', 0, PARAM_INT));
    }
    redirect($baseurl);
}
if ($action === 'deletetype' && confirm_sesskey()) {
    $deleted = syllabus_delete_type(required_param('typeid', PARAM_INT));
    redirect(
        $baseurl,
        get_string($deleted ? 'typedeleted' : 'typeinuse', 'mod_syllabusstaff'),
        null,
        $deleted ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
    );
}

// Questions communes.
if (in_array($action, ['delete', 'up', 'down'], true) && $questionid && confirm_sesskey()) {
    if ($action === 'delete') {
        syllabus_delete_question($questionid, 0);
    } else {
        syllabus_move_question($questionid, 0, $action === 'up' ? -1 : 1);
    }
    redirect($baseurl);
}
$types = array_map(fn($type) => format_string($type->name), syllabus_get_types());
$questionform = new \mod_syllabus\form\question_form(new moodle_url($baseurl, ['action' => 'editquestion',
    'questionid' => $questionid]), ['common' => true, 'types' => $types]);
if ($questionform->is_cancelled()) {
    redirect($baseurl);
} else if ($data = $questionform->get_data()) {
    $data->id = (int) $data->questionid;
    $data->syllabusid = 0;
    syllabus_save_question($data);
    redirect($baseurl, get_string('questionsaved', 'mod_syllabus'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$defaults = ['id' => $cm->id, 'questionid' => 0];
if ($action === 'editquestion' && $questionid) {
    $question = $DB->get_record('syllabus_question', ['id' => $questionid, 'syllabusid' => 0], '*', MUST_EXIST);
    $defaults = ['id' => $cm->id, 'questionid' => $question->id, 'origin' => $question->origin, 'typeid' => $question->typeid,
        'text' => $question->text, 'kind' => $question->kind, 'options' => $question->options,
        'required' => $question->required, 'enabled' => $question->enabled];
}
$questionform->set_data($defaults);

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'referential', $canmanageall);
echo $OUTPUT->heading(get_string('referential', 'mod_syllabusstaff'), 3);
echo $OUTPUT->notification(get_string('referential_help', 'mod_syllabusstaff'), 'info');

// 1. Réglages.
$hidden = fn($name, $value) => html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
$form = html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)])
    . $hidden('sesskey', sesskey()) . $hidden('action', 'savesettings')
    . html_writer::div(html_writer::checkbox(
        'reviewrequired',
        1,
        (bool) syllabus_setting('reviewrequired'),
        ' ' . get_string('reviewrequired', 'mod_syllabusstaff')
    ), 'mb-2')
    . html_writer::div(html_writer::label(
        get_string('resultsthreshold', 'mod_syllabusstaff'),
        'resultsthreshold',
        true,
        ['class' => 'mr-2']
    ) . html_writer::empty_tag('input', ['type' => 'number', 'min' => 1, 'max' => 50,
        'name' => 'resultsthreshold', 'id' => 'resultsthreshold', 'value' => syllabus_setting('resultsthreshold'),
        'class' => 'form-control d-inline-block', 'style' => 'width:6rem']), 'mb-2')
    . html_writer::div(html_writer::label(
        get_string('teacherresults', 'mod_syllabusstaff'),
        'teacherresults',
        true,
        ['class' => 'mr-2']
    ) . html_writer::select(
        ['afterclose' => get_string('teacherresults_afterclose', 'mod_syllabusstaff'),
            'always' => get_string('teacherresults_always', 'mod_syllabusstaff')],
        'teacherresults',
        syllabus_setting('teacherresults'),
        false,
        ['id' => 'teacherresults', 'class' => 'custom-select']
    ), 'mb-2')
    . html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary'])
    . html_writer::end_tag('form');
echo $OUTPUT->box(html_writer::tag('h4', get_string('settings', 'mod_syllabusstaff')) . $form, 'generalbox mb-3');

// 2. Types de module.
$html = html_writer::tag('p', get_string('types_help', 'mod_syllabusstaff'), ['class' => 'text-muted']);
$table = new html_table();
$table->head = [get_string('name'), get_string('modulescount', 'mod_syllabusstaff'), get_string('actions')];
foreach (syllabus_get_types() as $type) {
    $rename = html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'form-inline'])
        . $hidden('sesskey', sesskey()) . $hidden('action', 'savetype') . $hidden('typeid', $type->id)
        . html_writer::empty_tag('input', ['type' => 'text', 'name' => 'typename', 'value' => $type->name,
            'class' => 'form-control mr-2', 'aria-label' => get_string('name')])
        . html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('rename', 'syllabusstaff'),
            'class' => 'btn btn-secondary'])
        . html_writer::end_tag('form');
    $delete = html_writer::link(new moodle_url($baseurl, ['action' => 'deletetype', 'typeid' => $type->id,
        'sesskey' => sesskey()]), get_string('delete'), ['class' => 'text-danger']);
    $table->data[] = [$rename, $DB->count_records('syllabus', ['typeid' => $type->id]), $delete];
}
$html .= $table->data ? html_writer::table($table) : '';
$html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'form-inline'])
    . $hidden('sesskey', sesskey()) . $hidden('action', 'savetype')
    . html_writer::empty_tag('input', ['type' => 'text', 'name' => 'typename', 'class' => 'form-control mr-2',
        'placeholder' => get_string('newtype', 'mod_syllabusstaff'), 'aria-label' => get_string('newtype', 'mod_syllabusstaff')])
    . html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('add'), 'class' => 'btn btn-primary'])
    . html_writer::end_tag('form');
echo $OUTPUT->box(html_writer::tag('h4', get_string('moduletypes', 'mod_syllabusstaff')) . $html, 'generalbox mb-3');

// 3. Questions communes.
$actions = function ($question, $index, $count) use ($baseurl) {
    $links = [html_writer::link(
        new moodle_url($baseurl, ['action' => 'editquestion', 'questionid' => $question->id]),
        get_string('edit')
    )];
    foreach (['up' => 'moveup', 'down' => 'movedown'] as $move => $label) {
        $links[] = html_writer::link(new moodle_url($baseurl, ['action' => $move, 'questionid' => $question->id,
            'sesskey' => sesskey()]), get_string($label, 'syllabusstaff'));
    }
    $links[] = html_writer::link(new moodle_url($baseurl, ['action' => 'delete', 'questionid' => $question->id,
        'sesskey' => sesskey()]), get_string('delete'), ['class' => 'text-danger']);
    return implode(' · ', $links);
};
echo $OUTPUT->box_start('generalbox mb-3');
echo html_writer::tag('h4', get_string('commonquestions', 'mod_syllabusstaff'));
echo html_writer::tag('p', get_string('commonquestions_help', 'mod_syllabusstaff'), ['class' => 'text-muted']);
echo syllabus_render_questions(syllabus_get_common_questions(), $actions, true);
echo html_writer::tag('h5', get_string(
    $action === 'editquestion' && $questionid ? 'editquestion' : 'addquestion',
    'mod_syllabus'
));
$questionform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
