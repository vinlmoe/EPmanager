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
 * Écran de validation : d'abord les demandes qui attendent la décision de l'utilisateur
 * (inscriptions aux EP dont il est responsable, déclarations des étudiants dont il est référent —
 * toutes les demandes en attente pour la DEVE), puis la liste filtrable de tout ce qui est dans
 * son périmètre, pour retrouver une demande déjà traitée.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$filtertypeid = optional_param('typeid', 0, PARAM_INT);
$filterstatus = optional_param('status', '', PARAM_RAW);
$filteryear = optional_param('studyyear', '', PARAM_RAW);
$tsort = optional_param('tsort', 'timecreated', PARAM_ALPHA);
$tdir = optional_param('tdir', 'DESC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

$rights = ep_get_user_rights($ep, $context);
if (!ep_rights_has_validation_scope($rights)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('validation', 'mod_ep'));
}

$baseurl = new moodle_url('/mod/ep/credits.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('validation', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$types = ep_get_types($ep->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('validation', 'mod_ep'));
echo ep_render_navlinks($ep, $cm, $context, $rights);

// 1. Ce qui attend une décision de l'utilisateur : c'est pour cela qu'il ouvre cette page.
$awaiting = ep_get_credits_awaiting($ep, $rights, 'timecreated', 'ASC');
echo $OUTPUT->heading(get_string('awaitingmydecision', 'mod_ep'), 4);
if (empty($awaiting)) {
    echo $OUTPUT->notification(get_string('nopendingcredits', 'mod_ep'), 'info');
} else {
    $students = ep_get_credit_users($awaiting);
    $awaitingtable = new html_table();
    $awaitingtable->head = [
        get_string('student', 'mod_ep'),
        get_string('type', 'mod_ep'),
        get_string('creditname', 'mod_ep'),
        get_string('studyyear', 'mod_ep'),
        get_string('claimedects', 'mod_ep'),
        get_string('submittedon', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ];
    foreach ($awaiting as $credit) {
        $student = $students[$credit->userid] ?? null;
        $type = $types[$credit->typeid] ?? null;
        $awaitingtable->data[] = [
            $student ? fullname($student) : '-',
            $type ? format_string($type->name) : '-',
            format_string($credit->name),
            ep_studyyear_label($credit->studyyear),
            ep_format_ects($credit->claimedects),
            userdate($credit->timecreated, get_string('strftimedatetimeshort')),
            ep_render_actions([
                get_string('validatecredit', 'mod_ep') => new moodle_url('/mod/ep/validate.php',
                    ['id' => $cm->id, 'creditid' => $credit->id,
                        'returnurl' => $baseurl->out_as_local_url(false)]),
            ], 'btn btn-sm btn-primary mr-1 mb-1'),
        ];
    }
    echo html_writer::table($awaitingtable);
}

// 2. Tout le périmètre de l'utilisateur, filtrable : pour retrouver une demande déjà traitée,
// vérifier ce qui a été refusé, etc.
echo $OUTPUT->heading(get_string('allcreditsinscope', 'mod_ep'), 4);

$listurl = new moodle_url($baseurl, [
    'search' => $search, 'typeid' => $filtertypeid, 'status' => $filterstatus,
    'studyyear' => $filteryear, 'tsort' => $tsort, 'tdir' => $tdir,
]);
echo ep_render_list_filters($listurl, $types, [
    'search' => $search, 'typeid' => $filtertypeid, 'status' => $filterstatus, 'studyyear' => $filteryear,
]);

$filters = [
    'search' => $search,
    'typeid' => $filtertypeid,
    'status' => $filterstatus,
    'studyyear' => $filteryear,
];
$allcredits = $rights->validatedeve
    ? ep_get_filtered_credits($ep->id, $filters, $tsort, $tdir)
    : ep_get_filtered_credits($ep->id, $filters, $tsort, $tdir, $rights->referentids, $rights->responsibleids);

if (empty($allcredits)) {
    echo $OUTPUT->notification(get_string('nocredits', 'mod_ep'), 'info');
    echo $OUTPUT->footer();
    exit;
}

[$credits, $pagingbarhtml] = ep_paginate($allcredits, $page, $listurl);
$students = ep_get_credit_users($credits);

$table = new html_table();
$table->head = [
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
    $student = $students[$credit->userid] ?? null;
    $type = $types[$credit->typeid] ?? null;
    $canvalidate = ep_rights_can_validate($rights, $credit) && (int) $credit->status === EP_STATUS_PENDING;
    $label = $canvalidate ? get_string('validatecredit', 'mod_ep') : get_string('viewdetails', 'mod_ep');

    $table->data[] = [
        $student ? fullname($student) : '-',
        $type ? format_string($type->name) : '-',
        format_string($credit->name),
        ep_studyyear_label($credit->studyyear),
        ep_format_ects($credit->claimedects),
        (int) $credit->status === EP_STATUS_VALIDATED ? ep_format_ects($credit->retainedects) : '-',
        html_writer::span(ep_status_label($credit->status), 'badge ' . ep_status_badgeclass($credit->status)),
        ep_render_actions([
            $label => new moodle_url('/mod/ep/validate.php',
                ['id' => $cm->id, 'creditid' => $credit->id,
                    'returnurl' => $listurl->out_as_local_url(false)]),
        ]),
    ];
}

echo html_writer::table($table);
echo $pagingbarhtml;

echo $OUTPUT->footer();
