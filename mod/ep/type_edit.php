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
 * Création ou modification d'un type d'enseignement personnalisé par la DEVE, sans
 * programmation : libellé et consigne, mode d'acquisition, circuit de validation, évaluation,
 * justificatifs, plafonds et, pour un type alimenté par les stages, barème et portée.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\form\type_form;

$id = required_param('id', PARAM_INT);
$typeid = optional_param('typeid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$returnurl = new moodle_url('/mod/ep/types.php', ['id' => $cm->id]);
$pageurl = new moodle_url('/mod/ep/type_edit.php', ['id' => $cm->id, 'typeid' => $typeid]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string($typeid ? 'edittype' : 'addtype', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Le type est relu avec l'instance en condition : un identifiant d'une autre activité ne doit
// pas pouvoir être modifié depuis celle-ci.
$type = $typeid ? $DB->get_record('ep_type', ['id' => $typeid, 'epid' => $ep->id], '*', MUST_EXIST) : null;

$mform = new type_form($pageurl);
if ($type) {
    $data = clone $type;
    $data->typeid = $type->id;
    $data->id = $cm->id;
    foreach (['grademax', 'gradepass', 'maxclaimedects', 'maxects', 'maxectsperyear', 'ectsperday'] as $field) {
        $data->$field = ep_format_ects_input($type->$field);
    }
    $mform->set_data($data);
} else {
    $mform->set_data(['id' => $cm->id, 'typeid' => 0]);
}

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $record = clone $data;
    $record->id = $type ? $type->id : 0;
    unset($record->typeid, $record->submitbutton);
    $wasstage = $type && ep_type_is_stage($type);
    $savedid = ep_save_type($ep->id, $record);

    // Un barème, une portée ou une activation de type stage qui change modifie les crédits
    // automatiques : ils sont recalculés dans la foulée, sans attendre la tâche de la nuit.
    $saved = $DB->get_record('ep_type', ['id' => $savedid], '*', MUST_EXIST);
    if ($wasstage || ep_type_is_stage($saved)) {
        ep_sync_stage_credits($ep);
    }
    redirect($returnurl, get_string('typesaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($type ? 'edittype' : 'addtype', 'mod_ep'));
echo html_writer::link($returnurl, get_string('back'));
$mform->display();
echo $OUTPUT->footer();
