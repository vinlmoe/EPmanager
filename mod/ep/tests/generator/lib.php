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
 * Générateur de données de test pour mod_ep : au-delà de la création de l'instance elle-même
 * (déjà couverte par testing_module_generator), les tests ont besoin de crédits validés, d'EP de
 * catalogue et de minimums annuels sans reproduire à chaque fois les valeurs par défaut de leurs
 * tables respectives.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ep_generator extends testing_module_generator {
    /**
     * Règle les plafonds et le barème d'un type d'EP, repéré par son code.
     *
     * @param stdClass|int $ep
     * @param string $code Un des EP_TYPE_*.
     * @param array $settings maxects, maxectsperyear, ectsperday, enabled.
     * @return stdClass Le type mis à jour.
     */
    public function configure_type($ep, $code, array $settings = []) {
        global $DB;

        $epid = is_object($ep) ? $ep->id : $ep;
        $type = ep_get_type_by_code($epid, $code);
        if (!$type) {
            throw new coding_exception('Type inconnu : ' . $code);
        }

        foreach (['maxects', 'maxectsperyear', 'ectsperday', 'enabled'] as $field) {
            if (array_key_exists($field, $settings)) {
                $type->$field = $settings[$field];
            }
        }
        $type->timemodified = time();
        $DB->update_record('ep_type', $type);

        return $type;
    }

    /**
     * Crée un EP du catalogue.
     *
     * @param stdClass|int $ep
     * @param array $record name, typeid (ou code), ects, capacity, minstudyyear, maxstudyyear, visible.
     * @return stdClass L'EP créé.
     */
    public function create_activity($ep, array $record = []) {
        global $DB;

        $epid = is_object($ep) ? $ep->id : $ep;

        if (!isset($record['typeid'])) {
            $type = ep_get_type_by_code($epid, $record['code'] ?? EP_TYPE_ACADEMIC);
            $record['typeid'] = $type->id;
        }
        unset($record['code']);

        $record = array_merge([
            'epid' => $epid,
            'name' => 'EP ' . ($DB->count_records('ep_activity', ['epid' => $epid]) + 1),
            'description' => '',
            'ects' => 2,
            'minstudyyear' => 0,
            'maxstudyyear' => 0,
            'capacity' => 0,
            'visible' => 1,
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);

        $id = $DB->insert_record('ep_activity', (object) $record);
        return $DB->get_record('ep_activity', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Crée un crédit déjà validé, pour tester les décomptes sans rejouer tout le circuit de
     * validation.
     *
     * @param stdClass $ep
     * @param int $userid
     * @param string $code Code du type (EP_TYPE_*).
     * @param float $ects ECTS retenus.
     * @param int $studyyear
     * @return stdClass Le crédit créé.
     */
    public function create_validated_credit(stdClass $ep, $userid, $code, $ects, $studyyear = 0) {
        global $DB;

        $type = ep_get_type_by_code($ep->id, $code);
        $creditid = ep_create_credit($ep, $userid, $type, [
            'studyyear' => $studyyear,
            'name' => 'EP de test',
            'claimedects' => $ects,
        ]);

        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        if ((int) $credit->status !== EP_STATUS_VALIDATED) {
            ep_validate_credit($credit, 0, $ects);
            $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        }
        return $credit;
    }
}
