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
 * Fiche d'un EP du catalogue : ses caractéristiques et son syllabus en vigueur, ouverts à tous
 * dès que l'EP est au catalogue. Ceux qui rédigent la fiche et la DEVE y voient aussi la version
 * en cours de révision ; la DEVE y statue sur une version soumise (publier, renvoyer, refuser).
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\output\syllabus_view;

$id = required_param('id', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

$activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
$canedit = ep_can_edit_syllabus($activity, $context);
$canmanage = has_capability('mod/ep:manage', $context);
// Un EP qui n'est pas (ou plus) au catalogue n'est visible que de ceux qui le rédigent ou le
// publient : une proposition en cours n'a pas à être lue par les étudiants.
$listed = in_array($activity->state, [EP_ACTIVITY_PUBLISHED, EP_ACTIVITY_CLOSED], true);
if (!$listed && !$canedit && !$canmanage) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('syllabus', 'mod_ep'));
}

$baseurl = new moodle_url('/mod/ep/syllabus.php', ['id' => $cm->id, 'activityid' => $activity->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('syllabusof', 'mod_ep', format_string($activity->name)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$labelsep = get_string('labelsep', 'langconfig');

$published = ep_get_syllabus($activity->id);
$working = ($canedit || $canmanage) ? ep_get_working_syllabus($activity->id) : null;

// Décision de la DEVE sur la version soumise. L'identifiant de la version est repris du
// formulaire : si une autre version a été soumise entre-temps, la décision ne s'y applique pas.
$decision = optional_param('decision', '', PARAM_ALPHA);
if ($decision !== '' && data_submitted()) {
    require_sesskey();
    require_capability('mod/ep:manage', $context);
    if (!in_array($decision, ['publish', 'return', 'reject'], true)) {
        redirect($baseurl);
    }
    $syllabusid = required_param('syllabusid', PARAM_INT);
    $comment = optional_param('reviewcomment', '', PARAM_TEXT);
    if (
        !$working || (int) $working->id !== $syllabusid || $working->status !== 'submitted'
            || !ep_review_syllabus($ep, $activity, $working, $USER->id, $decision, trim($comment))
    ) {
        redirect($baseurl, get_string('errorsyllabusnoteditable', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(
        $baseurl,
        get_string('reviewdone', 'mod_ep', get_string('syllabusdecision_' . $decision, 'mod_ep')),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$types = ep_get_types($ep->id);
$type = $types[$activity->typeid] ?? null;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('syllabusof', 'mod_ep', format_string($activity->name)));
// Retour vers la liste d'où l'on vient le plus souvent : les propositions pour ceux qui rédigent ou
// publient, le catalogue pour les autres.
$backurl = ($canedit || $canmanage)
    ? new moodle_url('/mod/ep/proposals.php', ['id' => $cm->id])
    : new moodle_url('/mod/ep/catalog.php', ['id' => $cm->id]);
echo html_writer::link($backurl, get_string('back'));

// Actions : fiche PDF de la version en vigueur, rédaction. Une version soumise n'est plus
// modifiable, et un EP archivé n'a plus de fiche à rédiger, sauf pour la DEVE.
$editlabel = null;
if (
    $canedit && (!$working || $working->status !== 'submitted')
        && ($activity->state !== EP_ACTIVITY_ARCHIVED || $canmanage)
) {
    $editlabel = ($published && !$working) ? get_string('proposeupdate', 'mod_ep') : get_string('editsyllabus', 'mod_ep');
}
$actions = [
    get_string('downloadsyllabus', 'mod_ep') => $published
        ? new moodle_url('/mod/ep/syllabus_pdf.php', ['id' => $cm->id, 'activityid' => $activity->id])
        : null,
];
if ($editlabel !== null) {
    $actions[$editlabel] = new moodle_url('/mod/ep/syllabus_edit.php', ['id' => $cm->id, 'activityid' => $activity->id]);
}
echo html_writer::div(ep_render_actions($actions, 'btn btn-secondary mr-1 mb-1', ''), 'mt-2 mb-3');

// 1. Caractéristiques ; l'état de l'EP et l'auteur de la proposition ne concernent que ceux qui
// suivent la publication.
echo syllabus_view::render_characteristics($activity, $type, $canedit || $canmanage);

// 2. Version de travail signalée en tête, pour qu'on ne prenne pas la version en vigueur pour la
// dernière rédigée.
if ($working) {
    echo $OUTPUT->notification(get_string('pendingrevision', 'mod_ep', (object) [
        'version' => (int) $working->version,
        'status' => get_string('syllabusstatus_' . $working->status, 'mod_ep'),
    ]), 'info');
}

// 3. Version en vigueur.
if ($working) {
    echo $OUTPUT->heading(get_string('comparepublished', 'mod_ep'), 3);
}
if ($published) {
    $meta = get_string('syllabusversion', 'mod_ep', (int) $published->version);
    if (!empty($published->timereviewed)) {
        $meta .= ' - ' . userdate($published->timereviewed, get_string('strftimedate'));
    }
    echo html_writer::div($meta, 'text-muted small mb-2');
    echo syllabus_view::render_version($activity, $published);
} else {
    echo $OUTPUT->notification(get_string('nosyllabus', 'mod_ep'), 'info');
}

// 4. Version de travail, pour ceux qui la rédigent et la DEVE.
if ($working) {
    echo $OUTPUT->heading(get_string('comparepending', 'mod_ep'), 3, 'mt-4');
    $meta = get_string('syllabusversion', 'mod_ep', (int) $working->version) . ' - '
        . get_string('syllabusstatus_' . $working->status, 'mod_ep');
    if (!empty($working->authorid) && ($author = $DB->get_record('user', ['id' => $working->authorid]))) {
        $meta .= ' - ' . get_string('proposedby', 'mod_ep', fullname($author));
    }
    if (!empty($working->timesubmitted)) {
        $meta .= ' - ' . userdate($working->timesubmitted, get_string('strftimedatetimeshort'));
    }
    echo html_writer::div(s($meta), 'text-muted small mb-2');
    $comment = trim((string) $working->reviewcomment);
    if ($working->status === 'returned' && $comment !== '') {
        echo $OUTPUT->notification(
            html_writer::tag('strong', get_string('reviewcomment', 'mod_ep') . $labelsep) . s($comment),
            'warning'
        );
    }
    echo syllabus_view::render_version($activity, $working);

    // 5. Décision de la DEVE sur une version soumise.
    if ($canmanage && $working->status === 'submitted') {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'mt-4 mb-4']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'syllabusid', 'value' => $working->id]);
        echo html_writer::tag('label', get_string('reviewcomment', 'mod_ep'), ['for' => 'reviewcomment']);
        echo html_writer::tag('textarea', '', [
            'name' => 'reviewcomment', 'id' => 'reviewcomment', 'rows' => 4, 'class' => 'form-control mb-3',
        ]);
        $buttons = [
            'publish' => ['reviewpublish', 'btn btn-primary mr-2'],
            'return' => ['reviewreturn', 'btn btn-secondary mr-2'],
            'reject' => ['reviewreject', 'btn btn-danger'],
        ];
        foreach ($buttons as $value => [$label, $class]) {
            echo html_writer::tag('button', get_string($label, 'mod_ep'), [
                'type' => 'submit', 'name' => 'decision', 'value' => $value, 'class' => $class,
            ]);
        }
        echo html_writer::end_tag('form');
    }
}

echo $OUTPUT->footer();
