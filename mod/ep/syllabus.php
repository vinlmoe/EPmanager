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
 * Fiche d'un EP du catalogue : depuis la version 1.1, c'est l'activité « Syllabus du module » de
 * l'EP (mod_syllabus) ; cette adresse y mène. Pour ceux qui peuvent la rédiger, la fiche est
 * créée au besoin ; avec review=1, la DEVE arrive sur la version soumise, où elle tranche.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);
$review = optional_param('review', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);
$activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);

$syllabus = ep_can_edit_syllabus($activity, $context) ? ep_ensure_activity_syllabus($ep, $activity)
    : ep_activity_syllabus_record($activity->id);
if (!$syllabus) {
    $PAGE->set_url(new moodle_url('/mod/ep/syllabus.php', ['id' => $cm->id, 'activityid' => $activity->id]));
    $PAGE->set_context($context);
    $PAGE->set_title(format_string($ep->name));
    $PAGE->set_heading(format_string($course->fullname));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('nosyllabusyet', 'mod_ep'), 'info');
    echo html_writer::link(new moodle_url('/mod/ep/catalog.php', ['id' => $cm->id]), get_string('back'));
    echo $OUTPUT->footer();
    exit;
}
redirect(new moodle_url(
    '/mod/syllabus/view.php',
    ['id' => $syllabus->cmid] + ($review ? ['show' => 'working'] : []),
    $review ? 'review' : null
));
