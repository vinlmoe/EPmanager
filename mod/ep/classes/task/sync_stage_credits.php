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

namespace mod_ep\task;

/**
 * Recalcule chaque nuit, pour toutes les instances, les EP de type stage attribués
 * automatiquement d'après les stages complémentaires (EP) validés par la DEVE dans mod_stage.
 *
 * Les pages du module resynchronisent déjà à l'affichage (voir ep_sync_stage_credits_if_due()) :
 * cette tâche garantit que le décompte est juste même pour une instance que personne n'ouvre, et
 * rattrape les stages dévalidés ou repassés en obligatoire entre-temps.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_stage_credits extends \core\task\scheduled_task {
    /**
     * Nom de la tâche, tel qu'affiché dans l'administration des tâches planifiées.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksyncstagecredits', 'mod_ep');
    }

    /**
     * Exécute la synchronisation pour chaque instance de l'activité.
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/ep/locallib.php');

        foreach ($DB->get_records('ep') as $ep) {
            $result = ep_sync_stage_credits($ep);
            if ($result->created || $result->updated || $result->deleted) {
                mtrace("mod_ep: instance {$ep->id} — {$result->created} créé(s), "
                    . "{$result->updated} mis à jour, {$result->deleted} retiré(s).");
            }
        }
    }
}
