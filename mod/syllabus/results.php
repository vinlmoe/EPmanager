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
// Année affichée : le recueil en cours (0) ou une archive d'une année passée.
$archiveid = optional_param('archive', 0, PARAM_INT);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init(
    $cm,
    '/mod/syllabus/results.php',
    $archiveid ? ['archive' => $archiveid] : []
);
$archives = syllabus_get_archives((int) $syllabus->id);
// Les années passées sont closes : leurs résultats sont lisibles des responsables comme de la DEVE.
$canseepast = $rights->canseeresults || $rights->isteacher;
if ($archiveid && (!isset($archives[$archiveid]) || !$canseepast)) {
    throw new moodle_exception('invalidrecord', 'error');
}
if (!$rights->canseeresults && !($archiveid && $canseepast)) {
    if ($archives && $rights->isteacher) {
        // Le recueil en cours n'est pas encore lisible : la dernière année passée l'est.
        redirect(new moodle_url($PAGE->url, ['archive' => array_key_last($archives)]));
    }
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
$archive = $archiveid ? $archives[$archiveid] : null;
$results = $archive ? syllabus_archive_results($archive) : syllabus_get_results($syllabus);
$eligible = $archive ? (int) $archive->eligible : syllabus_count_eligible($context);
$yearlabel = $archive ? $archive->evalyear : ($syllabus->evalyear !== '' ? $syllabus->evalyear
    : syllabus_academic_year(time()));

if ($download === 'csv' && $results->shown) {
    $csv = new csv_export_writer('semicolon');
    $csv->set_filename(clean_filename(($syllabus->code !== '' ? $syllabus->code : $course->shortname) . '-avis-' . $yearlabel));
    $csv->add_data(syllabus_csv_safe([get_string('question', 'mod_syllabus'), get_string('questionkind', 'mod_syllabus'),
        get_string('answers', 'mod_syllabus'), get_string('mean', 'mod_syllabus'), get_string('distribution', 'mod_syllabus')]));
    $kinds = syllabus_question_kinds();
    foreach ($results->questions as $entry) {
        $parts = [];
        foreach ($entry->distribution as $value => $count) {
            $parts[] = $entry->labels[$value] . ' : ' . $count;
        }
        if ($entry->question->kind === 'text') {
            $parts = $entry->texts;
        }
        $csv->add_data(syllabus_csv_safe([$entry->question->text, $kinds[$entry->question->kind], $entry->count,
            $entry->mean === null ? '' : syllabus_format_number($entry->mean), implode(' | ', $parts)]));
    }
    $csv->download_file();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('results', 'mod_syllabus') . ' ' . s($yearlabel));

// Choix de l'année : le recueil en cours et les années archivées.
if ($archives) {
    $options = [];
    if ($rights->canseeresults) {
        $options[0] = get_string('yearcurrent', 'mod_syllabus', s($syllabus->evalyear !== '' ? $syllabus->evalyear : $yearlabel));
    }
    foreach (array_reverse($archives, true) as $id => $past) {
        $options[$id] = get_string('yearpast', 'mod_syllabus', s($past->evalyear));
    }
    echo $OUTPUT->single_select(
        new moodle_url('/mod/syllabus/results.php', ['id' => $cm->id]),
        'archive',
        $options,
        $archiveid,
        null,
        null,
        ['label' => get_string('academicyear', 'mod_syllabus')]
    );
}
echo html_writer::tag('p', $archive ? s(syllabus_eval_window_label($archive)) : syllabus_eval_badge($syllabus) . ' '
    . s(syllabus_eval_window_label($syllabus)));
echo html_writer::tag('p', get_string('responsesrate', 'mod_syllabus', (object) ['responses' => $results->responses,
    'eligible' => $eligible]) . ($eligible ? ' (' . round(100 * $results->responses / $eligible) . ' %)' : ''));

// Évolution des moyennes d'une année à l'autre, dès qu'il y a une année passée.
if ($archives) {
    $years = [];
    foreach ($archives as $past) {
        $years[$past->evalyear] = syllabus_archive_results($past);
    }
    if ($rights->canseeresults && syllabus_count_responses((int) $syllabus->id)) {
        $years[$syllabus->evalyear !== '' ? $syllabus->evalyear : $yearlabel] = $archive ? syllabus_get_results($syllabus)
            : $results;
    }
    $evolution = syllabus_results_evolution($years);
    if ($evolution) {
        $table = new html_table();
        $table->attributes = ['class' => 'generaltable syllabus-evolution'];
        $table->head = array_merge([get_string('question', 'mod_syllabus')], array_map('s', array_keys($years)));
        foreach ($evolution as $row) {
            $table->data[] = array_merge([format_string($row->text)], array_map(
                fn($mean) => $mean === null ? '-' : syllabus_format_number($mean),
                array_values($row->means)
            ));
        }
        echo $OUTPUT->heading(get_string('evolution', 'mod_syllabus'), 4);
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    }
}
if (!$results->shown) {
    echo $OUTPUT->notification(get_string('belowthreshold', 'mod_syllabus', $results->threshold ?: syllabus_setting(
        'resultsthreshold'
    )), 'info');
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
    echo html_writer::div(html_writer::table($summary), 'table-responsive');
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
        $html .= html_writer::div(html_writer::table($table), 'table-responsive');
    }
    echo $OUTPUT->box($html, 'generalbox mb-3');
}
echo $OUTPUT->footer();
