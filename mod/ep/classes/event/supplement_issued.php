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
 * Émission d'un supplément au diplôme par la DEVE ; « other » porte son numéro (number).
 *
 * @package mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supplement_issued extends \core\event\base {
    /**
     * Initialise les données.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'ep_supplement_issue';
    }

    /**
     * Nom de l'événement.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsupplementissued', 'mod_ep');
    }

    /**
     * Description pour les journaux.
     *
     * @return string
     */
    public function get_description() {
        return "L'utilisateur #{$this->userid} a émis le supplément au diplôme {$this->other['number']} de l'étudiant "
            . "#{$this->relateduserid}.";
    }

    /**
     * Page des suppléments émis.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/ep/supplement_issue.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * L'étudiant et le numéro sont obligatoires.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid) || !isset($this->other['number'])) {
            throw new \coding_exception('relateduserid et other[number] sont obligatoires.');
        }
    }

    /**
     * Correspondance de l'objet lors d'une restauration de journaux.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'ep_supplement_issue', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Les données « other » ne désignent pas d'enregistrement.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
