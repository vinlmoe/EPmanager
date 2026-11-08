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
 * Étape de restauration de mod_syllabusstaff.
 *
 * @package   mod_syllabusstaff
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restauration de l'instance. Une catégorie qui n'existe pas sur le site de destination devient
 * « tout le site ».
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_syllabusstaff_activity_structure_step extends restore_activity_structure_step {
    /**
     * Chemins lus.
     *
     * @return array
     */
    protected function define_structure() {
        return $this->prepare_activity_structure([new restore_path_element('syllabusstaff', '/activity/syllabusstaff')]);
    }

    /**
     * Instance.
     *
     * @param array $data
     */
    protected function process_syllabusstaff($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        if (!empty($data->categoryid) && !$DB->record_exists('course_categories', ['id' => $data->categoryid])) {
            $data->categoryid = 0;
        }
        $this->apply_activity_instance($DB->insert_record('syllabusstaff', $data));
    }

    /**
     * Fichiers de la description.
     */
    protected function after_execute() {
        $this->add_related_files('mod_syllabusstaff', 'intro', null);
    }
}
