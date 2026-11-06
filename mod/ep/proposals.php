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
 * Tableau d'une liste de propositions : l'EP, son état, la version de travail et son état, son
 * auteur, sa date de soumission et les actions possibles.
 *
 * @param array $rows Lignes {activity, syllabus} de ep_get_proposals().
 * @param stdClass $cm
 * @param context $context
 * @param array $authors userid => stdClass user
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
        $syllabusurl = new moodle_url('/mod/ep/syllabus.php', $params);
        $editable = in_array($syllabus->status, ['draft', 'returned'], true) && ep_can_edit_syllabus($activity, $context);

        $name = html_writer::link($syllabusurl, format_string($activity->name));
        if (trim((string) $activity->code) !== '') {
            $name .= ' ' . html_writer::span(s($activity->code), 'text-muted small');
        }
        $author = !empty($syllabus->authorid) && isset($authors[$syllabus->authorid])
            ? fullname($authors[$syllabus->authorid]) : '-';
        $cells[] = [
            $name,
            $states[$activity->state] ?? s($activity->state),
            (int) $syllabus->version,
            html_writer::span(
                get_string('syllabusstatus_' . $syllabus->status, 'mod_ep'),
                $syllabus->status === 'submitted' ? 'badge badge-warning'
                    : ($syllabus->status === 'returned' ? 'badge badge-danger' : 'badge badge-secondary')
            ),
            s($author),
            !empty($syllabus->timesubmitted) ? userdate($syllabus->timesubmitted, get_string('strftimedatetimeshort')) : '-',
            ep_render_actions([
                get_string('syllabus', 'mod_ep') => $syllabusurl,
                get_string('editsyllabus', 'mod_ep') => $editable ? new moodle_url('/mod/ep/syllabus_edit.php', $params) : null,
            ]),
        ];
    }
    return ep_render_paged_table($head, $cells);
}

$rows = ep_get_proposals($ep, $context, $USER->id);
// La DEVE voit à part ce qui lui est soumis ; tout le reste (ses propres brouillons, ou pour un
// enseignant toutes ses propositions et révisions) va dans « mes propositions ».
$toreview = $canmanage ? array_values(array_filter($rows, fn($row) => $row->syllabus->status === 'submitted')) : [];
$mine = array_values(array_filter($rows, fn($row) => !in_array($row, $toreview, true)));

// Auteurs des versions, lus en une requête.
$authorids = array_unique(array_filter(array_map(fn($row) => (int) $row->syllabus->authorid, $rows)));
$authors = $authorids ? $DB->get_records_list('user', 'id', $authorids) : [];

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

// EP dont l'utilisateur est responsable : accès à leur fiche, et proposition d'une mise à jour
// quand aucune révision n'est en cours (sinon, elle figure déjà dans la liste ci-dessus).
$responsible = $canpropose ? ep_get_responsible_activities($ep->id, $USER->id) : [];
if ($responsible) {
    echo $OUTPUT->heading(get_string('myresponsibleactivities', 'mod_ep'), 3);

    // Versions publiées et de travail de ces EP, en une requête.
    [$insql, $params] = $DB->get_in_or_equal(array_keys($responsible), SQL_PARAMS_NAMED, 'act');
    $versions = $DB->get_records_select(
        'ep_syllabus',
        "activityid $insql AND status IN ('draft', 'submitted', 'returned', 'published')",
        $params,
        'version ASC',
        'id, activityid, status, version'
    );
    $publishedversion = [];
    $hasworking = [];
    foreach ($versions as $version) {
        if ($version->status === 'published') {
            $publishedversion[$version->activityid] = (int) $version->version;
        } else {
            $hasworking[$version->activityid] = true;
        }
    }

    $head = [
        get_string('catalogactivity', 'mod_ep'),
        get_string('activitystate', 'mod_ep'),
        get_string('syllabusstatus_published', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ];
    $cells = [];
    foreach ($responsible as $activity) {
        $params = ['id' => $cm->id, 'activityid' => $activity->id];
        $canupdate = empty($hasworking[$activity->id]) && $activity->state !== EP_ACTIVITY_ARCHIVED
            && ep_can_edit_syllabus($activity, $context);
        $updatelabel = isset($publishedversion[$activity->id])
            ? get_string('proposeupdate', 'mod_ep') : get_string('editsyllabus', 'mod_ep');
        $cells[] = [
            html_writer::link(new moodle_url('/mod/ep/syllabus.php', $params), format_string($activity->name)),
            $states[$activity->state] ?? s($activity->state),
            isset($publishedversion[$activity->id])
                ? get_string('syllabusversion', 'mod_ep', $publishedversion[$activity->id]) : '-',
            ep_render_actions([
                get_string('syllabus', 'mod_ep') => new moodle_url('/mod/ep/syllabus.php', $params),
                $updatelabel => $canupdate ? new moodle_url('/mod/ep/syllabus_edit.php', $params) : null,
            ]),
        ];
    }
    echo ep_render_paged_table($head, $cells);
}

echo $OUTPUT->footer();
