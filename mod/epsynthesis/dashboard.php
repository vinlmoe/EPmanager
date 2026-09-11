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
 * Tableau de pilotage : reprend mod_ep/dashboard.php (avancement des ECTS de chaque étudiant,
 * avec accès à sa situation détaillée), combiné sur toutes les activités « Enseignement
 * personnalisé » liées où l'utilisateur connecté est enseignant référent. Complète entries.php
 * (validation, page d'atterrissage de l'activité) : mêmes conventions que sur l'écran d'origine.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$studentid = optional_param('studentid', 0, PARAM_INT);
$studentcmid = optional_param('cmid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$tsort = optional_param('tsort', 'student', PARAM_ALPHA);
$tdir = optional_param('tdir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$epsynthesis = $DB->get_record('epsynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:view', $context);

$baseurl = new moodle_url('/mod/epsynthesis/dashboard.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($epsynthesis->name) . ' - ' . get_string('pilotage', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$activelinks = epsynthesis_get_active_links($epsynthesis->id, $USER->id);

// Situation détaillée d'un étudiant donné : délègue entièrement à ep_print_student_dashboard(),
// qui affiche déjà tout (synthèse, bilans par année et par type, détail des EP) pour une activité
// mod_ep donnée — seul le repérage de l'activité et de l'étudiant dans le périmètre change ici.
if ($studentid) {
    if (!isset($activelinks[$studentcmid])) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pilotage', 'mod_ep'));
    }
    $link = $activelinks[$studentcmid];
    if (!in_array((int) $studentid, $link->rights->referentids, true)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pilotage', 'mod_ep'));
    }
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
    $PAGE->set_url(new moodle_url($baseurl, ['studentid' => $studentid, 'cmid' => $studentcmid]));

    ep_sync_stage_credits_if_due($link->ep);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pilotage', 'mod_ep') . ' - ' . fullname($student));
    echo html_writer::link($baseurl, get_string('back'));

    ep_print_student_dashboard($link->ep, $student->id, $link->cm, $link->rights, false);

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($epsynthesis->name));
echo html_writer::link(
    new moodle_url('/mod/epsynthesis/entries.php', ['id' => $cm->id]),
    get_string('validation', 'mod_ep')
);

if ($epsynthesis->intro) {
    echo $OUTPUT->box(format_module_intro('epsynthesis', $epsynthesis, $cm->id), 'generalbox mod_introbox');
}

echo epsynthesis_render_managelinks_notice($epsynthesis, $cm, $context);

if (empty($activelinks)) {
    echo $OUTPUT->notification(get_string('noscope', 'mod_epsynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

epsynthesis_sync_active_links($activelinks);

$listurl = new moodle_url($baseurl, ['search' => $search, 'tsort' => $tsort, 'tdir' => $tdir]);

$searchformurl = new moodle_url($baseurl);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $searchformurl, 'class' => 'form-inline ep-filters mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'name' => 'search', 'value' => s($search),
    'placeholder' => get_string('searchstudent', 'mod_ep'), 'class' => 'form-control mr-2',
]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2']);
echo html_writer::link($searchformurl, get_string('resetfilters', 'mod_ep'), ['class' => 'btn btn-link']);
echo html_writer::end_tag('form');

$rows = epsynthesis_get_pilotage_rows($activelinks);

if ($search !== '') {
    $needle = core_text::strtolower($search);
    $rows = array_filter($rows, function ($row) use ($needle) {
        return core_text::strpos(core_text::strtolower(fullname($row->user)), $needle) !== false;
    });
}

$sortmap = [
    'course' => function ($row) {
        return core_text::strtolower($row->coursename);
    },
    'student' => function ($row) {
        return core_text::strtolower(fullname($row->user));
    },
    'retained' => function ($row) {
        return $row->progress->totalretained;
    },
    'years' => function ($row) {
        return $row->progress->yearstotal > 0 ? ($row->progress->yearsdone / $row->progress->yearstotal) : -1;
    },
    'pending' => function ($row) {
        return $row->pendingcount;
    },
];
$sortkey = array_key_exists($tsort, $sortmap) ? $tsort : 'student';
$sortfn = $sortmap[$sortkey];
usort($rows, function ($a, $b) use ($sortfn) {
    return $sortfn($a) <=> $sortfn($b);
});
if (strtoupper($tdir) === 'DESC') {
    $rows = array_reverse($rows);
}

if (empty($rows)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_ep'), 'info');
} else {
    [$pagerows, $pagingbarhtml] = ep_paginate($rows, $page, $listurl);

    $table = new html_table();
    $table->head = [
        ep_sort_header(get_string('course'), 'course', $listurl, $sortkey, $tdir),
        ep_sort_header(get_string('student', 'mod_ep'), 'student', $listurl, $sortkey, $tdir),
        ep_sort_header(get_string('totalretainedshort', 'mod_ep'), 'retained', $listurl, $sortkey, $tdir),
        get_string('cursusminimumshort', 'mod_ep'),
        ep_sort_header(get_string('yearsdoneshort', 'mod_ep'), 'years', $listurl, $sortkey, $tdir),
        ep_sort_header(get_string('pendingrequests', 'mod_ep'), 'pending', $listurl, $sortkey, $tdir),
        get_string('status', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ];
    foreach ($pagerows as $row) {
        $progress = $row->progress;
        $cursuscell = $progress->mincursus > 0
            ? get_string('progressofects', 'mod_ep', (object) [
                'retained' => ep_format_ects($progress->totalretained),
                'required' => ep_format_ects($progress->mincursus),
            ]) . ' ' . ep_render_status_badge($progress->cursusdone)
            : '-';
        $yearscell = $progress->yearstotal > 0
            ? $progress->yearsdone . ' / ' . $progress->yearstotal
            : get_string('noyearminimum', 'mod_ep');
        $pendingcell = $row->pendingcount > 0
            ? html_writer::span($row->pendingcount, 'badge badge-info')
            : html_writer::span('0', 'text-muted');

        $table->data[] = [
            format_string($row->coursename),
            fullname($row->user),
            ep_format_ects($progress->totalretained),
            $cursuscell,
            $yearscell,
            $pendingcell,
            ep_render_status_badge($progress->complete),
            ep_render_actions([
                get_string('viewdetails', 'mod_ep') =>
                    new moodle_url($baseurl, ['studentid' => $row->user->id, 'cmid' => $row->cmid]),
            ]),
        ];
    }
    echo html_writer::table($table);
    echo $pagingbarhtml;
}

echo $OUTPUT->footer();
