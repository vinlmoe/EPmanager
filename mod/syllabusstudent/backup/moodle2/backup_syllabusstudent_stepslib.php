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
 * Étapes de sauvegarde et de restauration de mod_syllabusstudent : l'instance seule.
 *
 * @package   mod_syllabusstudent
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure sauvegardée.
 *
 * @package   mod_syllabusstudent
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_syllabusstudent_activity_structure_step extends backup_activity_structure_step {
    /**
     * Structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $instance = new backup_nested_element('syllabusstudent', ['id'], [
            'name', 'intro', 'introformat', 'categoryid', 'timecreated', 'timemodified',
        ]);
        $instance->set_source_table('syllabusstudent', ['id' => backup::VAR_ACTIVITYID]);
        $instance->annotate_files('mod_syllabusstudent', 'intro', null);
        return $this->prepare_activity_structure($instance);
    }
}
