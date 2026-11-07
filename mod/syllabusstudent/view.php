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
 * Mes modules : les modules des cours où l'étudiant est inscrit, leur fiche, et les évaluations
 * qu'il a à faire.
 *
 * @package   mod_syllabusstudent
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstudent/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('syllabusstudent', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
$course = get_course($cm->course);
$instance = $DB->get_record('syllabusstudent', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_capability('mod/syllabusstudent:view', $context);
$PAGE->set_url('/mod/syllabusstudent/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
\mod_syllabusstudent\event\course_module_viewed::create(['objectid' => $instance->id, 'context' => $context])->trigger();
(new completion_info($course))->set_module_viewed($cm);

$modules = syllabusstudent_get_modules((int) $USER->id, (int) $instance->categoryid);
$todo = array_filter($modules, fn($row) => $row->evalproblem === null);
$teachers = syllabus_get_teachers_bulk(array_keys($modules));
$types = syllabus_get_types();
$format = get_string('strftimedatetimeshort', 'langconfig');

echo $OUTPUT->header();

// Évaluations à faire, la plus urgente en tête.
echo $OUTPUT->heading(get_string('todo', 'mod_syllabusstudent', count($todo)), 3);
if ($todo) {
    uasort($todo, fn($a, $b) => [(int) ($a->evalclose ?: PHP_INT_MAX), $a->name] <=> [(int) ($b->evalclose ?: PHP_INT_MAX),
        $b->name]);
    $items = [];
    foreach ($todo as $row) {
        $deadline = $row->evalclose ? get_string('before', 'mod_syllabusstudent', userdate($row->evalclose, $format))
            : get_string('nodeadline', 'mod_syllabusstudent');
        $items[] = html_writer::link(
            new moodle_url('/mod/syllabus/evaluate.php', ['id' => $row->cmid]),
            get_string('evaluate', 'mod_syllabusstudent', format_string($row->name)),
            ['class' => 'btn btn-primary mr-2 mb-1']
        )
            . html_writer::span($deadline, 'text-muted');
    }
    echo html_writer::alist($items, ['class' => 'list-unstyled']);
} else {
    echo html_writer::tag('p', get_string('nothingtodo', 'mod_syllabusstudent'), ['class' => 'text-muted']);
}

// Tous les modules.
echo $OUTPUT->heading(get_string('mymodules', 'mod_syllabusstudent', count($modules)), 3);
if (!$modules) {
    echo $OUTPUT->notification(get_string('nomodules', 'mod_syllabusstudent'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable syllabusstudent-modules';
    $table->head = [get_string('module', 'mod_syllabus'), get_string('moduletype', 'mod_syllabus'), get_string(
        'ects',
        'mod_syllabus'
    ), get_string('semester', 'mod_syllabus'), get_string('teachers', 'mod_syllabus'),
        get_string('evaluation', 'mod_syllabus')];
    foreach ($modules as $row) {
        $status = match ($row->evalproblem) {
            null => html_writer::link(
                new moodle_url('/mod/syllabus/evaluate.php', ['id' => $row->cmid]),
                get_string('evaluatenow', 'mod_syllabusstudent'), ['class' => 'btn btn-sm btn-primary']
            ),
            'evaldone' => html_writer::span(get_string('evaluated', 'mod_syllabusstudent'), 'badge badge-success'),
            'evalupcoming' => get_string('opens', 'mod_syllabusstudent', userdate($row->evalopen, $format)),
            'evalclosed' => html_writer::span(get_string('evalstate_closed', 'mod_syllabus'), 'badge badge-dark'),
            default => '-',
        };
        $table->data[] = [
            html_writer::link(new moodle_url('/mod/syllabus/view.php', ['id' => $row->cmid]), format_string($row->name))
                . html_writer::div(
                    format_string($row->coursename) . ($row->code !== '' ? ' · ' . s($row->code) : ''),
                    'small text-muted'
                ),
            $row->typeid && isset($types[$row->typeid]) ? format_string($types[$row->typeid]->name) : '-',
            syllabus_format_number($row->ects),
            s($row->semester) ?: '-',
            s(implode(', ', array_map('fullname', $teachers[(int) $row->id]))) ?: '-',
            $status,
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
