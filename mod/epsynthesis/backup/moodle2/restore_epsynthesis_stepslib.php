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
 * Étape de restauration de la structure d'une instance de mod_epsynthesis.
 *
 * @package   mod_epsynthesis
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restaure l'arbre décrit par backup_epsynthesis_activity_structure_step.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_epsynthesis_activity_structure_step extends restore_activity_structure_step {
    /**
     * Déclare les chemins à restaurer.
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];

        $paths[] = new restore_path_element('epsynthesis', '/activity/epsynthesis');
        $paths[] = new restore_path_element('epsynthesis_link', '/activity/epsynthesis/links/link');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restaure l'instance elle-même.
     *
     * @param array $data
     */
    protected function process_epsynthesis($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('epsynthesis', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restaure un lien vers une activité « Enseignement personnalisé ».
     *
     * L'identifiant de course-module est conservé tel quel à ce stade : l'activité mod_ep visée
     * peut n'être restaurée qu'après celle-ci. La correspondance est appliquée par after_restore(),
     * une fois toutes les activités du cours restaurées.
     *
     * @param array $data
     */
    protected function process_epsynthesis_link($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->synthesisid = $this->get_new_parentid('epsynthesis');

        $DB->insert_record('epsynthesis_link', $data);
    }

    /**
     * Rattache les fichiers de la description.
     */
    protected function after_execute() {
        $this->add_related_files('mod_epsynthesis', 'intro', null);
    }

    /**
     * Fait pointer les liens vers les activités mod_ep réellement restaurées.
     *
     * Un lien dont l'activité d'origine n'a pas été restaurée n'est conservé que sur le même site,
     * où l'identifiant de course-module d'origine désigne toujours la bonne activité ; ailleurs il
     * désignerait une activité sans rapport et est donc supprimé.
     */
    protected function after_restore() {
        global $DB;

        $synthesisid = $this->task->get_activityid();
        if (!$synthesisid) {
            return;
        }

        foreach ($DB->get_records('epsynthesis_link', ['synthesisid' => $synthesisid]) as $link) {
            $newcmid = (int) $this->get_mappingid('course_module', $link->epcmid);

            if (!$newcmid) {
                $stillvalid = $this->task->is_samesite()
                    && get_coursemodule_from_id('ep', $link->epcmid, 0, false, IGNORE_MISSING);
                if (!$stillvalid) {
                    $DB->delete_records('epsynthesis_link', ['id' => $link->id]);
                }
                continue;
            }

            if ($newcmid === (int) $link->epcmid) {
                continue;
            }

            // L'index (synthesisid, epcmid) est unique : si la cible est déjà liée, ce lien ferait
            // doublon.
            if (
                $DB->record_exists(
                    'epsynthesis_link',
                    ['synthesisid' => $synthesisid, 'epcmid' => $newcmid]
                )
            ) {
                $DB->delete_records('epsynthesis_link', ['id' => $link->id]);
                continue;
            }

            $DB->set_field('epsynthesis_link', 'epcmid', $newcmid, ['id' => $link->id]);
        }
    }
}
