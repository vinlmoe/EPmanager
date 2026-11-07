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
 * Résultats des évaluations, module par module : réponses et moyenne de chaque question commune
 * à échelle (comparable d'un module à l'autre). Les modules sous le seuil d'anonymat n'ont pas
 * de moyenne.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$id = required_param('id', PARAM_INT);
$evalstate = optional_param('evalstate', SYLLABUS_EVAL_CLOSED, PARAM_ALPHA);
// Année passée (archives) : vide pour les recueils de l'année en cours.
$year = optional_param('year', '', PARAM_TEXT);
$download = optional_param('download', '', PARAM_ALPHA);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init(
    $cm,
    '/mod/syllabusstaff/results.php',
    array_filter(['evalstate' => $evalstate, 'year' => $year])
);
require_capability('mod/syllabusstaff:manageall', $context);

// Années passées présentes dans les archives des modules du périmètre.
[$all] = syllabus_list_instances(['categoryid' => $instance->categoryid]);
$pastyears = [];
if ($all) {
    [$insql, $params] = $DB->get_in_or_equal(array_map('intval', array_keys($all)));
    $pastyears = $DB->get_fieldset_sql("SELECT DISTINCT evalyear FROM {syllabus_evalarchive} WHERE syllabusid $insql
                                        ORDER BY evalyear DESC", $params);
}
if ($year !== '' && !in_array($year, $pastyears, true)) {
    $year = '';
}
if ($year === '') {
    [$rows] = syllabus_list_instances(['categoryid' => $instance->categoryid, 'evalstate' => $evalstate]);
    $ids = array_map('intval', array_keys($rows));
    $means = syllabus_common_means($ids);
    $overview = syllabus_get_overview($ids);
    $counts = array_map(fn($info) => $info->responses, $overview);
} else {
    // Année passée : les modules qui en ont une archive, avec les moyennes gardées.
    $archived = syllabus_archived_means(array_map('intval', array_keys($all)), $year);
    $rows = array_intersect_key($all, $archived);
    $means = array_map(fn($entry) => $entry['means'], $archived);
    $counts = array_map(fn($entry) => $entry['responses'], $archived);
}
// Colonnes : questions communes à échelle (même actives ou non, pour les évaluations passées).
$columns = array_filter(syllabus_get_common_questions(), fn($q) => $q->kind === 'scale');

$head = [get_string('module', 'mod_syllabus'), get_string('responses', 'mod_syllabusstaff')];
foreach (array_values($columns) as $index => $question) {
    $head[] = 'Q' . ($index + 1);
}
$data = [];
foreach ($rows as $row) {
    // Le nom du module ouvre ses résultats détaillés.
    $target = new moodle_url('/mod/syllabus/results.php', ['id' => $row->cmid] + ($year !== ''
        ? ['archive' => $archived[(int) $row->id]['archiveid']] : []));
    $line = [html_writer::link($target, format_string($row->name))
        . ($row->code !== '' ? ' ' . html_writer::span(s($row->code), 'small text-muted') : ''),
        $year === '' ? $counts[(int) $row->id] . ' / ' . syllabus_count_eligible(context_module::instance($row->cmid))
            : (string) $counts[(int) $row->id]];
    foreach ($columns as $question) {
        $mean = $means[(int) $row->id][(int) $question->id] ?? null;
        $line[] = $mean === null ? '-' : syllabus_format_number($mean);
    }
    $data[] = $line;
}

if ($download === 'csv') {
    $csv = new csv_export_writer('semicolon');
    $csv->set_filename('avis-des-etudiants-' . ($year !== '' ? $year : syllabus_academic_year(time())));
    $csv->add_data(array_merge(array_slice($head, 0, 2), array_map(fn($q) => $q->text, array_values($columns))));
    foreach ($data as $line) {
        $csv->add_data(array_map(fn($cell) => html_entity_decode(strip_tags((string) $cell), ENT_QUOTES, 'UTF-8'), $line));
    }
    $csv->download_file();
    exit;
}

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'results', $canmanageall);
echo $OUTPUT->heading(get_string('resultsoverview', 'mod_syllabusstaff'), 3);
echo html_writer::tag('p', get_string('resultsoverview_help', 'mod_syllabusstaff', syllabus_setting('resultsthreshold')));
// Année : celle en cours (recueils ouverts ou terminés), ou une année passée.
$selects = '';
if ($pastyears) {
    $selects .= $OUTPUT->single_select(
        new moodle_url('/mod/syllabusstaff/results.php', ['id' => $cm->id]),
        'year',
        array_combine($pastyears, $pastyears),
        $year,
        ['' => get_string('yearcurrent', 'mod_syllabus', syllabus_academic_year(time()))],
        null,
        ['label' => get_string('academicyear', 'mod_syllabus')]
    );
}
if ($year === '') {
    $states = [];
    foreach ([SYLLABUS_EVAL_OPEN, SYLLABUS_EVAL_CLOSED] as $state) {
        $states[$state] = get_string('evalstate_' . $state, 'mod_syllabus');
    }
    $selects .= ' ' . $OUTPUT->single_select(new moodle_url($PAGE->url), 'evalstate', $states, $evalstate, null);
}
echo html_writer::div($selects, 'mb-2');
if ($columns) {
    $legend = [];
    foreach (array_values($columns) as $index => $question) {
        $legend[] = 'Q' . ($index + 1) . ' : ' . format_string($question->text);
    }
    echo html_writer::alist($legend, ['class' => 'small text-muted']);
}
if ($data) {
    $table = new html_table();
    $table->head = $head;
    $table->data = $data;
    echo html_writer::table($table);
    echo html_writer::link(
        new moodle_url($PAGE->url, ['download' => 'csv']),
        get_string('downloadcsv', 'mod_syllabus'),
        ['class' => 'btn btn-secondary']
    );
} else {
    echo $OUTPUT->notification(get_string('nomodules', 'mod_syllabusstaff'), 'info');
}
echo $OUTPUT->footer();
