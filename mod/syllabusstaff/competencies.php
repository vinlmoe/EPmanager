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
 * et à quel niveau (par leur module, ou directement pour un cours sans module) ; couverture du
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

$frameworks = syllabus_get_frameworks();
if (!isset($frameworks[$frameworkid])) {
    $frameworkid = $frameworks ? (int) array_key_first($frameworks) : 0;
}

// Colonnes : les cours du périmètre qui ont un module, et ceux qui visent des compétences sans en
// avoir (liés par la DEVE).
$columns = [];
$withoutmodule = [];
if ($frameworkid) {
    [$modules] = syllabus_list_instances(['categoryid' => $instance->categoryid]);
    foreach ($modules as $module) {
        if (!$studyyear || (int) $module->studyyear === $studyyear) {
            $columns[(int) $module->course] = (object) ['courseid' => (int) $module->course, 'code' => $module->code !== ''
                ? $module->code : $module->courseshortname, 'name' => $module->coursename, 'cmid' => (int) $module->cmid];
        }
    }
    $withmodule = array_map(fn($m) => (int) $m->course, $modules);
    foreach (syllabusstaff_scope_courses($instance) as $other) {
        if (in_array((int) $other->id, $withmodule, true)) {
            continue;
        }
        $withoutmodule[(int) $other->id] = $other;
        if (!$studyyear && $DB->record_exists('syllabus_complink', ['courseid' => $other->id, 'syllabusid' => 0])) {
            $columns[(int) $other->id] = (object) ['courseid' => (int) $other->id, 'code' => $other->shortname,
                'name' => $other->fullname, 'cmid' => 0];
        }
    }
    core_collator::asort_objects_by_property($columns, 'code', core_collator::SORT_NATURAL);
}
$tree = $frameworkid ? syllabus_get_framework_tree($frameworkid) : [];
$matrix = $frameworkid ? syllabus_competency_matrix($frameworkid, array_keys($columns)) : [];
$symbol = function (?stdClass $cell): string {
    return $cell ? get_string('levelshort_' . $cell->level, 'mod_syllabus') . ($cell->coursewide ? '*' : '') : '';
};
$coverage = [];
foreach ($tree as $compid => $comp) {
    $coverage[$compid] = count(array_filter($columns, fn($c) => isset($matrix[$c->courseid][$compid])));
}

if ($download === 'csv' && $frameworkid) {
    $csv = new csv_export_writer('semicolon');
    $csv->set_filename('competences-' . clean_filename($frameworks[$frameworkid]->idnumber));
    $csv->add_data(array_merge(
        [get_string('code', 'mod_syllabusstaff'), get_string('competency', 'mod_syllabus')],
        array_map(fn($c) => $c->code, array_values($columns)),
        [get_string('coverage', 'mod_syllabusstaff')]
    ));
    foreach ($tree as $compid => $comp) {
        $line = [$comp->idnumber, str_repeat('  ', $comp->depth) . $comp->name];
        foreach ($columns as $column) {
            $line[] = $symbol($matrix[$column->courseid][$compid] ?? null);
        }
        $line[] = $coverage[$compid];
        $csv->add_data($line);
    }
    $csv->download_file();
    exit;
}

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'competencies', $canmanageall);
echo $OUTPUT->heading(get_string('competencymatrix', 'mod_syllabusstaff'), 3);
echo html_writer::tag('p', get_string('competencymatrix_help', 'mod_syllabusstaff'));
if (!$frameworks) {
    echo $OUTPUT->notification(get_string($canmanageall ? 'noframeworksdeve' : 'noframeworks', $canmanageall
        ? 'mod_syllabusstaff' : 'mod_syllabus'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$url = new moodle_url('/mod/syllabusstaff/competencies.php', ['id' => $cm->id]);
echo html_writer::div($OUTPUT->single_select(
    new moodle_url($url, ['studyyear' => $studyyear]),
    'frameworkid',
    array_map(fn($f) => format_string($f->name), $frameworks),
    $frameworkid,
    null,
    null,
    ['label' => get_string('framework', 'mod_syllabus')]
) . ' ' . $OUTPUT->single_select(
    new moodle_url(
        $url,
        ['frameworkid' => $frameworkid]
    ),
    'studyyear',
    syllabus_studyyear_options(),
    $studyyear,
    null,
    null,
    ['label' => get_string('studyyear', 'mod_syllabus')]
), 'mb-2');

$leaves = array_filter($tree, fn($c) => $c->isleaf);
echo html_writer::tag('p', get_string('coveragesummary', 'mod_syllabusstaff', (object) [
    'covered' => count(array_filter($leaves, fn($c) => $coverage[$c->id] > 0)), 'total' => count($leaves),
    'courses' => count($columns)]));
echo html_writer::tag('p', get_string('matrixlegend', 'mod_syllabusstaff'), ['class' => 'small text-muted']);

$table = new html_table();
$table->attributes['class'] = 'generaltable syllabusstaff-matrix';
$table->head = [get_string('competency', 'mod_syllabus')];
foreach ($columns as $column) {
    $link = $column->cmid ? new moodle_url('/mod/syllabus/competencies.php', ['id' => $column->cmid,
        'frameworkid' => $frameworkid]) : new moodle_url('/mod/syllabusstaff/courselink.php', ['id' => $cm->id,
        'courseid' => $column->courseid, 'frameworkid' => $frameworkid]);
    $table->head[] = html_writer::link($link, s($column->code), ['title' => format_string($column->name)]);
}
$table->head[] = get_string('coverage', 'mod_syllabusstaff');
foreach ($tree as $compid => $comp) {
    $label = html_writer::span(s($comp->idnumber), 'small text-muted') . ' ' . html_writer::span(
        format_string($comp->name),
        $comp->isleaf ? '' : 'font-weight-bold'
    );
    $row = new html_table_row([html_writer::div($label, '', ['style' => 'padding-left:' . (1.2 * $comp->depth) . 'rem'])]);
    foreach ($columns as $column) {
        $row->cells[] = new html_table_cell($symbol($matrix[$column->courseid][$compid] ?? null));
    }
    $row->cells[] = new html_table_cell((string) $coverage[$compid]);
    if ($comp->isleaf && !$coverage[$compid]) {
        $row->attributes['class'] = 'table-warning';
    }
    $table->data[] = $row;
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
echo html_writer::link(
    new moodle_url($url, ['frameworkid' => $frameworkid, 'studyyear' => $studyyear, 'download' => 'csv']),
    get_string('downloadcsv', 'mod_syllabus'),
    ['class' => 'btn btn-secondary mr-2']
);

// Lier un cours du périmètre qui n'a pas de module (DEVE).
if ($canmanageall && $withoutmodule) {
    $options = [];
    foreach ($withoutmodule as $other) {
        $options[$other->id] = format_string($other->fullname);
    }
    echo html_writer::div($OUTPUT->single_select(
        new moodle_url('/mod/syllabusstaff/courselink.php', ['id' => $cm->id,
        'frameworkid' => $frameworkid]),
        'courseid',
        $options,
        '',
        ['' => get_string('linkcourse', 'mod_syllabusstaff')],
        null,
        ['label' => get_string('coursewithoutmodule', 'mod_syllabusstaff')]
    ), 'mt-3');
}
echo $OUTPUT->footer();
