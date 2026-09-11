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
 * Étape de restauration de la structure d'une instance de mod_ep.
 *
 * @package   mod_ep
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restaure l'arbre décrit par backup_ep_activity_structure_step.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ep_activity_structure_step extends restore_activity_structure_step {

    /**
     * Déclare les chemins à restaurer.
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('ep', '/activity/ep');
        $paths[] = new restore_path_element('ep_type', '/activity/ep/types/type');
        $paths[] = new restore_path_element('ep_activity', '/activity/ep/activities/activity');
        $paths[] = new restore_path_element('ep_activity_teacher',
            '/activity/ep/activities/activity/activityteachers/activityteacher');
        $paths[] = new restore_path_element('ep_year_requirement',
            '/activity/ep/yearrequirements/yearrequirement');

        if ($userinfo) {
            $paths[] = new restore_path_element('ep_credit', '/activity/ep/credits/credit');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restaure l'instance elle-même.
     *
     * @param array $data
     */
    protected function process_ep($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        // La copie n'a encore rien synchronisé depuis mod_stage : la prochaine consultation
        // déclenchera une synchronisation complète (voir ep_sync_stage_credits_if_due()).
        $data->timesynced = 0;

        $newitemid = $DB->insert_record('ep', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restaure un type d'EP.
     *
     * @param array $data
     */
    protected function process_ep_type($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->epid = $this->get_new_parentid('ep');

        $newitemid = $DB->insert_record('ep_type', $data);
        $this->set_mapping('ep_type', $oldid, $newitemid);
    }

    /**
     * Restaure un EP du catalogue.
     *
     * @param array $data
     */
    protected function process_ep_activity($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->epid = $this->get_new_parentid('ep');
        $data->typeid = $this->get_mappingid('ep_type', $data->typeid) ?: 0;

        $newitemid = $DB->insert_record('ep_activity', $data);
        $this->set_mapping('ep_activity', $oldid, $newitemid);
    }

    /**
     * Restaure un responsable d'EP du catalogue. La ligne est écartée si le compte n'est pas
     * présent dans la sauvegarde (restauration sans les utilisateurs).
     *
     * @param array $data
     */
    protected function process_ep_activity_teacher($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);

        $teacherid = $this->get_mappingid('user', $data->teacherid);
        if (!$teacherid) {
            return;
        }

        $data->activityid = $this->get_new_parentid('ep_activity');
        $data->teacherid = $teacherid;

        // L'index (activityid, teacherid) est unique : deux comptes distincts de la sauvegarde
        // peuvent se retrouver fusionnés sur un même compte du site cible.
        if ($DB->record_exists('ep_activity_teacher',
                ['activityid' => $data->activityid, 'teacherid' => $teacherid])) {
            return;
        }

        $DB->insert_record('ep_activity_teacher', $data);
    }

    /**
     * Restaure un minimum d'ECTS pour une année d'étude.
     *
     * @param array $data
     */
    protected function process_ep_year_requirement($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->epid = $this->get_new_parentid('ep');

        $DB->insert_record('ep_year_requirement', $data);
    }

    /**
     * Restaure un crédit d'enseignement personnalisé.
     *
     * @param array $data
     */
    protected function process_ep_credit($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }

        $data->epid = $this->get_new_parentid('ep');
        $data->userid = $userid;
        $data->typeid = $this->get_mappingid('ep_type', $data->typeid) ?: 0;
        $data->activityid = empty($data->activityid) ? null
            : ($this->get_mappingid('ep_activity', $data->activityid) ?: null);
        $data->validatedby = empty($data->validatedby) ? null
            : ($this->get_mappingid('user', $data->validatedby) ?: null);

        $newitemid = $DB->insert_record('ep_credit', $data);
        $this->set_mapping('ep_credit', $oldid, $newitemid, true);
    }

    /**
     * Rattache les fichiers une fois toutes les correspondances établies.
     */
    protected function after_execute() {
        $this->add_related_files('mod_ep', 'intro', null);

        if ($this->get_setting_value('userinfo')) {
            $this->add_related_files('mod_ep', EP_EVIDENCE_FILEAREA, 'ep_credit');
        }
    }

    /**
     * Fait pointer l'activité vers le mod_stage réellement restauré.
     *
     * Le réglage désigne une activité « Gestion des stages » par son identifiant de course-module,
     * qui peut n'être restaurée qu'après celle-ci. S'il ne correspond à aucune activité restaurée,
     * il n'est conservé que sur le même site, où il désigne toujours la bonne activité ; ailleurs
     * il est remis à 0, ce qui revient à considérer toutes les activités stage du cours.
     */
    protected function after_restore() {
        global $DB;

        $epid = $this->task->get_activityid();
        if (!$epid) {
            return;
        }

        $stagecmid = (int) $DB->get_field('ep', 'stagecmid', ['id' => $epid]);
        if (!$stagecmid) {
            return;
        }

        $newcmid = (int) $this->get_mappingid('course_module', $stagecmid);
        if (!$newcmid) {
            $stillvalid = $this->task->is_samesite()
                && get_coursemodule_from_id('stage', $stagecmid, 0, false, IGNORE_MISSING);
            $newcmid = $stillvalid ? $stagecmid : 0;
        }

        if ($newcmid !== $stagecmid) {
            $DB->set_field('ep', 'stagecmid', $newcmid, ['id' => $epid]);
        }
    }
}
