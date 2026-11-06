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
 * Téléchargement de la fiche PDF (syllabus) d'un EP du catalogue.
 *
 * Par défaut, c'est la version publiée qui est éditée : celle que voient les étudiants. Le
 * rédacteur du syllabus et la DEVE peuvent demander la version de travail (version=working),
 * pour relire une proposition sur papier avant de la soumettre ou de la publier.
 *
 * Un EP qui n'est pas au catalogue (brouillon, proposé, archivé) n'est accessible qu'à ceux qui
 * peuvent en rédiger le syllabus et à la DEVE : une proposition non publiée ne doit pas circuler.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);
$version = optional_param('version', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

// L'EP est relu avec l'instance en condition : un identifiant d'une autre activité ne doit pas
// pouvoir être édité depuis celle-ci.
$activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);

$canedit = ep_can_edit_syllabus($activity, $context) || has_capability('mod/ep:manage', $context);
if (!ep_activity_is_listed($activity) && !$canedit) {
    throw new required_capability_exception($context, 'mod/ep:propose', 'nopermissions', '');
}

if ($version === 'working') {
    if (!$canedit) {
        throw new required_capability_exception($context, 'mod/ep:propose', 'nopermissions', '');
    }
    // Sans révision en cours, la version de travail est la version publiée elle-même.
    $syllabus = ep_get_working_syllabus($activity->id) ?? ep_get_syllabus($activity->id);
} else {
    $syllabus = ep_get_syllabus($activity->id);
}

$type = $DB->get_record('ep_type', ['id' => $activity->typeid, 'epid' => $ep->id]) ?: null;
$teachers = ep_get_activity_teachers($activity->id);

// L'édition d'une fiche longue (programme de nombreuses séances) peut dépasser les limites
// ordinaires d'une requête.
core_php_time_limit::raise();
raise_memory_limit(MEMORY_EXTRA);

$pdf = new \mod_ep\pdf\syllabus_pdf($ep, $context);
$code = trim((string) $activity->code);
$pdf->SetTitle(format_string($activity->name, true, ['context' => $context, 'escape' => false]));
$pdf->add_activity($activity, $syllabus, $type, $teachers);
$pdf->Output(clean_filename('syllabus-' . ($code !== '' ? $code : $activity->name) . '.pdf'), 'D');
