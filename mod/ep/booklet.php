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
 * Livret PDF des EP du catalogue : page de titre, sommaire, puis la fiche de chaque EP publié ou
 * clos, dans l'ordre du catalogue. C'est le document que la DEVE diffuse en début d'année et que
 * les étudiants consultent pour choisir leurs EP ; il est donc ouvert à tous les participants.
 *
 * Seules les versions publiées des syllabus y figurent : une révision en cours n'engage personne
 * tant que la DEVE ne l'a pas publiée.
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

// Un catalogue de plusieurs dizaines d'EP produit un document de plusieurs centaines de pages :
// les limites ordinaires d'une requête n'y suffisent pas.
core_php_time_limit::raise();
raise_memory_limit(MEMORY_EXTRA);

// Responsables et types sont chargés en une requête chacun pour tout le livret, plutôt qu'une
// par fiche.
$activities = ep_get_activities($ep->id, true);
$allteachers = ep_get_activities_teachers($ep->id);
$types = ep_get_types($ep->id);

// Versions publiées de tous les syllabus de l'instance, en une requête : il n'y en a qu'une par EP
// (la publication d'une révision remplace la précédente), mais le tri par version garantit que
// la plus récente l'emporte si une ancienne était restée publiée.
$syllabi = [];
$sql = "SELECT s.*
          FROM {ep_syllabus} s
          JOIN {ep_activity} a ON a.id = s.activityid
         WHERE a.epid = :epid AND s.status = :status
      ORDER BY s.version ASC, s.id ASC";
foreach ($DB->get_records_sql($sql, ['epid' => $ep->id, 'status' => 'published']) as $syllabus) {
    $syllabi[(int) $syllabus->activityid] = $syllabus;
}

$title = get_string('booklettitle', 'mod_ep');
$pdf = new \mod_ep\pdf\syllabus_pdf($ep, $context);
$pdf->SetTitle($title);
$pdf->add_title_page($title, [
    format_string($ep->name, true, ['context' => $context, 'escape' => false]),
    format_string($course->fullname, true, ['context' => context_course::instance($course->id), 'escape' => false]),
    userdate(time(), get_string('strftimedate', 'langconfig')),
]);

foreach ($activities as $activity) {
    $pdf->add_activity(
        $activity,
        $syllabi[(int) $activity->id] ?? null,
        $types[$activity->typeid] ?? null,
        $allteachers[$activity->id] ?? []
    );
}

// Le sommaire s'insère après la page de titre ; sans EP, il n'y aurait rien à y lister.
if ($activities) {
    $pdf->add_contents_page(get_string('bookletcontents', 'mod_ep'), 2);
}

$pdf->Output(clean_filename(get_string('booklettitle', 'mod_ep') . ' - ' . $ep->name . '.pdf'), 'D');
