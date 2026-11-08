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
 * Fiche d'un EP en PDF : depuis la version 1.1, celle de son activité « Syllabus du module »
 * (mod_syllabus), qui contrôle elle-même qui peut lire une version non publiée.
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
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($cm->course, true, $cm);
require_capability('mod/ep:view', context_module::instance($cm->id));
$activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
$syllabus = ep_activity_syllabus_record($activity->id);
if (!$syllabus) {
    throw new moodle_exception('nosyllabusyet', 'mod_ep');
}
$working = $version === 'working' ? ep_get_working_syllabus($activity->id) : null;
redirect(new moodle_url('/mod/syllabus/pdf.php', ['id' => $syllabus->cmid] + ($working ? ['versionid' => $working->id] : [])));
