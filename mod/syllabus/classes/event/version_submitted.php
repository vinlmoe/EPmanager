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
 * Une version de la fiche a été soumise à la relecture de la DEVE.
 *
 * Données : objectid (version), other : syllabusid, component et itemid (module gestionnaire de
 * la fiche, vide pour un module d'enseignement).
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class version_submitted extends \core\event\base {
    /**
     * Initialisation.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'syllabus_version';
    }

    /**
     * Nom de l'événement.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventversionsubmitted', 'mod_syllabus');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' submitted version '$this->objectid' of the syllabus with course module id "
            . "'$this->contextinstanceid' for review.";
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
     * Contrôle des données.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['syllabusid'])) {
            throw new \coding_exception('other[syllabusid] est requis.');
        }
    }

    /**
     * Correspondance de l'objet à la restauration.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'syllabus_version', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Correspondance des autres données à la restauration.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return ['syllabusid' => ['db' => 'syllabus', 'restore' => 'syllabus'], 'itemid' => \core\event\base::NOT_MAPPED];
    }
}
