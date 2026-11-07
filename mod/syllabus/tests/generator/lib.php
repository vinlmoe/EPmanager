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

    /**
     * Responsable d'un module (inscrit au cours avec le droit de rédiger).
     *
     * @param array $record {syllabusid, userid}
     * @return void
     */
    public function create_leader(array $record): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
        $syllabus = $DB->get_record('syllabus', ['id' => $record['syllabusid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $current = array_keys(syllabus_get_teachers((int) $syllabus->id));
        // Le compte qui a créé l'activité (l'administrateur, dans un scénario) n'en reste pas responsable.
        $current = array_diff($current, [(int) get_admin()->id]);
        syllabus_set_teachers($syllabus, \context_module::instance($cm->id), array_merge($current, [(int) $record['userid']]));
    }

    /**
     * Fiche d'un module : publiée (par défaut) ou soumise à la DEVE.
     *
     * @param array $record {syllabusid, status?, objectives?, outcomes?, content?, assessment?,
     *     sessions? (intitulés séparés par « ; »)}
     * @return void
     */
    public function create_fiche(array $record): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
        $syllabus = $DB->get_record('syllabus', ['id' => $record['syllabusid']], '*', MUST_EXIST);
        $authorid = (int) get_admin()->id;
        $version = syllabus_start_working($syllabus, $authorid);
        $sessions = [];
        foreach (array_filter(array_map('trim', explode(';', $record['sessions'] ?? 'Introduction'))) as $title) {
            $sessions[] = ['title' => $title, 'kind' => 'lecture', 'duration' => 2];
        }
        syllabus_save_working($version, (object) [
            'objectives' => $record['objectives'] ?? 'Objectifs du module.',
            'outcomes' => $record['outcomes'] ?? 'Acquis visés.',
            'content' => $record['content'] ?? 'Contenu du module.',
            'assessment' => $record['assessment'] ?? 'Examen écrit.',
            'language' => 'Français',
        ], $sessions, $authorid);
        $version = syllabus_get_working((int) $syllabus->id);
        if (($record['status'] ?? 'published') === 'submitted') {
            $DB->update_record('syllabus_version', (object) ['id' => $version->id, 'status' => SYLLABUS_STATUS_SUBMITTED,
                'timesubmitted' => time()]);
        } else {
            syllabus_publish($syllabus, $version, $authorid, '');
        }
    }

    /**
     * Avis d'un étudiant : la même valeur à toutes les questions à échelle, les autres laissées
     * vides (sauf obligatoires : première réponse possible). Le recueil doit être ouvert.
     *
     * @param array $record {syllabusid, userid, value}
     * @return void
     */
    public function create_feedback(array $record): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
        $syllabus = $DB->get_record('syllabus', ['id' => $record['syllabusid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        syllabus_freeze_questions($syllabus);
        $answers = [];
        foreach (syllabus_get_module_questions((int) $syllabus->id) as $question) {
            if ($question->kind === 'scale') {
                $answers[$question->id] = (int) $record['value'];
            } else if ($question->required) {
                $answers[$question->id] = $question->kind === 'text' ? 'Réponse' : ($question->kind === 'yesno' ? 1 : 0);
            }
        }
        $errors = syllabus_submit_response($syllabus, \context_module::instance($cm->id), (int) $record['userid'], $answers);
        if ($errors) {
            throw new \coding_exception('Avis refusé : ' . json_encode($errors));
        }
    }

    /**
     * Année universitaire du recueil en cours d'un module (pour simuler un recueil de l'an dernier).
     *
     * @param array $record {syllabusid, evalyear}
     * @return void
     */
    public function create_feedback_year(array $record): void {
        global $DB;
        $DB->set_field('syllabus', 'evalyear', $record['evalyear'], ['id' => $record['syllabusid']]);
    }

    /**
     * Référentiel de compétences, et ses compétences en CSV (« Code;Code parent;Intitulé », lignes
     * séparées par « | »).
     *
     * @param array $record {name, idnumber, competencies?}
     * @return int
     */
    public function create_framework(array $record): int {
        global $CFG;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
        $id = syllabus_save_framework((object) ['name' => $record['name'], 'idnumber' => $record['idnumber']]);
        if (!empty($record['competencies'])) {
            syllabus_import_comps($id, "Code;Code parent;Intitulé\n" . str_replace('|', "\n", $record['competencies']));
        }
        return $id;
    }
}
