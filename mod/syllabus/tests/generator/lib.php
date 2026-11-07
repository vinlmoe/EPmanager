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
 * Générateur de données de test de mod_syllabus.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_syllabus_generator extends testing_module_generator {
    /**
     * Instance, avec des caractéristiques par défaut.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) ($record ?? []) + ['name' => 'Module de test', 'code' => 'MOD1', 'ects' => 3, 'semester' => 'S5',
            'studyyear' => 3, 'typeid' => 0, 'evalopen' => 0, 'evalclose' => 0];
        return parent::create_instance($record, $options);
    }

    /**
     * Type de module.
     *
     * @param string $name
     * @return int
     */
    public function create_type(string $name): int {
        global $CFG;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
        return syllabus_save_type($name);
    }

    /**
     * Question : commune (syllabusid 0, origin common ou type) ou de module.
     *
     * @param array $record
     * @return int
     */
    public function create_question(array $record): int {
        global $CFG;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
        return syllabus_save_question((object) ($record + ['syllabusid' => 0, 'origin' => 'common', 'kind' => 'scale',
            'text' => 'Question', 'required' => 1]));
    }
}
