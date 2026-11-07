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

namespace mod_syllabus\event;

/**
 * Un étudiant a évalué le module. L'événement ne porte pas l'étudiant comme utilisateur
 * concerné et rien de sa réponse : le journal dit qu'il a répondu (comme la liste des
 * participants), pas ce qu'il a répondu.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation_submitted extends \core\event\base {
    /**
     * Initialisation.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'syllabus';
    }

    /**
     * Nom de l'événement.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventevaluationsubmitted', 'mod_syllabus');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' evaluated the module with course module id '$this->contextinstanceid'.";
    }

    /**
     * Adresse liée.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/syllabus/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Correspondance de l'objet à la restauration.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'syllabus', 'restore' => 'syllabus'];
    }
}
