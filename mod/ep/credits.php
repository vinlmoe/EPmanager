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
$apage = optional_param('apage', 0, PARAM_INT);

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

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('validation', 'mod_ep'));
echo ep_render_navlinks($ep, $cm, $context, $rights);

// 0. Les EP du catalogue dont l'utilisateur est responsable (tous, pour la DEVE qui n'en a
// aucun en propre) : c'est d'ici qu'il ouvre leur liste de participants, pour inscrire, retirer
// et saisir les notes.
$responsible = $rights->responsibleids
    ? array_intersect_key(ep_get_activities($ep->id), array_flip($rights->responsibleids))
    : [];
if ($responsible) {
    $counts = ep_get_activities_status_counts($ep->id);
    echo $OUTPUT->heading(get_string('myresponsibleactivities', 'mod_ep'), 4);
    $rows = [];
    foreach ($responsible as $activity) {
        $bystatus = $counts[$activity->id] ?? [];
        $active = ($bystatus[EP_STATUS_PENDING] ?? 0) + ($bystatus[EP_STATUS_VALIDATED] ?? 0);
        $rows[] = [
            format_string($activity->name),
            ep_activity_state_options()[$activity->state] ?? '-',
            get_string('participantscount', 'mod_ep', $active)
                . (empty($activity->capacity) ? '' : ' / ' . (int) $activity->capacity),
            $bystatus[EP_STATUS_PENDING] ?? 0,
            ep_render_actions([
                get_string('participants', 'mod_ep') =>
                    new moodle_url('/mod/ep/participants.php', ['id' => $cm->id, 'activityid' => $activity->id]),
            ], 'btn btn-sm btn-primary mr-1 mb-1'),
        ];
    }
    echo ep_render_paged_table([
        get_string('catalogactivity', 'mod_ep'),
        get_string('activitystate', 'mod_ep'),
        get_string('participants', 'mod_ep'),
        get_string('pendingregistrations', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ], $rows);
}

// 1. Ce qui attend une décision de l'utilisateur : c'est pour cela qu'il ouvre cette page.
// File paginée en base (paramètre « apage », la liste complète plus bas ayant « page ») : la
// DEVE d'une grande promotion peut avoir des milliers de demandes en attente.
$awaitingtotal = ep_count_credits_awaiting($ep, $rights);
$awaiting = $awaitingtotal
    ? ep_get_credits_awaiting($ep, $rights, 'timecreated', 'ASC', $apage * EP_LIST_PERPAGE, EP_LIST_PERPAGE)
    : [];
echo $OUTPUT->heading(get_string('awaitingmydecision', 'mod_ep') . ($awaitingtotal ? " ($awaitingtotal)" : ''), 4);
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
                get_string('validatecredit', 'mod_ep') => new moodle_url(
                    '/mod/ep/validate.php',
                    ['id' => $cm->id, 'creditid' => $credit->id,
                    'returnurl' => $baseurl->out_as_local_url(false)]
                ),
            ], 'btn btn-sm btn-primary mr-1 mb-1'),
        ];
    }
    echo html_writer::table($awaitingtable);
    echo ep_render_paging_bar($awaitingtotal, $apage, $baseurl, EP_LIST_PERPAGE, 'apage');
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
// Liste paginée en base : seule la page demandée est chargée, le total sert à la pagination.
$restrictusers = $rights->validatedeve ? null : $rights->referentids;
$restrictactivities = $rights->validatedeve ? null : $rights->responsibleids;
$total = ep_count_filtered_credits($ep->id, $filters, $restrictusers, $restrictactivities);
if ($total === 0) {
    echo $OUTPUT->notification(get_string('nocredits', 'mod_ep'), 'info');
    echo $OUTPUT->footer();
    exit;
}
$credits = ep_get_filtered_credits(
    $ep->id,
    $filters,
    $tsort,
    $tdir,
    $restrictusers,
    $restrictactivities,
    $page * EP_LIST_PERPAGE,
    EP_LIST_PERPAGE
);
$pagingbarhtml = ep_render_paging_bar($total, $page, $listurl);
$students = ep_get_credit_users($credits);

$head = [
    ep_sort_header(get_string('student', 'mod_ep'), 'student', $listurl, $tsort, $tdir),
    ep_sort_header(get_string('type', 'mod_ep'), 'type', $listurl, $tsort, $tdir),
    ep_sort_header(get_string('creditname', 'mod_ep'), 'name', $listurl, $tsort, $tdir),
    get_string('studyyear', 'mod_ep'),
    ep_sort_header(get_string('claimedects', 'mod_ep'), 'ects', $listurl, $tsort, $tdir),
    get_string('retainedects', 'mod_ep'),
    ep_sort_header(get_string('status', 'mod_ep'), 'status', $listurl, $tsort, $tdir),
    get_string('actions', 'mod_ep'),
];

$rows = [];
foreach ($credits as $credit) {
    $student = $students[$credit->userid] ?? null;
    $type = $types[$credit->typeid] ?? null;
    $canvalidate = ep_rights_can_validate($rights, $credit) && (int) $credit->status === EP_STATUS_PENDING;
    $label = $canvalidate ? get_string('validatecredit', 'mod_ep') : get_string('viewdetails', 'mod_ep');

    $rows[] = [
        $student ? fullname($student) : '-',
        $type ? format_string($type->name) : '-',
        format_string($credit->name),
        ep_studyyear_label($credit->studyyear),
        ep_format_ects($credit->claimedects),
        (int) $credit->status === EP_STATUS_VALIDATED ? ep_format_ects($credit->retainedects) : '-',
        ep_render_credit_status($credit->status),
        ep_render_actions([
            $label => new moodle_url(
                '/mod/ep/validate.php',
                ['id' => $cm->id, 'creditid' => $credit->id,
                'returnurl' => $listurl->out_as_local_url(false)]
            ),
        ]),
    ];
}

echo ep_render_paged_table($head, $rows, $pagingbarhtml);

echo $OUTPUT->footer();
