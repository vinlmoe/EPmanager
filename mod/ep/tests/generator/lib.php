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

        foreach ($settings as $field => $value) {
            if (property_exists($type, $field) && $field !== 'id' && $field !== 'epid') {
                $type->$field = $value;
            }
        }
        $type->timemodified = time();
        $DB->update_record('ep_type', $type);

        return $DB->get_record('ep_type', ['id' => $type->id], '*', MUST_EXIST);
    }

    /**
     * Crée un type supplémentaire, avec les mêmes valeurs par défaut que l'interface.
     *
     * @param stdClass|int $ep
     * @param array $params code, name, acquisition, validationflow, grading, evidence...
     * @return stdClass Le type créé.
     */
    public function create_type($ep, array $params = []) {
        global $DB;

        $epid = is_object($ep) ? $ep->id : $ep;
        $params += ['name' => 'Type ' . ($DB->count_records('ep_type', ['epid' => $epid]) + 1)];
        $id = ep_save_type($epid, (object) $params);
        return $DB->get_record('ep_type', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Crée un EP du catalogue.
     *
     * @param stdClass|int $ep
     * @param array $record name, typeid (ou typecode), code, ects, capacity, minstudyyear, maxstudyyear, state,
     *                      registrationstart, registrationend, grading, grademax, gradepass, minects,
     *                      prereqblocking, prerequisites (identifiants d'EP).
     * @return stdClass L'EP créé.
     */
    public function create_activity($ep, array $record = []) {
        global $DB;

        $epid = is_object($ep) ? $ep->id : $ep;

        // « typecode » désigne le type par son code ; « code » est le code de l'EP lui-même.
        if (!isset($record['typeid'])) {
            $type = ep_get_type_by_code($epid, $record['typecode'] ?? EP_TYPE_ACADEMIC);
            $record['typeid'] = $type->id;
        }
        unset($record['typecode']);
        $prerequisites = $record['prerequisites'] ?? null;
        unset($record['prerequisites']);

        $record = array_merge([
            'epid' => $epid,
            'name' => 'EP ' . ($DB->count_records('ep_activity', ['epid' => $epid]) + 1),
            'description' => '',
            'ects' => 2,
            'minstudyyear' => 0,
            'maxstudyyear' => 0,
            'capacity' => 0,
            'state' => EP_ACTIVITY_PUBLISHED,
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);

        $id = $DB->insert_record('ep_activity', (object) $record);
        $activity = $DB->get_record('ep_activity', ['id' => $id], '*', MUST_EXIST);
        if ($prerequisites !== null) {
            ep_set_activity_prerequisites($activity, $prerequisites);
        }
        return $activity;
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

    /**
     * Rédige la fiche d'un EP (activité « Syllabus du module », créée au besoin) : sa version de
     * travail reçoit les rubriques et les séances données.
     *
     * @param stdClass $activity
     * @param int $userid Auteur.
     * @param array $data Rubriques (objectives, language, personalhours...).
     * @param array|null $sessions Séances {title, kind, duration, teacherid, teachername}.
     * @return stdClass La version de travail.
     */
    public function write_syllabus(stdClass $activity, int $userid, array $data = [], ?array $sessions = null): stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/ep/locallib.php');
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
        $ep = $DB->get_record('ep', ['id' => $activity->epid], '*', MUST_EXIST);
        $syllabus = ep_ensure_activity_syllabus($ep, $activity);
        $version = syllabus_start_working($syllabus, $userid);
        syllabus_save_working($version, (object) $data, $sessions, $userid);
        return $DB->get_record('syllabus_version', ['id' => $version->id], '*', MUST_EXIST);
    }

    /**
     * Soumet la version de travail de la fiche d'un EP, comme le bouton de mod_syllabus.
     *
     * @param stdClass $activity
     * @param int $userid
     * @return string 'submitted' ou 'published'
     */
    public function submit_syllabus(stdClass $activity, int $userid): string {
        $syllabus = ep_activity_syllabus_record($activity->id);
        $cm = get_coursemodule_from_id('syllabus', $syllabus->cmid, 0, false, MUST_EXIST);
        $version = syllabus_start_working($syllabus, $userid);
        return syllabus_submit($syllabus, $cm, $version, $userid);
    }

    /**
     * Décision de la DEVE sur la version soumise de la fiche d'un EP.
     *
     * @param stdClass $activity
     * @param int $reviewerid
     * @param string $decision publish, return ou reject
     * @param string $comment
     * @return bool
     */
    public function review_syllabus(stdClass $activity, int $reviewerid, string $decision, string $comment = ''): bool {
        global $DB;
        $syllabus = ep_activity_syllabus_record($activity->id);
        $cm = get_coursemodule_from_id('syllabus', $syllabus->cmid, 0, false, MUST_EXIST);
        $version = $DB->get_record('syllabus_version', ['syllabusid' => $syllabus->id, 'status' => 'submitted'], '*', MUST_EXIST);
        return syllabus_review($syllabus, $cm, $version, $reviewerid, $decision, $comment);
    }
}
