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
 * Étape de sauvegarde de mod_syllabus.
 *
 * @package   mod_syllabus
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure : instance, versions et séances, questions du module ; avec les données des
 * utilisateurs, responsables et évaluation (réponses anonymes et participations).
 *
 * Le référentiel commun (types, questions communes) n'appartient à aucun cours : il n'est pas
 * sauvegardé ; les questions figées dans le module, elles, le sont.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_syllabus_activity_structure_step extends backup_activity_structure_step {
    /**
     * Structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $syllabus = new backup_nested_element('syllabus', ['id'], ['name', 'intro', 'introformat', 'code', 'ects', 'semester',
            'studyyear', 'typeid', 'evalopen', 'evalclose', 'evalfrozen', 'evalnotified', 'timecreated', 'timemodified']);
        $versions = new backup_nested_element('versions');
        $version = new backup_nested_element('version', ['id'], ['version', 'status', 'objectives', 'outcomes',
            'prerequisitestext', 'content', 'methods', 'assessment', 'resit', 'bibliography', 'language', 'personalhours',
            'authorid', 'reviewerid', 'reviewcomment', 'timesubmitted', 'timereviewed', 'timecreated', 'timemodified']);
        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'], ['sortorder', 'title', 'kind', 'duration', 'teacherid',
            'teachername', 'timecreated']);
        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], ['origin', 'typeid', 'sourceid', 'kind', 'text', 'options',
            'required', 'sortorder', 'enabled', 'timecreated', 'timemodified']);
        $teachers = new backup_nested_element('teachers');
        $teacher = new backup_nested_element('teacher', ['id'], ['userid', 'timecreated']);
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], ['daysubmitted']);
        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'], ['questionid', 'value', 'textvalue']);
        $competencies = new backup_nested_element('competencies');
        $competency = new backup_nested_element('competency', ['id'], ['competencyid', 'level', 'competencyidnumber',
            'frameworkidnumber', 'timecreated', 'timemodified']);
        $participants = new backup_nested_element('participants');
        $participant = new backup_nested_element('participant', ['id'], ['userid']);

        $syllabus->add_child($versions);
        $versions->add_child($version);
        $version->add_child($sessions);
        $sessions->add_child($session);
        $syllabus->add_child($questions);
        $questions->add_child($question);
        $syllabus->add_child($teachers);
        $teachers->add_child($teacher);
        $syllabus->add_child($responses);
        $responses->add_child($response);
        $response->add_child($answers);
        $answers->add_child($answer);
        $syllabus->add_child($competencies);
        $competencies->add_child($competency);
        $syllabus->add_child($participants);
        $participants->add_child($participant);

        $syllabus->set_source_table('syllabus', ['id' => backup::VAR_ACTIVITYID]);
        $version->set_source_table('syllabus_version', ['syllabusid' => backup::VAR_PARENTID], 'version ASC');
        $session->set_source_table('syllabus_session', ['versionid' => backup::VAR_PARENTID], 'sortorder ASC');
        $question->set_source_table('syllabus_question', ['syllabusid' => backup::VAR_PARENTID], 'sortorder ASC');
        // Niveaux de contribution aux compétences : avec les identifiants du référentiel et de la
        // compétence, qui permettent de les retrouver sur un autre site.
        $competency->set_source_sql('SELECT sc.id, sc.competencyid, sc.level, c.idnumber AS competencyidnumber,
                                            f.idnumber AS frameworkidnumber, sc.timecreated, sc.timemodified
                                       FROM {syllabus_competency} sc
                                       JOIN {competency} c ON c.id = sc.competencyid
                                       JOIN {competency_framework} f ON f.id = c.competencyframeworkid
                                      WHERE sc.syllabusid = ?', [backup::VAR_PARENTID]);
        if ($userinfo) {
            $teacher->set_source_table('syllabus_teacher', ['syllabusid' => backup::VAR_PARENTID]);
            $response->set_source_table('syllabus_response', ['syllabusid' => backup::VAR_PARENTID]);
            $answer->set_source_table('syllabus_answer', ['responseid' => backup::VAR_PARENTID]);
            $participant->set_source_table('syllabus_participant', ['syllabusid' => backup::VAR_PARENTID]);
        }

        $version->annotate_ids('user', 'authorid');
        $version->annotate_ids('user', 'reviewerid');
        $session->annotate_ids('user', 'teacherid');
        $teacher->annotate_ids('user', 'userid');
        $participant->annotate_ids('user', 'userid');
        $syllabus->annotate_files('mod_syllabus', 'intro', null);
        return $this->prepare_activity_structure($syllabus);
    }
}
