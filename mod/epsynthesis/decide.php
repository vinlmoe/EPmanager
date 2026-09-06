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
 * Décision du responsable sur une inscription à un EP académique, prise depuis la synthèse :
 * accepter l'inscription, puis, à la fin de l'EP, valider les ECTS — ou refuser, avec son motif.
 *
 * Cet écran double celui de mod_ep (validate.php) parce qu'un EP partagé s'adresse à plusieurs
 * promotions : son responsable n'est pas nécessairement inscrit dans le cours de l'étudiant, et
 * n'aurait donc pas accès à l'activité d'origine. Le formulaire et le traitement sont ceux de
 * mod_ep (voir ep_render_credit_decision_form() et ep_handle_credit_decision()) : les deux écrans
 * ne peuvent pas diverger.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$creditid = required_param('creditid', PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$epsynthesis = $DB->get_record('epsynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:view', $context);

$credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);

// Seules les inscriptions au catalogue se traitent ici : une déclaration hors catalogue relève de
// l'enseignant référent de l'étudiant, dans l'activité de sa promotion.
if (!ep_credit_is_registration($credit)) {
    throw new moodle_exception('errornotaregistration', 'mod_epsynthesis');
}

$followup = epsynthesis_get_followup_activity($epsynthesis, $cm, $context, $USER->id, $credit->activityid);
if (!$followup) {
    throw new moodle_exception('nopermissions', 'error', '',
        get_string('registrationsfollowup', 'mod_epsynthesis'));
}
$activity = $followup->activity;

$ep = $DB->get_record('ep', ['id' => $credit->epid], '*', MUST_EXIST);
$epcm = ep_get_cm($ep);
if (!$epcm) {
    throw new moodle_exception('nopermissions', 'error', '',
        get_string('registrationsfollowup', 'mod_epsynthesis'));
}

$candecide = epsynthesis_can_decide($credit, $followup, $epcm->id, $USER->id);

$backurl = $returnurlparam !== ''
    ? new moodle_url($returnurlparam)
    : new moodle_url('/mod/epsynthesis/registrations.php', ['id' => $cm->id, 'activityid' => $activity->id]);
$pageurl = new moodle_url('/mod/epsynthesis/decide.php',
    ['id' => $cm->id, 'creditid' => $credit->id, 'returnurl' => $returnurlparam]);

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($epsynthesis->name) . ' - ' . get_string('creditdetail', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($candecide) {
    ep_handle_credit_decision($credit, $backurl);
}

$student = $DB->get_record('user', ['id' => $credit->userid], '*', MUST_EXIST);
$type = $DB->get_record('ep_type', ['id' => $credit->typeid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('creditdetail', 'mod_ep'));
echo html_writer::link($backurl, get_string('back'));

echo ep_render_credit_summary($credit, $type, $student);
// La promotion de l'étudiant, et non le cours de suivi : sur un EP partagé, c'est justement ce
// qui distingue une inscription d'une autre.
$studentcourse = get_course($ep->course);
echo html_writer::div(get_string('registrationorigin', 'mod_epsynthesis', (object) [
    'activity' => format_string($activity->name),
    'course' => format_string($studentcourse->fullname),
]), 'text-muted mb-3');

$counts = ep_get_activity_registration_counts($activity->id);
echo ep_render_activity_occupancy($activity, $counts);

if ($candecide) {
    echo $OUTPUT->heading(get_string('decision', 'mod_ep'), 4);

    if (ep_credit_is_registration_step($credit) && !empty($activity->capacity)
            && $counts->taken >= $activity->capacity) {
        echo $OUTPUT->notification(get_string('acceptbeyondcapacity', 'mod_ep'), 'warning');
    }
    echo ep_render_credit_decision_form($pageurl, $credit);
} else if (ep_credit_awaits_decision($credit)) {
    echo $OUTPUT->notification(get_string('decisionnotyours', 'mod_epsynthesis'), 'info');
} else {
    echo $OUTPUT->notification(get_string('creditalreadydecided', 'mod_ep'), 'info');
}

echo $OUTPUT->footer();
