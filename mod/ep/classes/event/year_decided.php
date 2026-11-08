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
 * Décision de la DEVE sur l'année d'étude d'un étudiant (validée ou non) ; « other » porte
 * l'année (studyyear) et la décision (decision : 1 validée, 0 non validée).
 *
 * @package mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class year_decided extends \core\event\base {
    /**
     * Initialise les données.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'ep_year_validation';
    }

    /**
     * Nom de l'événement.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventyeardecided', 'mod_ep');
    }

    /**
     * Description pour les journaux.
     *
     * @return string
     */
    public function get_description() {
        $verb = empty($this->other['decision']) ? 'déclaré non validée' : 'validé';
        return "L'utilisateur #{$this->userid} a {$verb} l'année {$this->other['studyyear']} de l'étudiant "
            . "#{$this->relateduserid}.";
    }

    /**
     * Page de validation des années.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/ep/year_validation.php', [
            'id' => $this->contextinstanceid,
            'studyyear' => $this->other['studyyear'],
        ]);
    }

    /**
     * L'étudiant et l'année sont obligatoires.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid) || !isset($this->other['studyyear'])) {
            throw new \coding_exception('relateduserid et other[studyyear] sont obligatoires.');
        }
    }

    /**
     * Correspondance de l'objet lors d'une restauration de journaux.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'ep_year_validation', 'restore' => \core\event\base::NOT_MAPPED];
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
