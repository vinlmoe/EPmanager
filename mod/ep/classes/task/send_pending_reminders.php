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
 * Relance hebdomadaire des validateurs (responsables d'EP, enseignants référents, DEVE) qui ont
 * des demandes en attente de leur décision depuis plus d'une semaine : un message récapitulatif
 * par personne et par activité, désactivable par la DEVE dans les courriels de l'activité.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_pending_reminders extends \core\task\scheduled_task {
    /**
     * Nom de la tâche, tel qu'affiché dans l'administration des tâches planifiées.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksendpendingreminders', 'mod_ep');
    }

    /**
     * Envoie les relances de chaque instance.
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/ep/locallib.php');

        $rs = $DB->get_recordset('ep');
        foreach ($rs as $ep) {
            $sent = ep_send_pending_reminders($ep);
            if ($sent) {
                mtrace("mod_ep: instance {$ep->id} — {$sent} relance(s) envoyée(s).");
            }
        }
        $rs->close();
    }
}
