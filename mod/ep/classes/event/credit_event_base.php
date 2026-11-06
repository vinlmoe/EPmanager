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
 * Socle des événements du circuit d'un enseignement personnalisé, tous rattachés à un crédit
 * (table ep_credit).
 *
 * Chaque étape du circuit (demande, modification, validation, refus, annulation, synchronisation
 * depuis les stages) déclenche un événement de cette famille, une fois la base mise à jour. Les
 * journaux Moodle gardent ainsi qui a fait quoi et quand sur chaque crédit, ce que la seule lecture
 * de la table ne permet pas de reconstituer.
 *
 * L'objet est le crédit, l'utilisateur lié (relateduserid) l'étudiant, et « other » porte toujours
 * le statut du crédit après l'action (status) et son type (typeid), plus l'EP du catalogue
 * (activityid) quand le crédit vient d'une inscription, et les données propres à chaque événement.
 *
 * @package mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class credit_event_base extends \core\event\base {
    /** @var string Lettre CRUD de l'événement : c, r, u ou d. */
    const CRUD = 'u';

    /** @var int Niveau éducatif de l'événement (constantes LEVEL_* de \core\event\base). */
    const EDULEVEL = self::LEVEL_OTHER;

    /**
     * Initialise les données communes.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = static::CRUD;
        $this->data['edulevel'] = static::EDULEVEL;
        $this->data['objecttable'] = 'ep_credit';
    }

    /**
     * Nom lisible de l'événement, tiré de la chaîne de langue « event » suivie du nom de la classe
     * sans tiret bas (eventcreditcreated, eventcreditvalidated...).
     *
     * @return string
     */
    public static function get_name() {
        $short = substr(strrchr(static::class, '\\'), 1);
        return get_string('event' . str_replace('_', '', $short), 'mod_ep');
    }

    /**
     * Page de détail du crédit concerné.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/ep/validate.php', [
            'id' => $this->contextinstanceid,
            'creditid' => $this->objectid,
        ]);
    }

    /**
     * L'étudiant doit toujours être renseigné, et « other » porter le statut et le type du crédit.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' (student) must be set.');
        }
        if (!isset($this->other['status']) || !is_numeric($this->other['status'])) {
            throw new \coding_exception('The \'status\' value (int) must be set in other.');
        }
        if (!isset($this->other['typeid']) || !is_numeric($this->other['typeid'])) {
            throw new \coding_exception('The \'typeid\' value (int) must be set in other.');
        }
    }

    /**
     * Correspondance de l'objet lors d'une restauration de journaux.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'ep_credit', 'restore' => 'ep_credit'];
    }

    /**
     * Correspondances de « other » lors d'une restauration de journaux : le type est toujours là,
     * l'EP du catalogue seulement quand le crédit vient d'une inscription (une clé absente est
     * simplement ignorée à la restauration).
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'typeid' => ['db' => 'ep_type', 'restore' => 'ep_type'],
            'activityid' => ['db' => 'ep_activity', 'restore' => 'ep_activity'],
        ];
    }

    /**
     * Phrase type des descriptions : « L'utilisateur #n a <action> l'enseignement personnalisé #m
     * de l'étudiant #p. »
     *
     * @param string $action Verbe et compléments, à l'accompli.
     * @return string
     */
    protected function describe($action) {
        return "L'utilisateur #{$this->userid} a {$action} l'enseignement personnalisé #{$this->objectid}"
            . " de l'étudiant #{$this->relateduserid}.";
    }
}
