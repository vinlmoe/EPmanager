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
 * Étape de restauration de mod_syllabus.
 *
 * @package   mod_syllabus
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restauration : type de module et question commune d'origine gardés s'ils existent sur le site,
 * sinon remis à zéro ; utilisateurs rapprochés par le mécanisme de Moodle.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_syllabus_activity_structure_step extends restore_activity_structure_step {
    /**
     * Chemins lus.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('syllabus', '/activity/syllabus'),
            new restore_path_element('syllabus_version', '/activity/syllabus/versions/version'),
            new restore_path_element('syllabus_session', '/activity/syllabus/versions/version/sessions/session'),
            new restore_path_element('syllabus_question', '/activity/syllabus/questions/question'),
            new restore_path_element('syllabus_competency', '/activity/syllabus/competencies/competency'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('syllabus_teacher', '/activity/syllabus/teachers/teacher');
            $paths[] = new restore_path_element('syllabus_response', '/activity/syllabus/responses/response');
            $paths[] = new restore_path_element('syllabus_answer', '/activity/syllabus/responses/response/answers/answer');
            $paths[] = new restore_path_element('syllabus_participant', '/activity/syllabus/participants/participant');
            $paths[] = new restore_path_element('syllabus_archive', '/activity/syllabus/archives/archive');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Instance.
     *
     * @param array $data
     */
    protected function process_syllabus($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        // Fiche gérée par un autre module (un EP) : ce module la reprend à sa propre restauration
        // (voir restore_ep_activity_structure_step) ; seule, elle redevient une fiche ordinaire.
        $data->component = '';
        $data->itemid = 0;
        if (!empty($data->typeid) && !$DB->record_exists('syllabus_type', ['id' => $data->typeid])) {
            $data->typeid = 0;
        }
        // Sans les réponses (copie du cours pour une autre année, import), le recueil repart de
        // zéro, sans fenêtre : sinon les étudiants du nouveau cours seraient prévenus d'un recueil
        // aux dates de l'ancien. La DEVE ou le responsable fixe la nouvelle fenêtre.
        if (!$this->get_setting_value('userinfo')) {
            $data->evalnotified = 0;
            $data->evalopen = 0;
            $data->evalclose = 0;
            $data->evalyear = '';
        }
        // Sauvegarde antérieure aux avis annuels : l'année se déduit de l'ouverture.
        if (!isset($data->evalyear) || ($data->evalyear === '' && !empty($data->evalopen))) {
            global $CFG;
            require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
            $data->evalyear = !empty($data->evalopen) ? syllabus_academic_year((int) $data->evalopen) : '';
        }
        $this->apply_activity_instance($DB->insert_record('syllabus', $data));
    }

    /**
     * Version.
     *
     * @param array $data
     */
    protected function process_syllabus_version($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->syllabusid = $this->get_new_parentid('syllabus');
        $data->authorid = $this->get_mappingid('user', $data->authorid ?? 0) ?: null;
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid ?? 0) ?: null;
        $this->set_mapping('syllabus_version', $oldid, $DB->insert_record('syllabus_version', $data));
    }

    /**
     * Séance.
     *
     * @param array $data
     */
    protected function process_syllabus_session($data) {
        global $DB;
        $data = (object) $data;
        $data->versionid = $this->get_new_parentid('syllabus_version');
        $data->teacherid = $this->get_mappingid('user', $data->teacherid ?? 0) ?: null;
        $DB->insert_record('syllabus_session', $data);
    }

    /**
     * Question du module.
     *
     * @param array $data
     */
    protected function process_syllabus_question($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->syllabusid = $this->get_new_parentid('syllabus');
        if (!empty($data->sourceid) && !$DB->record_exists('syllabus_question', ['id' => $data->sourceid, 'syllabusid' => 0])) {
            $data->sourceid = 0;
        }
        if (!empty($data->typeid) && !$DB->record_exists('syllabus_type', ['id' => $data->typeid])) {
            $data->typeid = 0;
        }
        $this->set_mapping('syllabus_question', $oldid, $DB->insert_record('syllabus_question', $data));
    }

    /**
     * Compétence visée : retrouvée par le code de son référentiel et le sien ; à défaut (référentiel
     * absent du site de destination), le lien est abandonné.
     *
     * @param array $data
     */
    protected function process_syllabus_competency($data) {
        global $DB;
        $data = (object) $data;
        $compid = (int) $DB->get_field_sql('SELECT c.id FROM {syllabus_comp} c
                JOIN {syllabus_framework} f ON f.id = c.frameworkid
               WHERE c.idnumber = ? AND f.idnumber = ?', [(string) $data->compidnumber, (string) $data->frameworkidnumber]);
        if (!$compid) {
            return;
        }
        $syllabusid = $this->get_new_parentid('syllabus');
        $link = ['courseid' => $this->get_courseid(), 'syllabusid' => $syllabusid, 'compid' => $compid];
        if (!$DB->record_exists('syllabus_complink', $link)) {
            $DB->insert_record('syllabus_complink', (object) ($link + ['level' => max(1, min(3, (int) $data->level)),
                'timecreated' => $data->timecreated, 'timemodified' => $data->timemodified]));
        }
    }

    /**
     * Responsable.
     *
     * @param array $data
     */
    protected function process_syllabus_teacher($data) {
        global $DB;
        $data = (object) $data;
        $data->syllabusid = $this->get_new_parentid('syllabus');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (
            $data->userid && !$DB->record_exists('syllabus_teacher', ['syllabusid' => $data->syllabusid,
                'userid' => $data->userid])
        ) {
            $DB->insert_record('syllabus_teacher', $data);
        }
    }

    /**
     * Réponse anonyme.
     *
     * @param array $data
     */
    protected function process_syllabus_response($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->syllabusid = $this->get_new_parentid('syllabus');
        $this->set_mapping('syllabus_response', $oldid, $DB->insert_record('syllabus_response', $data));
    }

    /**
     * Réponse à une question.
     *
     * @param array $data
     */
    protected function process_syllabus_answer($data) {
        global $DB;
        $data = (object) $data;
        $data->responseid = $this->get_new_parentid('syllabus_response');
        $data->questionid = $this->get_mappingid('syllabus_question', $data->questionid);
        if ($data->questionid) {
            $DB->insert_record('syllabus_answer', $data);
        }
    }

    /**
     * Participation.
     *
     * @param array $data
     */
    protected function process_syllabus_participant($data) {
        global $DB;
        $data = (object) $data;
        $data->syllabusid = $this->get_new_parentid('syllabus');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (
            $data->userid && !$DB->record_exists('syllabus_participant', ['syllabusid' => $data->syllabusid,
                'userid' => $data->userid])
        ) {
            $DB->insert_record('syllabus_participant', $data);
        }
    }

    /**
     * Résultats d'une année passée.
     *
     * @param array $data
     */
    protected function process_syllabus_archive($data) {
        global $DB;
        $data = (object) $data;
        $data->syllabusid = $this->get_new_parentid('syllabus');
        $DB->insert_record('syllabus_evalarchive', $data);
    }

    /**
     * Fichiers de la description.
     */
    protected function after_execute() {
        $this->add_related_files('mod_syllabus', 'intro', null);
    }
}
