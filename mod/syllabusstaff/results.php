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
$download = optional_param('download', '', PARAM_ALPHA);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init(
    $cm,
    '/mod/syllabusstaff/results.php',
    ['evalstate' => $evalstate]
);
require_capability('mod/syllabusstaff:manageall', $context);

[$rows] = syllabus_list_instances(['categoryid' => $instance->categoryid, 'evalstate' => $evalstate]);
$ids = array_map('intval', array_keys($rows));
$means = syllabus_common_means($ids);
$overview = syllabus_get_overview($ids);
// Colonnes : questions communes à échelle (même actives ou non, pour les évaluations passées).
$columns = array_filter(syllabus_get_common_questions(), fn($q) => $q->kind === 'scale');

$head = [get_string('module', 'mod_syllabus'), get_string('responses', 'mod_syllabusstaff')];
foreach (array_values($columns) as $index => $question) {
    $head[] = 'Q' . ($index + 1);
}
$data = [];
foreach ($rows as $row) {
    // Le nom du module ouvre ses résultats détaillés.
    $line = [html_writer::link(new moodle_url('/mod/syllabus/results.php', ['id' => $row->cmid]), format_string($row->name))
        . ($row->code !== '' ? ' ' . html_writer::span(s($row->code), 'small text-muted') : ''),
        $overview[(int) $row->id]->responses . ' / ' . syllabus_count_eligible(context_module::instance($row->cmid))];
    foreach ($columns as $question) {
        $mean = $means[(int) $row->id][(int) $question->id] ?? null;
        $line[] = $mean === null ? '-' : syllabus_format_number($mean);
    }
    $data[] = $line;
}

if ($download === 'csv') {
    $csv = new csv_export_writer('semicolon');
    $csv->set_filename('evaluations-modules');
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
$states = [];
foreach ([SYLLABUS_EVAL_OPEN, SYLLABUS_EVAL_CLOSED] as $state) {
    $states[$state] = get_string('evalstate_' . $state, 'mod_syllabus');
}
echo $OUTPUT->single_select(new moodle_url($PAGE->url), 'evalstate', $states, $evalstate, null);
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
