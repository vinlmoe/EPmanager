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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Générateur Behat de mod_syllabus : responsables, fiches, questions, avis et référentiels créés
 * depuis les tableaux « the following "mod_syllabus > ..." exist » des scénarios.
 *
 * @package    mod_syllabus
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_syllabus_generator extends behat_generator_base {
    /**
     * Entités créables. Le module est désigné par le nom de son activité, les utilisateurs par
     * leur username.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'types' => ['singular' => 'type', 'datagenerator' => 'type_record', 'required' => ['name']],
            'common questions' => ['singular' => 'common question', 'datagenerator' => 'question', 'required' => ['text']],
            'teacher questions' => ['singular' => 'teacher question', 'datagenerator' => 'question',
                'required' => ['syllabus', 'text'], 'switchids' => ['syllabus' => 'syllabusid']],
            'leaders' => ['singular' => 'leader', 'datagenerator' => 'leader', 'required' => ['syllabus', 'user'],
                'switchids' => ['syllabus' => 'syllabusid', 'user' => 'userid']],
            'fiches' => ['singular' => 'fiche', 'datagenerator' => 'fiche', 'required' => ['syllabus'],
                'switchids' => ['syllabus' => 'syllabusid']],
            'feedback' => ['singular' => 'feedback', 'datagenerator' => 'feedback', 'required' => ['syllabus', 'user', 'value'],
                'switchids' => ['syllabus' => 'syllabusid', 'user' => 'userid']],
            'feedback years' => ['singular' => 'feedback year', 'datagenerator' => 'feedback_year',
                'required' => ['syllabus', 'evalyear'], 'switchids' => ['syllabus' => 'syllabusid']],
            'frameworks' => ['singular' => 'framework', 'datagenerator' => 'framework', 'required' => ['name', 'idnumber']],
        ];
    }

    /**
     * Identifiant d'une instance d'après le nom de son activité.
     *
     * @param string $name
     * @return int
     */
    protected function get_syllabus_id(string $name): int {
        global $DB;
        return (int) $DB->get_field('syllabus', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * Type de module (le générateur de données attend un nom).
     *
     * @param array $data
     * @return void
     */
    protected function process_type_record(array $data): void {
        $this->componentdatagenerator->create_type($data['name']);
    }

    /**
     * Question commune, éventuellement posée aux modules d'un type (colonne « type » : son nom).
     *
     * @param array $data
     * @return void
     */
    protected function process_question(array $data): void {
        global $DB;
        if (!empty($data['type'])) {
            $data['origin'] = 'type';
            $data['typeid'] = (int) $DB->get_field('syllabus_type', 'id', ['name' => $data['type']], MUST_EXIST);
            unset($data['type']);
        }
        $this->componentdatagenerator->create_question($data);
    }
}
