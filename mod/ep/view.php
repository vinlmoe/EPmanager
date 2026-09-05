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
 * Vue principale de l'activité mod_ep : tableau de bord de l'étudiant connecté (ses ECTS
 * d'enseignement personnalisé, ce qu'il lui reste à valider, ses demandes en cours). La DEVE et
 * les enseignants sont redirigés vers le tableau de pilotage (dashboard.php), leur page
 * d'atterrissage habituelle.
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
require_capability('mod/ep:view', $context);

$rights = ep_get_user_rights($ep, $context);

if ($rights->viewall || ep_rights_has_validation_scope($rights)) {
    redirect(new moodle_url('/mod/ep/dashboard.php', ['id' => $cm->id]));
}

$PAGE->set_url('/mod/ep/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($ep->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Les EP de type stage sont attribués automatiquement : l'étudiant doit les voir apparaître dès
// que la DEVE a validé le stage correspondant, sans attendre la tâche planifiée de la nuit.
ep_sync_stage_credits_if_due($ep);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($ep->name));

if ($ep->intro) {
    echo $OUTPUT->box(format_module_intro('ep', $ep, $cm->id), 'generalbox mod_introbox', 'epintro');
}

echo ep_render_navlinks($ep, $cm, $context, $rights);

if (has_capability('mod/ep:submit', $context)) {
    echo $OUTPUT->heading(get_string('mycredits', 'mod_ep'), 3);
    ep_print_student_dashboard($ep, $USER->id, $cm, $rights, true);
}

echo $OUTPUT->footer();
