<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_ep\task;

/**
 * Reprise des fiches d'EP rédigées avant la version 1.1 : pour chaque EP qui a des versions dans
 * ep_syllabus, une activité « Syllabus du module » est créée dans son cours et les reçoit (voir
 * ep_ensure_activity_syllabus()). Une fiche déjà reprise ne l'est pas deux fois ; une page qui
 * a besoin d'une fiche pas encore reprise la reprend elle-même.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class migrate_syllabi extends \core\task\adhoc_task {
    /**
     * Nom de la tâche.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskmigratesyllabi', 'mod_ep');
    }

    /**
     * Reprend les fiches restantes.
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/ep/locallib.php');
        \core\session\manager::set_user(get_admin());
        $activityids = $DB->get_fieldset_sql('SELECT DISTINCT activityid FROM {ep_syllabus}');
        $done = 0;
        foreach ($activityids as $activityid) {
            $activity = $DB->get_record('ep_activity', ['id' => $activityid]);
            $ep = $activity ? $DB->get_record('ep', ['id' => $activity->epid]) : null;
            if (!$ep || !get_coursemodule_from_instance('ep', $ep->id, 0, false, IGNORE_MISSING)) {
                continue;
            }
            $syllabus = ep_ensure_activity_syllabus($ep, $activity);
            // Fiche déjà créée par une page : seules ses versions restent à reprendre.
            ep_migrate_legacy_syllabus($activity, $syllabus);
            $done++;
        }
        mtrace("mod_ep: {$done} fiche(s) d'EP reprise(s) dans mod_syllabus.");
    }
}
