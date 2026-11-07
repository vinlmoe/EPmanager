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
 * Matrice d'un référentiel de compétences : quels cours du périmètre visent quelles compétences,
 * et à quel niveau (lien au module avec son niveau, ou lien au cours seulement) ; couverture du
 * référentiel ; export CSV.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$id = required_param('id', PARAM_INT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$studyyear = optional_param('studyyear', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init(
    $cm,
    '/mod/syllabusstaff/competencies.php',
    ['frameworkid' => $frameworkid, 'studyyear' => $studyyear]
);

$frameworks = syllabus_competencies_enabled() ? syllabus_get_frameworks() : [];
if (!$frameworkid && $frameworks) {
    $frameworkid = (int) array_key_first($frameworks);
}

// Colonnes : les cours du périmètre qui ont un module, et ceux qui ont des compétences du
// référentiel liées au cours sans avoir de module.
$columns = [];
if ($frameworkid) {
    [$modules] = syllabus_list_instances(['categoryid' => $instance->categoryid]);
    foreach ($modules as $module) {
        if (!$studyyear || (int) $module->studyyear === $studyyear) {
            $columns[(int) $module->course] = (object) ['courseid' => (int) $module->course, 'code' => $module->code !== ''
                ? $module->code : $module->courseshortname, 'name' => $module->coursename, 'cmid' => (int) $module->cmid];
        }
    }
    if (!$studyyear) {
        $params = ['fw' => $frameworkid];
        $catwhere = '';
        if ($instance->categoryid && ($category = core_course_category::get($instance->categoryid, IGNORE_MISSING, true))) {
            $catwhere = 'AND (cat.id = :catid OR ' . $DB->sql_like('cat.path', ':catpath') . ')';
            $params += ['catid' => $category->id, 'catpath' => $category->path . '/%'];
        }
        $others = $DB->get_records_sql("SELECT DISTINCT c.id, c.shortname, c.fullname
                  FROM {competency_coursecomp} cc
                  JOIN {competency} comp ON comp.id = cc.competencyid AND comp.competencyframeworkid = :fw
                  JOIN {course} c ON c.id = cc.courseid
                  JOIN {course_categories} cat ON cat.id = c.category
                 WHERE 1 = 1 $catwhere", $params);
        foreach ($others as $other) {
            $columns[(int) $other->id] = $columns[(int) $other->id] ?? (object) ['courseid' => (int) $other->id,
                'code' => $other->shortname, 'name' => $other->fullname, 'cmid' => 0];
        }
    }
    core_collator::asort_objects_by_property($columns, 'code', core_collator::SORT_NATURAL);
}
$tree = $frameworkid ? syllabus_get_framework_tree($frameworkid) : [];
$matrix = $frameworkid ? syllabus_competency_matrix($frameworkid, array_keys($columns)) : [];
$symbols = [-1 => '•', 0 => '✓'];
foreach (array_keys(syllabus_competency_levels()) as $level) {
    $symbols[$level] = get_string('levelshort_' . $level, 'mod_syllabus');
}

// Couverture de chaque compétence.
$coverage = [];
foreach ($tree as $competencyid => $competency) {
    $coverage[$competencyid] = 0;
    foreach ($columns as $column) {
        if (isset($matrix[$column->courseid][$competencyid])) {
            $coverage[$competencyid]++;
        }
    }
}

if ($download === 'csv' && $frameworkid) {
    $csv = new csv_export_writer('semicolon');
    $csv->set_filename('competences-' . clean_filename($frameworks[$frameworkid]->shortname));
    $csv->add_data(array_merge(
        [get_string('competency', 'mod_syllabus'), get_string('idnumber')],
        array_map(fn($c) => $c->code, array_values($columns)),
        [get_string('coverage', 'mod_syllabusstaff')]
    ));
    foreach ($tree as $competencyid => $competency) {
        $line = [str_repeat('  ', $competency->depth) . $competency->shortname, $competency->idnumber];
        foreach ($columns as $column) {
            $line[] = isset($matrix[$column->courseid][$competencyid]) ? $symbols[$matrix[$column->courseid][$competencyid]] : '';
        }
        $line[] = $coverage[$competencyid];
        $csv->add_data($line);
    }
    $csv->download_file();
    exit;
}

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'competencies', $canmanageall);
echo $OUTPUT->heading(get_string('competencymatrix', 'mod_syllabusstaff'), 3);
echo html_writer::tag('p', get_string('competencymatrix_help', 'mod_syllabusstaff'));
if (!syllabus_competencies_enabled()) {
    echo $OUTPUT->notification(get_string('competenciesdisabled', 'mod_syllabus'), 'info');
    echo $OUTPUT->footer();
    exit;
}
if (!$frameworks) {
    echo $OUTPUT->notification(get_string('noframeworks', 'mod_syllabus'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$options = array_map(fn($f) => format_string($f->shortname), $frameworks);
$url = new moodle_url('/mod/syllabusstaff/competencies.php', ['id' => $cm->id]);
echo html_writer::div($OUTPUT->single_select(
    new moodle_url($url, ['studyyear' => $studyyear]),
    'frameworkid',
    $options,
    $frameworkid,
    null,
    null,
    ['label' => get_string('framework', 'mod_syllabus')]
) . ' '
    . $OUTPUT->single_select(
        new moodle_url($url, ['frameworkid' => $frameworkid]),
        'studyyear',
        syllabus_studyyear_options(),
        $studyyear,
        null,
        null,
        ['label' => get_string('studyyear', 'mod_syllabus')]
    ), 'mb-2');

$leaves = array_filter($tree, fn($c) => $c->isleaf);
$covered = array_filter($leaves, fn($c) => $coverage[$c->id] > 0);
echo html_writer::tag('p', get_string('coveragesummary', 'mod_syllabusstaff', (object) ['covered' => count($covered),
    'total' => count($leaves), 'courses' => count($columns)]));
echo html_writer::tag('p', get_string('matrixlegend', 'mod_syllabusstaff'), ['class' => 'small text-muted']);

$table = new html_table();
$table->attributes['class'] = 'generaltable syllabusstaff-matrix';
$table->head = [get_string('competency', 'mod_syllabus')];
foreach ($columns as $column) {
    $link = $column->cmid ? new moodle_url('/mod/syllabus/competencies.php', ['id' => $column->cmid])
        : new moodle_url('/admin/tool/lp/coursecompetencies.php', ['courseid' => $column->courseid]);
    $table->head[] = html_writer::link($link, s($column->code), ['title' => format_string($column->name)]);
}
$table->head[] = get_string('coverage', 'mod_syllabusstaff');
foreach ($tree as $competencyid => $competency) {
    $label = html_writer::span(format_string($competency->shortname), $competency->isleaf ? '' : 'font-weight-bold');
    $row = new html_table_row([html_writer::div($label, '', ['style' => 'padding-left:' . (1.2 * $competency->depth) . 'rem'])]);
    foreach ($columns as $column) {
        $value = $matrix[$column->courseid][$competencyid] ?? null;
        $row->cells[] = new html_table_cell($value === null ? '' : $symbols[$value]);
    }
    $row->cells[] = new html_table_cell((string) $coverage[$competencyid]);
    if ($competency->isleaf && !$coverage[$competencyid]) {
        $row->attributes['class'] = 'table-warning';
    }
    $table->data[] = $row;
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
echo html_writer::link(
    new moodle_url($url, ['frameworkid' => $frameworkid, 'studyyear' => $studyyear, 'download' => 'csv']),
    get_string('downloadcsv', 'mod_syllabus'),
    ['class' => 'btn btn-secondary']
);
echo $OUTPUT->footer();
