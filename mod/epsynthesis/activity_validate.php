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
 * Notation groupée d'un EP académique, prise depuis la synthèse : comme
 * mod_ep/activity_validate.php, une note sur 20 (qui décide seule de la validation ou du refus)
 * ou une case « Valider » directement, ligne par ligne, pour chaque étudiant dont l'inscription a
 * été acceptée.
 *
 * Cet écran double celui de mod_ep parce qu'un EP partagé s'adresse à plusieurs promotions : son
 * responsable n'est pas nécessairement inscrit dans le cours de chaque étudiant, et n'aurait donc
 * pas accès à l'activité d'origine. Le rendu et le traitement sont ceux de mod_ep (voir
 * ep_render_activity_grading_form() et ep_process_activity_grading()) : les deux écrans ne
 * peuvent pas diverger.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$epsynthesis = $DB->get_record('epsynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:view', $context);

$followup = epsynthesis_get_followup_activity($epsynthesis, $cm, $context, $USER->id, $activityid);
if (!$followup) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('gradebulk', 'mod_ep'));
}
$activity = $followup->activity;

$backurl = $returnurlparam !== ''
    ? new moodle_url($returnurlparam)
    : new moodle_url('/mod/epsynthesis/registrations.php', ['id' => $cm->id, 'activityid' => $activity->id]);
$pageurl = new moodle_url('/mod/epsynthesis/activity_validate.php',
    ['id' => $cm->id, 'activityid' => $activity->id, 'returnurl' => $returnurlparam]);

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($epsynthesis->name) . ' - ' . get_string('gradebulk', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if (data_submitted() && confirm_sesskey()) {
    $grades = optional_param_array('grade', [], PARAM_RAW);
    $directvalidate = optional_param_array('directvalidate', [], PARAM_INT);
    $processed = ep_process_activity_grading($activity->id, $grades, $directvalidate, $USER->id);
    redirect($backurl, get_string('gradingdone', 'mod_ep', $processed), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradebulk', 'mod_ep') . ' — ' . format_string($activity->name));
echo html_writer::link($backurl, get_string('back'));

$registrations = ep_get_activity_registrations($activity->id, ['status' => EP_STATUS_ENROLLED], 'student', 'ASC');

if (empty($registrations)) {
    echo $OUTPUT->notification(get_string('nogradableregistrations', 'mod_ep'), 'info');
} else {
    echo ep_render_activity_grading_form($pageurl, $registrations);
}

echo $OUTPUT->footer();
