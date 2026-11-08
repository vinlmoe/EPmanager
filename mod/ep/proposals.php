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
 * Propositions d'EP et révisions de syllabus : ce que la DEVE a à examiner, les propositions et
 * révisions en cours de l'utilisateur, et les EP dont il est responsable, dont il peut proposer
 * une mise à jour.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$canpropose = has_capability('mod/ep:propose', $context);
$canmanage = has_capability('mod/ep:manage', $context);
if (!$canpropose && !$canmanage) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('proposals', 'mod_ep'));
}

$baseurl = new moodle_url('/mod/ep/proposals.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('proposals', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$states = ep_activity_state_options();

/**
 * Tableau des propositions et fiches en cours.
 *
 * @param array $rows Lignes de ep_get_proposals().
 * @param stdClass $cm
 * @param context $context
 * @param array $authors userid => stdClass
 * @return string HTML
 */
function ep_proposals_table(array $rows, stdClass $cm, context $context, array $authors): string {
    $states = ep_activity_state_options();
    $head = [
        get_string('catalogactivity', 'mod_ep'),
        get_string('activitystate', 'mod_ep'),
        get_string('syllabusversionlabel', 'mod_ep'),
        get_string('syllabusstatus', 'mod_ep'),
        get_string('syllabusauthor', 'mod_ep'),
        get_string('submittedon', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ];
    $cells = [];
    foreach ($rows as $row) {
        $activity = $row->activity;
        $syllabus = $row->syllabus;
        $params = ['id' => $cm->id, 'activityid' => $activity->id];
        $status = $syllabus->status ?? 'draft';
        $editable = in_array($status, ['draft', 'returned'], true) && ep_can_edit_syllabus($activity, $context);
        // La DEVE tranche sous la fiche elle-même ; les autres la lisent ou la rédigent.
        $open = new moodle_url('/mod/ep/syllabus.php', $params + ($status === 'submitted' ? ['review' => 1] : []));
        $name = html_writer::link($open, format_string($activity->name));
        if (trim((string) $activity->code) !== '') {
            $name .= ' ' . html_writer::span(s($activity->code), 'text-muted small');
        }
        $author = !empty($syllabus->authorid) && isset($authors[$syllabus->authorid])
            ? fullname($authors[$syllabus->authorid]) : '-';
        $cells[] = [
            $name,
            $states[$activity->state] ?? s($activity->state),
            $syllabus ? (int) $syllabus->version : '-',
            html_writer::span(
                $syllabus ? get_string('syllabusstatus_' . $status, 'mod_ep') : get_string('nosyllabusyet', 'mod_ep'),
                $status === 'submitted' ? 'badge badge-warning'
                    : ($status === 'returned' ? 'badge badge-danger' : 'badge badge-secondary')
            ),
            s($author),
            !empty($syllabus->timesubmitted) ? userdate($syllabus->timesubmitted, get_string('strftimedatetimeshort')) : '-',
            ep_render_actions([
                ($status === 'submitted' && has_capability('mod/ep:manage', $context)
                    ? get_string('review', 'mod_ep') : get_string('syllabus', 'mod_ep')) => $open,
                get_string('editsyllabus', 'mod_ep') => $editable ? new moodle_url('/mod/ep/syllabus_edit.php', $params) : null,
            ]),
        ];
    }
    return ep_render_paged_table($head, $cells);
}

$rows = ep_get_proposals($ep, $context, $USER->id);
$toreview = $canmanage ? array_values(array_filter($rows, fn($row) => ($row->syllabus->status ?? '') === 'submitted')) : [];
$mine = array_values(array_filter($rows, fn($row) => !in_array($row, $toreview, true)));

$authorids = array_unique(array_filter(array_map(fn($row) => (int) ($row->syllabus->authorid ?? 0), $rows)));
$authors = $authorids ? $DB->get_records_list('user', 'id', $authorids) : [];

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('proposals', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/view.php', ['id' => $cm->id]), get_string('back'));
echo html_writer::div(get_string('proposals_help', 'mod_ep'), 'text-muted mt-2 mb-3');
echo html_writer::div(ep_render_actions([
    get_string('proposenew', 'mod_ep') => new moodle_url('/mod/ep/syllabus_edit.php', ['id' => $cm->id, 'activityid' => 0]),
], 'btn btn-primary mr-1 mb-1', ''), 'mb-3');

if ($canmanage) {
    echo $OUTPUT->heading(get_string('proposalstoreview', 'mod_ep'), 3);
    if ($toreview) {
        echo ep_proposals_table($toreview, $cm, $context, $authors);
    } else {
        echo $OUTPUT->notification(get_string('noproposals', 'mod_ep'), 'info');
    }
}

echo $OUTPUT->heading(get_string('myproposals', 'mod_ep'), 3);
if ($mine) {
    echo ep_proposals_table($mine, $cm, $context, $authors);
} else {
    echo $OUTPUT->notification(get_string('noproposals', 'mod_ep'), 'info');
}

$responsible = $canpropose ? ep_get_responsible_activities($ep->id, $USER->id) : [];
if ($responsible) {
    echo $OUTPUT->heading(get_string('myresponsibleactivities', 'mod_ep'), 3);
    $published = ep_get_published_syllabi(array_keys($responsible));
    $head = [
        get_string('catalogactivity', 'mod_ep'),
        get_string('activitystate', 'mod_ep'),
        get_string('syllabusstatus_published', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ];
    $cells = [];
    foreach ($responsible as $activity) {
        $params = ['id' => $cm->id, 'activityid' => $activity->id];
        $canupdate = $activity->state !== EP_ACTIVITY_ARCHIVED && ep_can_edit_syllabus($activity, $context);
        $updatelabel = isset($published[$activity->id])
            ? get_string('proposeupdate', 'mod_ep') : get_string('editsyllabus', 'mod_ep');
        $cells[] = [
            html_writer::link(new moodle_url('/mod/ep/syllabus.php', $params), format_string($activity->name)),
            $states[$activity->state] ?? s($activity->state),
            isset($published[$activity->id])
                ? get_string('syllabusversion', 'mod_ep', (int) $published[$activity->id]->version) : '-',
            ep_render_actions([
                $updatelabel => $canupdate ? new moodle_url('/mod/ep/syllabus_edit.php', $params) : null,
            ]),
        ];
    }
    echo ep_render_paged_table($head, $cells);
}

echo $OUTPUT->footer();
