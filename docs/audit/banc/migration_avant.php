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
 * Banc de migration : sur un site où mod_ep 0.1.0 est installé, crée une activité, des types,
 * un EP du catalogue et des crédits de chaque statut, puis écrit leur état dans un fichier JSON
 * que migration_apres.php compare après la mise à jour.
 *
 * Usage : php migration_avant.php <config.php> <sortie.json>
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require($argv[1]);
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');

\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$course = $gen->create_course(['shortname' => 'MIG' . time()]);
$ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 2]);
$student = $gen->create_user();
$teacher = $gen->create_user();
$gen->enrol_user($student->id, $course->id, 'student');
$gen->enrol_user($teacher->id, $course->id, 'teacher');

$academic = ep_get_type_by_code($ep->id, EP_TYPE_ACADEMIC);
$activity = $DB->insert_record('ep_activity', (object) [
    'epid' => $ep->id, 'typeid' => $academic->id, 'name' => 'Échographie', 'description' => '', 'ects' => 2,
    'minstudyyear' => 0, 'maxstudyyear' => 0, 'capacity' => 0, 'visible' => 1, 'sortorder' => 0,
    'timecreated' => time(), 'timemodified' => time(),
]);
$sport = ep_get_type_by_code($ep->id, EP_TYPE_SPORT);
$credits = [];
foreach ([[EP_STATUS_PENDING, $activity], [EP_STATUS_VALIDATED, $activity], [EP_STATUS_PENDING, null],
          [EP_STATUS_REJECTED, null], [EP_STATUS_CANCELLED, null]] as $i => [$status, $activityid]) {
    $credits[] = $DB->insert_record('ep_credit', (object) [
        'epid' => $ep->id, 'userid' => $student->id, 'typeid' => $activityid ? $academic->id : $sport->id,
        'activityid' => $activityid, 'studyyear' => 2, 'name' => 'Crédit ' . $i, 'description' => '',
        'claimedects' => 2, 'retainedects' => $status === EP_STATUS_VALIDATED ? 1.5 : 0, 'status' => $status,
        'source' => 'student', 'sourceref' => 0, 'validatedby' => $status === EP_STATUS_PENDING ? null : $teacher->id,
        'validatetime' => $status === EP_STATUS_PENDING ? null : time(), 'validatorcomment' => 'Décision ' . $i,
        'timecreated' => time(), 'timemodified' => time(),
    ]);
}
file_put_contents($argv[2], json_encode([
    'epid' => (int) $ep->id,
    'types' => array_values($DB->get_records('ep_type', ['epid' => $ep->id], 'sortorder')),
    'credits' => $credits,
    'teacher' => (int) $teacher->id,
], JSON_PRETTY_PRINT));
echo "Données créées : instance {$ep->id}, " . count($credits) . " crédits.\n";
