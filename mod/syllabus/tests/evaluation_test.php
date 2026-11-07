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

namespace mod_syllabus;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

/**
 * Évaluation du module : fenêtre, questions figées à l'ouverture, réponses anonymes, résultats.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabus_freeze_questions
 * @covers    ::syllabus_submit_response
 * @covers    ::syllabus_get_results
 * @covers    \mod_syllabus\task\open_evaluations
 */
final class evaluation_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $syllabus;
    /** @var \context_module */
    protected $context;
    /** @var \stdClass */
    protected $leader;
    /** @var array */
    protected $students = [];
    /** @var int */
    protected $typeid;
    /** @var \mod_syllabus_generator */
    protected $gen;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->gen = $gen->get_plugin_generator('mod_syllabus');
        $this->typeid = $this->gen->create_type('Clinique');
        $other = $this->gen->create_type('Fondamental');
        // Questions de la DEVE : une pour tous, une pour le type du module, une pour un autre type.
        $this->gen->create_question(['text' => 'Le module était bien organisé.']);
        $this->gen->create_question(['origin' => 'type', 'typeid' => $this->typeid, 'kind' => 'yesno',
            'text' => 'Les cas cliniques étaient variés.']);
        $this->gen->create_question(['origin' => 'type', 'typeid' => $other, 'text' => 'Hors sujet']);

        $this->course = $gen->create_course();
        $this->leader = $gen->create_and_enrol($this->course, 'editingteacher');
        for ($i = 0; $i < 4; $i++) {
            $this->students[] = $gen->create_and_enrol($this->course, 'student');
        }
        $this->setUser($this->leader);
        $record = $gen->create_module('syllabus', ['course' => $this->course->id, 'typeid' => $this->typeid,
            'evalopen' => time() + DAYSECS, 'evalclose' => time() + 10 * DAYSECS]);
        $this->syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
        $this->context = \context_module::instance($record->cmid);
        $this->gen->create_question(['syllabusid' => $this->syllabus->id, 'kind' => 'choice',
            'text' => 'Séance préférée', 'options' => "Cours\nTP\nClinique"]);
        $this->gen->create_question(['syllabusid' => $this->syllabus->id, 'kind' => 'text', 'text' => 'Suggestions',
            'required' => 0]);
    }

    /**
     * Ouvre l'évaluation (ouverture une seconde dans le passé).
     */
    protected function open(): void {
        syllabus_set_window($this->syllabus, time() - 1, time() + DAYSECS);
    }

    /**
     * Questions de l'évaluation : communes, de type (le bon), puis de l'enseignant.
     */
    public function test_questions_order_and_scope(): void {
        $texts = array_map(fn($q) => $q->text, syllabus_evaluation_questions($this->syllabus));
        $this->assertSame(['Le module était bien organisé.', 'Les cas cliniques étaient variés.', 'Séance préférée',
            'Suggestions'], $texts);
    }

    /**
     * À venir : pas de réponse possible, questions encore modifiables.
     */
    public function test_upcoming_evaluation_refuses_answers(): void {
        $this->assertSame('evalupcoming', syllabus_evaluation_problem(
            $this->syllabus,
            $this->context,
            (int) $this->students[0]->id
        ));
        $this->assertSame(0, (int) $this->syllabus->evalfrozen);
    }

    /**
     * À l'ouverture, les questions communes sont copiées dans le module : les modifier ensuite
     * ne change plus l'évaluation, et le module ne se modifie plus.
     */
    public function test_freeze_copies_common_questions(): void {
        global $DB;
        $this->open();
        $this->assertTrue(syllabus_freeze_questions($this->syllabus));
        $this->assertFalse(syllabus_freeze_questions($this->syllabus));
        $questions = syllabus_get_module_questions((int) $this->syllabus->id);
        $this->assertCount(4, $questions);
        $this->assertSame(['common', 'type', 'teacher', 'teacher'], array_values(array_map(fn($q) => $q->origin, $questions)));

        $DB->set_field('syllabus_question', 'text', 'Modifiée', ['syllabusid' => 0, 'origin' => 'common']);
        $this->assertSame('Le module était bien organisé.', reset($questions)->text);
        $this->expectException(\moodle_exception::class);
        $this->gen->create_question(['syllabusid' => $this->syllabus->id, 'text' => 'Trop tard']);
    }

    /**
     * Réponse anonyme : rien ne relie la réponse à l'étudiant ; une seule réponse par étudiant ;
     * réponses obligatoires et valeurs contrôlées.
     */
    public function test_anonymous_single_response(): void {
        global $DB;
        $this->open();
        syllabus_freeze_questions($this->syllabus);
        $q = array_values(syllabus_get_module_questions((int) $this->syllabus->id));
        $student = $this->students[0];
        $this->setUser($student);

        $errors = syllabus_submit_response($this->syllabus, $this->context, (int) $student->id, [$q[0]->id => 6]);
        $this->assertArrayHasKey($q[0]->id, $errors);
        $this->assertArrayHasKey($q[1]->id, $errors);
        $this->assertSame(0, syllabus_count_responses((int) $this->syllabus->id));

        $errors = syllabus_submit_response(
            $this->syllabus,
            $this->context,
            (int) $student->id,
            [$q[0]->id => 4, $q[1]->id => 1, $q[2]->id => 2, $q[3]->id => 'Plus de cas']
        );
        $this->assertSame([], $errors);
        $response = $DB->get_record('syllabus_response', ['syllabusid' => $this->syllabus->id]);
        $this->assertSame(['id', 'syllabusid', 'daysubmitted'], array_keys((array) $response));
        $this->assertSame(0, (int) $response->daysubmitted % 60, 'Pas d\'heure précise : seulement le jour.');
        $this->assertSame('evaldone', syllabus_evaluation_problem($this->syllabus, $this->context, (int) $student->id));
        $this->expectException(\moodle_exception::class);
        syllabus_submit_response($this->syllabus, $this->context, (int) $student->id, [$q[0]->id => 4, $q[1]->id => 1,
            $q[2]->id => 0]);
    }

    /**
     * Un enseignant ne peut pas répondre.
     */
    public function test_teacher_cannot_evaluate(): void {
        $this->open();
        $this->assertSame('evalnotallowed', syllabus_evaluation_problem(
            $this->syllabus,
            $this->context,
            (int) $this->leader->id
        ));
    }

    /**
     * Résultats : rien sous le seuil ; au-delà, moyennes, répartitions, réponses libres triées.
     * Le responsable ne les voit qu'après la fermeture, sauf réglage contraire.
     */
    public function test_results_threshold_and_visibility(): void {
        $this->open();
        syllabus_freeze_questions($this->syllabus);
        $q = array_values(syllabus_get_module_questions((int) $this->syllabus->id));
        $answers = [[5, 1, 0, 'Zèbre'], [3, 0, 1, ''], [4, 1, 1, 'Abeille']];
        foreach ($answers as $i => [$scale, $yes, $choice, $text]) {
            $this->setUser($this->students[$i]);
            $this->assertSame([], syllabus_submit_response(
                $this->syllabus,
                $this->context,
                (int) $this->students[$i]->id,
                [$q[0]->id => $scale, $q[1]->id => $yes, $q[2]->id => $choice, $q[3]->id => $text]
            ));
            if ($i === 1) {
                set_config('resultsthreshold', 3, 'mod_syllabus');
                $this->assertFalse(syllabus_get_results($this->syllabus)->shown);
            }
        }
        $results = syllabus_get_results($this->syllabus);
        $this->assertTrue($results->shown);
        $this->assertSame(3, $results->responses);
        $this->assertEquals(4, $results->questions[0]->mean);
        $this->assertSame([1 => 2, 0 => 1], $results->questions[1]->distribution);
        $this->assertSame([0 => 1, 1 => 2, 2 => 0], $results->questions[2]->distribution);
        $this->assertSame(['Abeille', 'Zèbre'], $results->questions[3]->texts);

        // Visibilité pour le responsable.
        set_config('teacherresults', 'afterclose', 'mod_syllabus');
        $this->assertFalse(syllabus_get_rights($this->syllabus, $this->context, (int) $this->leader->id)->canseeresults);
        set_config('teacherresults', 'always', 'mod_syllabus');
        $this->assertTrue(syllabus_get_rights($this->syllabus, $this->context, (int) $this->leader->id)->canseeresults);
        set_config('teacherresults', 'afterclose', 'mod_syllabus');
        syllabus_set_window($this->syllabus, time() - 10, time() - 1);
        $this->assertTrue(syllabus_get_rights($this->syllabus, $this->context, (int) $this->leader->id)->canseeresults);
    }

    /**
     * La tâche fige les questions et prévient chaque étudiant une fois ; une nouvelle date
     * d'ouverture est de nouveau annoncée.
     */
    public function test_open_task_notifies_once(): void {
        global $DB;
        $this->open();
        $sink = $this->redirectMessages();
        $task = new \mod_syllabus\task\open_evaluations();
        $this->expectOutputRegex('/évaluation ouverte, 4 message/');
        $task->execute();
        $this->assertSame(4, $sink->count());
        $this->assertSame(1, (int) $DB->get_field('syllabus', 'evalfrozen', ['id' => $this->syllabus->id]));
        $task->execute();
        $this->assertSame(4, $sink->count());
    }

    /**
     * Réinitialisation du cours : réponses effacées, évaluation de nouveau modifiable.
     */
    public function test_reset_course(): void {
        global $DB;
        $this->open();
        syllabus_freeze_questions($this->syllabus);
        $q = array_values(syllabus_get_module_questions((int) $this->syllabus->id));
        $this->setUser($this->students[0]);
        syllabus_submit_response(
            $this->syllabus,
            $this->context,
            (int) $this->students[0]->id,
            [$q[0]->id => 4, $q[1]->id => 1, $q[2]->id => 2]
        );
        syllabus_reset_userdata((object) ['courseid' => $this->course->id, 'reset_syllabus_responses' => 1]);
        $this->assertSame(0, $DB->count_records('syllabus_response'));
        $this->assertSame(0, $DB->count_records('syllabus_answer'));
        $this->assertSame(0, syllabus_count_responses((int) $this->syllabus->id));
        $this->assertCount(2, syllabus_get_module_questions((int) $this->syllabus->id));
        $this->assertSame(0, (int) $DB->get_field('syllabus', 'evalfrozen', ['id' => $this->syllabus->id]));
    }
}
