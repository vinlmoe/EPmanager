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
 * Tâche de sauvegarde d'une instance de mod_ep.
 *
 * @package   mod_ep
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ep/lib.php');
require_once($CFG->dirroot . '/mod/ep/backup/moodle2/backup_ep_stepslib.php');

/**
 * Enchaîne les étapes de sauvegarde d'une instance de mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_ep_activity_task extends backup_activity_task {

    /**
     * Aucun réglage propre à cette activité.
     */
    protected function define_my_settings() {
    }

    /**
     * L'activité tient dans une seule étape de structure.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_ep_activity_structure_step('ep_structure', 'ep.xml'));
    }

    /**
     * Encode les liens vers les pages de l'activité pour qu'ils survivent à la restauration.
     *
     * @param string $content Texte pouvant contenir des liens vers l'activité.
     * @return string Le texte avec les liens encodés.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $content = preg_replace(
            '/(' . $base . '\/mod\/ep\/index.php\?id\=)([0-9]+)/',
            '$@EPINDEX*$2@$',
            $content
        );

        $content = preg_replace(
            '/(' . $base . '\/mod\/ep\/view.php\?id\=)([0-9]+)/',
            '$@EPVIEWBYID*$2@$',
            $content
        );

        return $content;
    }
}
