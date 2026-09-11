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
 * Tâche de restauration d'une instance de mod_ep.
 *
 * @package   mod_ep
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ep/lib.php');
require_once($CFG->dirroot . '/mod/ep/backup/moodle2/restore_ep_stepslib.php');

/**
 * Enchaîne les étapes de restauration d'une instance de mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ep_activity_task extends restore_activity_task {

    /**
     * Aucun réglage propre à cette activité.
     */
    protected function define_my_settings() {
    }

    /**
     * L'activité tient dans une seule étape de structure.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_ep_activity_structure_step('ep_structure', 'ep.xml'));
    }

    /**
     * Champs dont le contenu doit passer par le décodeur de liens.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('ep', ['intro']);
        $contents[] = new restore_decode_content('ep_type', ['description'], 'ep_type');
        $contents[] = new restore_decode_content('ep_activity', ['description'], 'ep_activity');
        $contents[] = new restore_decode_content('ep_credit',
            ['description', 'validatorcomment'], 'ep_credit');

        return $contents;
    }

    /**
     * Règles de décodage des liens encodés par backup_ep_activity_task::encode_content_links().
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('EPINDEX', '/mod/ep/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('EPVIEWBYID', '/mod/ep/view.php?id=$1', 'course_module');

        return $rules;
    }

    /**
     * Règles de restauration des journaux de l'activité.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('ep', 'view', 'view.php?id={course_module}', '{ep}');

        return $rules;
    }

    /**
     * Règles de restauration des journaux du cours rattachés à ce module.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('ep', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
