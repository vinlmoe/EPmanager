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
 * Livret des syllabus d'un semestre, d'une année d'étude ou de tout le cursus : aperçu des modules
 * retenus, puis le PDF (page de titre, sommaire, modules et crédits, fiches publiées).
 *
 * Seules les versions publiées figurent dans le livret, et seuls les modules dont l'activité est
 * visible : c'est le document diffusé aux étudiants.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');

$id = required_param('id', PARAM_INT);
$selection = optional_param('selection', 'all', PARAM_TEXT);
$download = optional_param('download', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init(
    $cm,
    '/mod/syllabusstaff/booklet.php',
    ['selection' => $selection]
);

[$all] = syllabus_list_instances(['categoryid' => $instance->categoryid]);
$all = array_filter($all, fn($module) => (int) $module->cmvisible === 1);
$options = syllabus_period_selections($all);
$valid = array_merge(...array_map(fn($group) => array_keys(reset($group)), $options));
if (!in_array($selection, $valid, true)) {
    $selection = 'all';
}
$modules = syllabus_filter_by_selection($all, $selection);
$teachers = syllabus_get_teachers_bulk(array_map(fn($m) => (int) $m->id, $modules));
foreach ($modules as $module) {
    $module->published = syllabus_get_published((int) $module->id);
    $module->teachers = $teachers[(int) $module->id] ?? [];
}

if ($download && $modules) {
    // Un livret de tout le cursus compte des centaines de pages.
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_EXTRA);
    $title = get_string('booklettitle', 'mod_syllabus');
    $filename = clean_filename($title . ' - ' . syllabus_selection_label($selection) . '.pdf');
    $scope = $instance->categoryid && ($category = core_course_category::get($instance->categoryid, IGNORE_MISSING, true))
        ? $category->get_formatted_name() : format_string(get_site()->fullname, true, ['escape' => false]);
    $lines = [syllabus_selection_label($selection), strip_tags($scope), userdate(time(), get_string('strftimedate', 'langconfig'))];
    $build = fn() => (new \mod_syllabus\pdf\booklet_pdf($title, $lines, $modules))->Output('', 'S');
    $file = syllabusstaff_booklet_file($context, $instance, $selection, $modules, $build);
    send_stored_file($file, 0, 0, true, ['filename' => $filename]);
}

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'booklet', $canmanageall);
echo $OUTPUT->heading(get_string('booklet', 'mod_syllabusstaff'), 3);
echo html_writer::tag('p', get_string('booklet_help', 'mod_syllabusstaff'));

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(),
    'class' => 'form-inline mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::tag('label', get_string('bookletselection', 'mod_syllabusstaff'), ['for' => 'selection',
    'class' => 'mr-2']);
echo html_writer::select($options, 'selection', $selection, false, ['id' => 'selection', 'class' => 'custom-select mr-2']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('show', 'mod_syllabusstaff'),
    'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if (!$modules) {
    echo $OUTPUT->notification(get_string('nomodules', 'mod_syllabusstaff'), 'info');
    echo $OUTPUT->footer();
    exit;
}
$published = count(array_filter($modules, fn($m) => $m->published));
echo html_writer::tag('p', get_string('bookletcount', 'mod_syllabusstaff', (object) ['published' => $published,
    'total' => count($modules)]));
echo html_writer::link(
    new moodle_url($PAGE->url, ['download' => 1]),
    get_string('bookletdownload', 'mod_syllabusstaff'),
    ['class' => 'btn btn-primary mb-3' . ($published ? '' : ' disabled')]
);

$table = new html_table();
$table->attributes['class'] = 'generaltable syllabusstaff-booklet';
$table->head = [get_string('code', 'mod_syllabus'), get_string('module', 'mod_syllabus'), get_string('ects', 'mod_syllabus'),
    get_string('teachers', 'mod_syllabus'), get_string('fiche', 'mod_syllabus')];
$current = null;
$ects = 0.0;
$total = 0.0;
$closeperiod = function () use (&$table, &$ects) {
    $cell = new html_table_cell(html_writer::tag('em', get_string('ectstotal', 'mod_syllabus')));
    $cell->colspan = 2;
    $cell->attributes['class'] = 'text-right';
    $table->data[] = new html_table_row([$cell, html_writer::tag('strong', syllabus_format_number($ects)), '', '']);
};
foreach ($modules as $module) {
    [$key, $label] = syllabus_period_of($module);
    if ($key !== $current) {
        if ($current !== null) {
            $closeperiod();
        }
        $cell = new html_table_cell(html_writer::tag('strong', s($label)));
        $cell->colspan = 5;
        $row = new html_table_row([$cell]);
        $row->attributes['class'] = 'table-active';
        $table->data[] = $row;
        $current = $key;
        $ects = 0.0;
    }
    $ects += (float) $module->ects;
    $total += (float) $module->ects;
    $fiche = $module->published
        ? html_writer::span(get_string('versionshort', 'mod_syllabus', $module->published->version), 'badge badge-success')
        : html_writer::span(get_string('notpublishedshort', 'mod_syllabus'), 'badge badge-warning');
    $table->data[] = [
        s($module->code !== '' ? $module->code : $module->courseshortname),
        html_writer::link(new moodle_url('/mod/syllabus/view.php', ['id' => $module->cmid]), format_string($module->name)),
        syllabus_format_number($module->ects),
        s(implode(', ', array_map('fullname', $module->teachers))) ?: '-',
        $fiche,
    ];
}
$closeperiod();
$cell = new html_table_cell(html_writer::tag('strong', get_string('ectsgrandtotal', 'mod_syllabus')));
$cell->colspan = 2;
$cell->attributes['class'] = 'text-right';
$table->data[] = new html_table_row([$cell, html_writer::tag('strong', syllabus_format_number($total)), '', '']);
echo html_writer::table($table);
echo $OUTPUT->footer();
