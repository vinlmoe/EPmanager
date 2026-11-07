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

namespace mod_syllabus\task;

/**
 * Évaluations qui viennent d'ouvrir : questions figées (les questions communes du moment sont
 * copiées dans le module) et étudiants prévenus, une fois par ouverture.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class open_evaluations extends \core\task\scheduled_task {
    /**
     * Nom de la tâche.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskopenevaluations', 'mod_syllabus');
    }

    /**
     * Exécution.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

        $now = time();
        $rs = $DB->get_recordset_select('syllabus', 'evalopen > 0 AND evalopen <= :now AND evalnotified <> evalopen '
            . 'AND (evalclose = 0 OR evalclose > :now2)', ['now' => $now, 'now2' => $now]);
        foreach ($rs as $syllabus) {
            $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $syllabus->course, false, IGNORE_MISSING);
            if (!$cm || !$cm->visible) {
                continue;
            }
            syllabus_freeze_questions($syllabus);
            // Marqué avant l'envoi : une exécution interrompue ne renvoie pas les messages déjà
            // partis (au pire, quelques étudiants ne sont pas prévenus ; le recueil reste ouvert).
            $DB->set_field('syllabus', 'evalnotified', $syllabus->evalopen, ['id' => $syllabus->id]);
            $sent = syllabus_notify_evaluation_open($syllabus, $cm);
            mtrace("mod_syllabus : « {$syllabus->name} » (cm {$cm->id}), évaluation ouverte, $sent message(s).");
        }
        $rs->close();
    }
}
