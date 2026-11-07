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
 * Compétences visées par le module : liens au référentiel de compétences de Moodle, avec le
 * niveau de contribution du module.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

$id = required_param('id', PARAM_INT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init(
    $cm,
    '/mod/syllabus/competencies.php',
    $frameworkid ? ['frameworkid' => $frameworkid] : []
);

if (!syllabus_competencies_enabled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('competenciesdisabled', 'mod_syllabus'), 'info');
    echo $OUTPUT->footer();
    exit;
}
$canlink = syllabus_can_link_competencies($rights, (int) $course->id);
$frameworks = syllabus_get_frameworks();
if ($frameworkid && !isset($frameworks[$frameworkid])) {
    $frameworkid = 0;
}

if ($canlink && $frameworkid && optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    $result = syllabus_set_module_competencies($syllabus, $cm, $frameworkid, optional_param_array('levels', [], PARAM_INT));
    redirect(
        $PAGE->url,
        get_string('competenciessaved', 'mod_syllabus', $result),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$linked = syllabus_get_module_competencies($syllabus, (int) $cm->id);
$levels = syllabus_competency_levels();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('competencies', 'mod_syllabus'));
echo html_writer::tag('p', get_string('competencies_help', 'mod_syllabus'));

// Compétences visées.
echo $OUTPUT->heading(get_string('targetedcompetencies', 'mod_syllabus', count($linked)), 4);
echo syllabus_render_competencies($linked);
$others = array_diff_key(syllabus_get_course_competencies((int) $course->id), $linked);
if ($others) {
    echo html_writer::tag('p', get_string('coursecompetenciesonly', 'mod_syllabus') . ' '
        . s(implode(', ', array_map(fn($c) => $c->shortname, $others))), ['class' => 'small text-muted']);
}

// Liaison, un référentiel à la fois.
if (!$canlink) {
    if ($rights->canedit || $rights->canmanage) {
        echo $OUTPUT->notification(get_string('competenciesnolink', 'mod_syllabus'), 'info');
    }
} else if (!$frameworks) {
    echo $OUTPUT->notification(get_string('noframeworks', 'mod_syllabus'), 'info');
} else {
    echo $OUTPUT->heading(get_string('linkcompetencies', 'mod_syllabus'), 4);
    $options = [0 => get_string('chooseframework', 'mod_syllabus')];
    foreach ($frameworks as $framework) {
        $options[$framework->id] = format_string($framework->shortname);
    }
    echo $OUTPUT->single_select(
        new moodle_url('/mod/syllabus/competencies.php', ['id' => $cm->id]),
        'frameworkid',
        $options,
        $frameworkid,
        null,
        null,
        ['label' => get_string('framework', 'mod_syllabus')]
    );
    if ($frameworkid) {
        $tree = syllabus_get_framework_tree($frameworkid);
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);
        $table = new html_table();
        $table->attributes['class'] = 'generaltable syllabus-competency-tree';
        $table->head = [get_string('competency', 'mod_syllabus'), get_string('level', 'mod_syllabus')];
        foreach ($tree as $competency) {
            $label = html_writer::span(format_string($competency->shortname), $competency->isleaf ? '' : 'font-weight-bold')
                . ($competency->idnumber !== '' ? ' ' . html_writer::span(s($competency->idnumber), 'small text-muted') : '');
            $select = html_writer::select(
                syllabus_competency_levels(true),
                'levels[' . $competency->id . ']',
                (int) ($linked[$competency->id]->level ?? 0),
                false,
                ['class' => 'custom-select',
                'aria-label' => format_string($competency->shortname)]
            );
            $table->data[] = [html_writer::div($label, '', ['style' => 'padding-left:' . (1.5 * $competency->depth) . 'rem']),
                $select];
        }
        echo html_writer::table($table);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges'),
            'class' => 'btn btn-primary']);
        echo html_writer::end_tag('form');
    }
}
echo $OUTPUT->footer();
