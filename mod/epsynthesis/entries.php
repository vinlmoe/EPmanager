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
 * Reprend l'écran de validation de mod_ep (credits.php) — ce qui attend une décision, puis la
 * liste filtrable de tout le périmètre — combiné sur toutes les activités « Enseignement
 * personnalisé » liées où l'utilisateur connecté a quelque chose à suivre. Page d'atterrissage de
 * l'activité (voir view.php, qui y redirige) ; dashboard.php la complète par le pilotage des
 * étudiants dont il est référent.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$typekey = optional_param('typekey', '', PARAM_RAW);
$filterstatus = optional_param('status', '', PARAM_RAW);
$filteryear = optional_param('studyyear', '', PARAM_RAW);
$tsort = optional_param('tsort', 'timecreated', PARAM_ALPHA);
$tdir = optional_param('tdir', 'DESC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$epsynthesis = $DB->get_record('epsynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:view', $context);

$baseurl = new moodle_url('/mod/epsynthesis/entries.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($epsynthesis->name) . ' - ' . get_string('validation', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($epsynthesis->name));
echo html_writer::link(
    new moodle_url('/mod/epsynthesis/dashboard.php', ['id' => $cm->id]),
    get_string('pilotage', 'mod_ep')
);

if ($epsynthesis->intro) {
    echo $OUTPUT->box(format_module_intro('epsynthesis', $epsynthesis, $cm->id), 'generalbox mod_introbox');
}

echo epsynthesis_render_managelinks_notice($epsynthesis, $cm, $context);

$activelinks = epsynthesis_get_active_links($epsynthesis->id, $USER->id);

if (empty($activelinks)) {
    echo $OUTPUT->notification(get_string('noscope', 'mod_epsynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

epsynthesis_sync_active_links($activelinks);

// 1. Ce qui attend une décision de l'utilisateur, toutes promotions confondues : c'est pour cela
// qu'il ouvre cette page.
$awaiting = epsynthesis_get_credits_awaiting($activelinks);
echo $OUTPUT->heading(get_string('awaitingmydecision', 'mod_ep'), 4);
if (empty($awaiting)) {
    echo $OUTPUT->notification(get_string('nopendingcredits', 'mod_ep'), 'info');
} else {
    $awaitingtable = new html_table();
    $awaitingtable->head = [
        get_string('course'),
        get_string('student', 'mod_ep'),
        get_string('type', 'mod_ep'),
        get_string('creditname', 'mod_ep'),
        get_string('studyyear', 'mod_ep'),
        get_string('claimedects', 'mod_ep'),
        get_string('submittedon', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ];
    foreach ($awaiting as $credit) {
        $awaitingtable->data[] = [
            format_string($credit->coursename),
            $credit->studentfullname,
            $credit->typename,
            format_string($credit->name),
            ep_studyyear_label($credit->studyyear),
            ep_format_ects($credit->claimedects),
            userdate($credit->timecreated, get_string('strftimedatetimeshort')),
            ep_render_actions([
                get_string('validatecredit', 'mod_ep') => new moodle_url(
                    '/mod/ep/validate.php',
                    ['id' => $credit->cmid, 'creditid' => $credit->id,
                    'returnurl' => $baseurl->out_as_local_url(false)]
                ),
            ], 'btn btn-sm btn-primary mr-1 mb-1'),
        ];
    }
    echo html_writer::table($awaitingtable);
}

// 2. Tout le périmètre, filtrable : pour retrouver une demande déjà traitée ou vérifier ce qui a
// été refusé, sans avoir à ouvrir chaque cours l'un après l'autre.
echo $OUTPUT->heading(get_string('allcreditsinscope', 'mod_ep'), 4);

$typeoptions = epsynthesis_get_type_options($activelinks);
$typefilter = epsynthesis_parse_type_filter($typekey, $typeoptions);

$listurl = new moodle_url($baseurl, [
    'search' => $search, 'typekey' => $typekey, 'status' => $filterstatus,
    'studyyear' => $filteryear, 'tsort' => $tsort, 'tdir' => $tdir,
]);
echo epsynthesis_render_list_filters($listurl, $typeoptions, [
    'search' => $search, 'typekey' => $typekey, 'status' => $filterstatus, 'studyyear' => $filteryear,
]);

$allcredits = epsynthesis_get_filtered_credits($activelinks, [
    'search' => $search,
    'typefilter' => $typefilter,
    'status' => $filterstatus,
    'studyyear' => $filteryear,
], $tsort, $tdir);

if (empty($allcredits)) {
    echo $OUTPUT->notification(get_string('nocredits', 'mod_ep'), 'info');
    echo $OUTPUT->footer();
    exit;
}

[$credits, $pagingbarhtml] = ep_paginate($allcredits, $page, $listurl);

$table = new html_table();
$table->head = [
    ep_sort_header(get_string('course'), 'course', $listurl, $tsort, $tdir),
    ep_sort_header(get_string('student', 'mod_ep'), 'student', $listurl, $tsort, $tdir),
    ep_sort_header(get_string('type', 'mod_ep'), 'type', $listurl, $tsort, $tdir),
    ep_sort_header(get_string('creditname', 'mod_ep'), 'name', $listurl, $tsort, $tdir),
    get_string('studyyear', 'mod_ep'),
    ep_sort_header(get_string('claimedects', 'mod_ep'), 'ects', $listurl, $tsort, $tdir),
    get_string('retainedects', 'mod_ep'),
    ep_sort_header(get_string('status', 'mod_ep'), 'status', $listurl, $tsort, $tdir),
    get_string('actions', 'mod_ep'),
];

foreach ($credits as $credit) {
    $link = $activelinks[$credit->cmid];
    $canvalidate = ep_rights_can_validate($link->rights, $credit)
        && (int) $credit->status === EP_STATUS_PENDING;
    $label = $canvalidate ? get_string('validatecredit', 'mod_ep') : get_string('viewdetails', 'mod_ep');

    $table->data[] = [
        format_string($credit->coursename),
        $credit->studentfullname,
        $credit->typename,
        format_string($credit->name),
        ep_studyyear_label($credit->studyyear),
        ep_format_ects($credit->claimedects),
        (int) $credit->status === EP_STATUS_VALIDATED ? ep_format_ects($credit->retainedects) : '-',
        html_writer::span(ep_status_label($credit->status), 'badge ' . ep_status_badgeclass($credit->status)),
        ep_render_actions([
            $label => new moodle_url(
                '/mod/ep/validate.php',
                ['id' => $credit->cmid, 'creditid' => $credit->id,
                'returnurl' => $listurl->out_as_local_url(false)]
            ),
        ]),
    ];
}

echo html_writer::table($table);
echo $pagingbarhtml;

echo $OUTPUT->footer();
