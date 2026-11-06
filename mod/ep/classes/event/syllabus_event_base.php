<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_ep\event;

/**
 * Socle des événements du circuit d'un syllabus (fiche descriptive d'un EP du catalogue) :
 * soumission à la DEVE, puis publication, renvoi ou refus. L'objet est la version du syllabus,
 * « other » porte l'EP du catalogue (activityid) et son état après l'action (state).
 *
 * @package mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class syllabus_event_base extends \core\event\base {
    /**
     * Initialise les données communes.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'ep_syllabus';
    }

    /**
     * Nom lisible de l'événement, tiré de la chaîne de langue « event » suivie du nom de la classe
     * sans tiret bas.
     *
     * @return string
     */
    public static function get_name() {
        $short = substr(strrchr(static::class, '\\'), 1);
        return get_string('event' . str_replace('_', '', $short), 'mod_ep');
    }

    /**
     * Page du syllabus de l'EP.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/ep/syllabus.php', [
            'id' => $this->contextinstanceid,
            'activityid' => $this->other['activityid'],
        ]);
    }

    /**
     * Contrôle des données : l'EP du catalogue est obligatoire.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['activityid'])) {
            throw new \coding_exception('Le champ other[activityid] est obligatoire.');
        }
    }

    /**
     * Correspondance de l'objet lors d'une restauration de journaux.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'ep_syllabus', 'restore' => 'ep_syllabus'];
    }

    /**
     * Correspondance des données « other » lors d'une restauration de journaux.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return ['activityid' => ['db' => 'ep_activity', 'restore' => 'ep_activity']];
    }
}
