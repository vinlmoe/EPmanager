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
 * Résultats de l'évaluation : nombre de réponses, moyennes et répartitions, réponses libres.
 * Responsables (après la fermeture, sauf réglage contraire de la DEVE) et DEVE. Sous le seuil
 * d'anonymat, seul le nombre de réponses est donné.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$id = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init($cm, '/mod/syllabus/results.php');
if (!$rights->canseeresults) {
    if ($rights->isteacher) {
        redirect(
            new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]),
            get_string('resultsafterclose', 'mod_syllabus'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
    throw new required_capability_exception($context, 'mod/syllabus:manage', 'nopermissions', '');
}
$results = syllabus_get_results($syllabus);
$eligible = syllabus_count_eligible($context);

if ($download === 'csv' && $results->shown) {
    $csv = new csv_export_writer('semicolon');
    $csv->set_filename(clean_filename(($syllabus->code !== '' ? $syllabus->code : $course->shortname) . '-evaluation'));
    $csv->add_data([get_string('question', 'mod_syllabus'), get_string('questionkind', 'mod_syllabus'),
        get_string('answers', 'mod_syllabus'), get_string('mean', 'mod_syllabus'), get_string('distribution', 'mod_syllabus')]);
    $kinds = syllabus_question_kinds();
    foreach ($results->questions as $entry) {
        $parts = [];
        foreach ($entry->distribution as $value => $count) {
            $parts[] = $entry->labels[$value] . ' : ' . $count;
        }
        if ($entry->question->kind === 'text') {
            $parts = $entry->texts;
        }
        $csv->add_data([$entry->question->text, $kinds[$entry->question->kind], $entry->count,
            $entry->mean === null ? '' : syllabus_format_number($entry->mean), implode(' | ', $parts)]);
    }
    $csv->download_file();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('results', 'mod_syllabus'));
echo html_writer::tag('p', syllabus_eval_badge($syllabus) . ' ' . s(syllabus_eval_window_label($syllabus)));
echo html_writer::tag('p', get_string('responsesrate', 'mod_syllabus', (object) ['responses' => $results->responses,
    'eligible' => $eligible]) . ($eligible ? ' (' . round(100 * $results->responses / $eligible) . ' %)' : ''));
if (!$results->shown) {
    echo $OUTPUT->notification(get_string('belowthreshold', 'mod_syllabus', $results->threshold), 'info');
    echo $OUTPUT->footer();
    exit;
}
echo html_writer::div(html_writer::link(new moodle_url($PAGE->url, ['download' => 'csv']), get_string(
    'downloadcsv',
    'mod_syllabus'
), ['class' => 'btn btn-secondary']), 'mb-3');

// En tête, les moyennes des questions à échelle : l'essentiel en un coup d'œil, le détail ensuite.
$summary = new html_table();
$summary->attributes = ['class' => 'generaltable syllabus-means'];
$summary->head = [get_string('question', 'mod_syllabus'), get_string('mean', 'mod_syllabus')];
foreach (array_values($results->questions) as $index => $entry) {
    if ($entry->mean !== null) {
        $summary->data[] = [($index + 1) . '. ' . format_string($entry->question->text), html_writer::tag(
            'strong',
            syllabus_format_number($entry->mean)
        ) . ' / 5'];
    }
}
if ($summary->data) {
    echo html_writer::table($summary);
}

$number = 0;
foreach ($results->questions as $entry) {
    $number++;
    $question = $entry->question;
    $html = html_writer::tag('h5', $number . '. ' . format_string($question->text) . ' '
        . html_writer::span(get_string('origin_' . $question->origin, 'mod_syllabus'), 'badge badge-light'));
    $html .= html_writer::tag('p', get_string('answersn', 'mod_syllabus', $entry->count), ['class' => 'text-muted small']);
    if ($question->kind === 'text') {
        $html .= $entry->texts ? html_writer::alist(array_map(fn($t) => format_text($t, FORMAT_PLAIN), $entry->texts))
            : html_writer::tag('p', '-');
    } else {
        if ($entry->mean !== null) {
            $html .= html_writer::tag('p', html_writer::tag('strong', get_string(
                'meanis',
                'mod_syllabus',
                syllabus_format_number($entry->mean)
            )));
        }
        $table = new html_table();
        $table->attributes = ['class' => 'generaltable syllabus-distribution'];
        $table->head = [get_string('answer', 'mod_syllabus'), get_string('answers', 'mod_syllabus'), '%'];
        foreach ($entry->distribution as $value => $count) {
            $percent = $entry->count ? round(100 * $count / $entry->count) : 0;
            $bar = html_writer::div('', 'bg-primary', ['style' => 'height:0.8rem;width:' . $percent . '%;min-width:1px']);
            $label = $question->kind === 'scale' ? get_string('scale_' . $value, 'mod_syllabus') : $entry->labels[$value];
            $table->data[] = [s($label), $count, html_writer::div($bar, '', ['style' => 'width:12rem']) . $percent . ' %'];
        }
        $html .= html_writer::table($table);
    }
    echo $OUTPUT->box($html, 'generalbox mb-3');
}
echo $OUTPUT->footer();
