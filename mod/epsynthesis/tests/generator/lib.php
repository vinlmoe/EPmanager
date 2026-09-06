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
 * Générateur de données de test pour mod_epsynthesis : au-delà de la création de l'instance
 * elle-même (déjà couverte par testing_module_generator), les tests ont besoin d'activités
 * « Enseignement personnalisé » liées et d'EP partagés, sans reproduire à chaque fois les valeurs
 * par défaut de leurs tables respectives.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_epsynthesis_generator extends testing_module_generator {

    /**
     * Ajoute une activité « Enseignement personnalisé » au périmètre d'une synthèse, sans
     * toucher à celles qui y sont déjà.
     *
     * @param stdClass $epsynthesis Instance renvoyée par create_module().
     * @param int $epcmid Course-module de l'activité mod_ep à suivre.
     * @return void
     */
    public function link_ep(stdClass $epsynthesis, $epcmid) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

        $epcmids = array_keys(epsynthesis_get_links($epsynthesis->id));
        $epcmids[] = (int) $epcmid;
        epsynthesis_set_links($epsynthesis->id, $epcmids);
    }

    /**
     * Crée un EP académique partagé défini dans une synthèse.
     *
     * @param stdClass $epsynthesis Instance renvoyée par create_module() (son cmid est utilisé).
     * @param array $record name, ects, capacity, minstudyyear, maxstudyyear, visible, sortorder.
     * @return stdClass L'EP créé.
     */
    public function create_shared_activity(stdClass $epsynthesis, array $record = []) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/ep/locallib.php');

        $record = array_merge([
            'activityid' => 0,
            'name' => 'EP partagé ' . ($DB->count_records('ep_activity',
                ['synthesiscmid' => $epsynthesis->cmid]) + 1),
            'description' => '',
            'ects' => 2,
            'minstudyyear' => 0,
            'maxstudyyear' => 0,
            'capacity' => 0,
            'sortorder' => 0,
            'visible' => 1,
        ], $record);

        $id = ep_save_shared_activity($epsynthesis->cmid, (object) $record);

        return $DB->get_record('ep_activity', ['id' => $id], '*', MUST_EXIST);
    }
}
