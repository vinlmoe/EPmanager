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
 * Suivi des EP académiques, EP par EP : d'abord la liste des EP suivis ici avec, pour chacun, où
 * en sont ses inscriptions ; puis, pour un EP donné, la liste de ses inscrits — toutes promotions
 * confondues, puisqu'un EP partagé s'adresse à plusieurs d'entre elles.
 *
 * Les autres écrans de la synthèse suivent des étudiants (dashboard.php) ou des demandes
 * (entries.php) ; celui-ci suit les EP eux-mêmes, ce qui est la façon de travailler du responsable
 * — « qui est inscrit à mon EP, combien en ai-je accepté, lesquels me reste-t-il à clôturer » — et
 * de la DEVE, qui voit ici l'ensemble des EP académiques (mod/epsynthesis:viewall).
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$activityid = optional_param('activityid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$filterstatus = optional_param('status', '', PARAM_RAW);
$filteryear = optional_param('studyyear', '', PARAM_RAW);
$tsort = optional_param('tsort', 'student', PARAM_ALPHA);
$tdir = optional_param('tdir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$epsynthesis = $DB->get_record('epsynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:view', $context);

$baseurl = new moodle_url('/mod/epsynthesis/registrations.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($epsynthesis->name) . ' - '
    . get_string('registrationsfollowup', 'mod_epsynthesis'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Inscrits d'un EP donné : c'est là que le responsable travaille, une fois qu'il a repéré son EP
// dans la liste.
if ($activityid) {
    $followup = epsynthesis_get_followup_activity($epsynthesis, $cm, $context, $USER->id, $activityid);
    if (!$followup) {
        throw new moodle_exception('nopermissions', 'error', '',
            get_string('registrationsfollowup', 'mod_epsynthesis'));
    }
    $activity = $followup->activity;

    $activityurl = new moodle_url($baseurl, ['activityid' => $activity->id]);
    $PAGE->set_url($activityurl);

    $listurl = new moodle_url($activityurl, [
        'search' => $search, 'status' => $filterstatus, 'studyyear' => $filteryear,
        'tsort' => $tsort, 'tdir' => $tdir,
    ]);

    $registrations = ep_get_activity_registrations($activity->id, [
        'search' => $search,
        'status' => $filterstatus,
        'studyyear' => $filteryear,
    ], $tsort, $tdir);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($activity->name));
    echo html_writer::link($baseurl, get_string('back'));

    if (trim((string) $activity->description) !== '') {
        echo $OUTPUT->box(format_text($activity->description, FORMAT_PLAIN));
    }

    $counts = ep_get_activity_registration_counts($activity->id);
    $summary = new html_table();
    $summary->attributes['class'] = 'generaltable';
    $summary->data[] = [get_string('origin', 'mod_ep'), $followup->origin];
    $summary->data[] = [get_string('ects', 'mod_ep'), ep_format_ects($activity->ects)];
    $summary->data[] = [
        get_string('studyyearrange', 'mod_ep'),
        ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear),
    ];
    $summary->data[] = [get_string('places', 'mod_ep'), ep_render_places_cell($activity, $counts)];
    $summary->data[] = [get_string('registrations', 'mod_ep'), ep_render_registration_counts($counts)];
    $teachers = ep_get_activity_teachers($activity->id);
    $summary->data[] = [
        get_string('activityteachers', 'mod_ep'),
        empty($teachers) ? '-' : implode(', ', array_map('fullname', $teachers)),
    ];
    echo html_writer::table($summary);

    // Les filtres reprennent ceux des listes de crédits, moins celui de type : sur un EP donné,
    // toutes les inscriptions relèvent du même.
    $formurl = new moodle_url($activityurl);
    echo html_writer::start_tag('form',
        ['method' => 'get', 'action' => $formurl, 'class' => 'form-inline ep-filters mb-3']);
    foreach ($formurl->params() as $key => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'search', 'value' => s($search),
        'placeholder' => get_string('searchstudent', 'mod_ep'), 'class' => 'form-control mr-2',
    ]);
    echo html_writer::select(['' => get_string('allyears', 'mod_ep')] + ep_studyyear_options(),
        'studyyear', $filteryear, false, ['class' => 'form-control mr-2']);
    echo html_writer::select(['' => get_string('allstatuses', 'mod_ep')] + ep_status_options(),
        'status', $filterstatus, false, ['class' => 'form-control mr-2']);
    echo html_writer::empty_tag('input',
        ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2']);
    echo html_writer::link($formurl, get_string('resetfilters', 'mod_ep'), ['class' => 'btn btn-link']);
    echo html_writer::end_tag('form');

    if (empty($registrations)) {
        echo $OUTPUT->notification(get_string('noregistrations', 'mod_epsynthesis'), 'info');
        echo $OUTPUT->footer();
        exit;
    }

    [$rows, $pagingbarhtml] = ep_paginate($registrations, $page, $listurl);

    $table = new html_table();
    $table->head = [
        ep_sort_header(get_string('student', 'mod_ep'), 'student', $listurl, $tsort, $tdir),
        ep_sort_header(get_string('course'), 'course', $listurl, $tsort, $tdir),
        ep_sort_header(get_string('studyyear', 'mod_ep'), 'studyyear', $listurl, $tsort, $tdir),
        ep_sort_header(get_string('submittedon', 'mod_ep'), 'timecreated', $listurl, $tsort, $tdir),
        ep_sort_header(get_string('status', 'mod_ep'), 'status', $listurl, $tsort, $tdir),
        get_string('retainedects', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ];

    foreach ($rows as $registration) {
        $candecide = epsynthesis_can_decide($registration, $followup, $registration->cmid, $USER->id);
        $decideurl = new moodle_url('/mod/epsynthesis/decide.php', [
            'id' => $cm->id, 'creditid' => $registration->id,
            'returnurl' => $listurl->out_as_local_url(false),
        ]);

        $table->data[] = [
            $registration->studentfullname,
            format_string($registration->coursename),
            ep_studyyear_label($registration->studyyear),
            userdate($registration->timecreated, get_string('strftimedatetimeshort')),
            html_writer::span(ep_status_label($registration->status),
                'badge ' . ep_status_badgeclass($registration->status)),
            (int) $registration->status === EP_STATUS_VALIDATED
                ? ep_format_ects($registration->retainedects) : '-',
            ep_render_actions([
                ($candecide ? ep_credit_decision_label($registration) : get_string('viewdetails', 'mod_ep'))
                    => $decideurl,
            ], $candecide ? 'btn btn-sm btn-primary mr-1 mb-1' : 'btn btn-sm btn-secondary mr-1 mb-1'),
        ];
    }

    echo html_writer::table($table);
    echo $pagingbarhtml;
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('registrationsfollowup', 'mod_epsynthesis'));
echo epsynthesis_render_navlinks($epsynthesis, $cm, $context, 'registrations');
echo html_writer::tag('p', get_string('registrationsfollowup_help', 'mod_epsynthesis'),
    ['class' => 'text-muted']);

$activities = epsynthesis_get_followup_activities($epsynthesis, $cm, $context, $USER->id);

if (empty($activities)) {
    echo $OUTPUT->notification(get_string('nofollowupactivities', 'mod_epsynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('catalogactivity', 'mod_ep'),
    get_string('origin', 'mod_ep'),
    get_string('ects', 'mod_ep'),
    get_string('studyyearrange', 'mod_ep'),
    get_string('places', 'mod_ep'),
    get_string('registrations', 'mod_ep'),
    get_string('activityteachers', 'mod_ep'),
    get_string('actions', 'mod_ep'),
];

foreach ($activities as $activityid => $row) {
    $activity = $row->activity;

    $name = format_string($activity->name);
    if (!$activity->visible) {
        $name .= ' ' . html_writer::span(get_string('hidden', 'mod_ep'), 'badge badge-secondary');
    }

    $teachers = ep_get_activity_teachers($activityid);

    $table->data[] = [
        $name,
        $row->origin,
        ep_format_ects($activity->ects),
        ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear),
        ep_render_places_cell($activity, $row->counts),
        ep_render_registration_counts($row->counts),
        empty($teachers) ? '-' : implode(', ', array_map('fullname', $teachers)),
        ep_render_actions([
            get_string('viewregistrations', 'mod_epsynthesis') =>
                new moodle_url($baseurl, ['activityid' => $activityid]),
            // Ne mène nulle part sans étudiant à noter : mieux vaut ne pas la proposer que
            // mener à un écran vide.
            get_string('gradebulk', 'mod_ep') => $row->counts->enrolled > 0
                ? new moodle_url('/mod/epsynthesis/activity_validate.php',
                    ['id' => $cm->id, 'activityid' => $activityid,
                        'returnurl' => $baseurl->out_as_local_url(false)])
                : null,
        ], $row->counts->pending > 0 ? 'btn btn-sm btn-primary mr-1 mb-1' : 'btn btn-sm btn-secondary mr-1 mb-1'),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
