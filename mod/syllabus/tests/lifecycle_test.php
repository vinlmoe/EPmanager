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
 * Cycle de vie : messages (soumission, décision, ouverture), événement d'évaluation, responsables
 * possibles, suppression de l'activité et du cours.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabus_notify_submitted
 * @covers    ::syllabus_notify_reviewed
 * @covers    ::syllabus_notify_evaluation_open
 * @covers    ::syllabus_potential_teachers
 * @covers    ::syllabus_delete_instance
 * @covers    \mod_syllabus\observer
 * @covers    \mod_syllabus\event\evaluation_submitted
 * @covers    ::syllabus_window_locked
 */
final class lifecycle_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $syllabus;
    /** @var \stdClass */
    protected $cm;
    /** @var \context_module */
    protected $context;
    /** @var array Comptes par rôle. */
    protected $users = [];

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        foreach (
            ['leader' => 'editingteacher', 'colleague' => 'teacher', 'deve' => 'manager', 's1' => 'student',
                's2' => 'student'] as $key => $role
        ) {
            $this->users[$key] = $gen->create_and_enrol($this->course, $role);
        }
        $this->setUser($this->users['leader']);
        $record = $gen->create_module('syllabus', ['course' => $this->course->id]);
        $this->cm = get_coursemodule_from_instance('syllabus', $record->id);
        $this->context = \context_module::instance($this->cm->id);
        $this->syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
    }

    /**
     * Soumission : la DEVE est prévenue ; décision : les responsables ; ouverture : les étudiants
     * qui n'ont pas encore répondu, pas les enseignants.
     */
    public function test_messages(): void {
        global $DB;
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        syllabus_notify_submitted($this->syllabus, $this->cm, (int) $this->users['leader']->id);
        $this->assertSame([(int) $this->users['deve']->id], array_map(fn($m) => (int) $m->useridto, $sink->get_messages()));
        $sink->clear();

        $version = syllabus_start_working($this->syllabus, (int) $this->users['leader']->id);
        syllabus_notify_reviewed($this->syllabus, $this->cm, $version, 'return', 'Préciser l\'évaluation.');
        $messages = $sink->get_messages();
        $this->assertSame([(int) $this->users['leader']->id], array_map(fn($m) => (int) $m->useridto, $messages));
        $this->assertStringContainsString('Préciser', $messages[0]->fullmessage);
        $sink->clear();

        syllabus_set_window($this->syllabus, time() - 60, 0);
        $this->syllabus = $DB->get_record('syllabus', ['id' => $this->syllabus->id]);
        $this->setUser($this->users['s1']);
        syllabus_submit_response($this->syllabus, $this->context, (int) $this->users['s1']->id, []);
        $this->setAdminUser();
        $this->assertSame(1, syllabus_notify_evaluation_open($this->syllabus, $this->cm));
        $this->assertSame([(int) $this->users['s2']->id], array_map(fn($m) => (int) $m->useridto, $sink->get_messages()));
        $sink->close();
    }

    /**
     * L'envoi d'une évaluation laisse un événement sur le module, sans réponse dans ses données.
     */
    public function test_evaluation_event(): void {
        global $DB;
        syllabus_save_question((object) ['syllabusid' => $this->syllabus->id, 'kind' => 'scale', 'text' => 'Utile ?']);
        syllabus_set_window($this->syllabus, time() - 60, 0);
        $this->syllabus = $DB->get_record('syllabus', ['id' => $this->syllabus->id]);
        $this->setUser($this->users['s1']);
        $question = current(syllabus_get_module_questions((int) $this->syllabus->id));
        $events = $this->redirectEvents();
        syllabus_submit_response($this->syllabus, $this->context, (int) $this->users['s1']->id, [$question->id => 4]);
        $submitted = array_values(array_filter(
            $events->get_events(),
            fn($e) => $e instanceof \mod_syllabus\event\evaluation_submitted
        ));
        $this->assertCount(1, $submitted);
        $this->assertEquals($this->syllabus->id, $submitted[0]->objectid);
        $this->assertEquals($this->context->id, $submitted[0]->contextid);
        $this->assertEmpty($submitted[0]->other);
        // Anonyme : le journal ne relie pas l'avis à son auteur.
        $this->assertSame(1, (int) $submitted[0]->anonymous);
    }

    /**
     * Fenêtre du recueil : libre tant qu'aucun avis n'a été donné, ensuite réservée à la DEVE,
     * par la page du recueil comme par les paramètres de l'activité.
     */
    public function test_window_locked_after_first_response(): void {
        global $DB;
        $this->assertFalse(syllabus_window_locked($this->syllabus, $this->context));
        syllabus_save_question((object) ['syllabusid' => $this->syllabus->id, 'kind' => 'scale', 'text' => 'Utile ?']);
        syllabus_set_window($this->syllabus, time() - 60, 0);
        $this->setUser($this->users['s1']);
        $question = current(syllabus_get_module_questions((int) $this->syllabus->id));
        syllabus_submit_response($this->syllabus, $this->context, (int) $this->users['s1']->id, [$question->id => 4]);

        $this->setUser($this->users['leader']);
        $this->assertTrue(syllabus_window_locked($this->syllabus, $this->context));
        $data = (object) ['instance' => $this->syllabus->id, 'coursemodule' => $this->cm->id, 'name' => $this->syllabus->name,
            'evalopen' => time() - 7200, 'evalclose' => time() - 3600];
        syllabus_update_instance($data);
        $this->assertEquals(0, $DB->get_field('syllabus', 'evalclose', ['id' => $this->syllabus->id]));

        $this->setUser($this->users['deve']);
        $this->assertFalse(syllabus_window_locked($this->syllabus, $this->context));
    }

    /**
     * Responsables possibles : les enseignants du cours (éditeurs ou non), pas les étudiants ; la
     * DEVE seulement si elle est déjà responsable.
     */
    public function test_potential_teachers(): void {
        $ids = fn() => (function () {
            $list = array_map('intval', array_keys(syllabus_potential_teachers($this->context)));
            sort($list);
            return $list;
        })();
        $expected = [(int) $this->users['leader']->id, (int) $this->users['colleague']->id];
        sort($expected);
        $this->assertSame($expected, $ids());
        $GLOBALS['DB']->insert_record('syllabus_teacher', ['syllabusid' => $this->syllabus->id,
            'userid' => $this->users['deve']->id, 'timecreated' => time()]);
        $expected[] = (int) $this->users['deve']->id;
        sort($expected);
        $this->assertSame($expected, $ids());
    }

    /**
     * Suppression de l'activité : plus rien du module en base ; suppression d'un cours sans module :
     * ses liens aux compétences disparaissent.
     */
    public function test_deletion(): void {
        global $DB;
        $this->setAdminUser();
        $version = syllabus_start_working($this->syllabus, (int) $this->users['leader']->id);
        syllabus_save_working(
            $version,
            (object) ['objectives' => 'Savoir'],
            [['title' => 'Séance', 'duration' => 2]],
            (int) $this->users['leader']->id
        );
        $qid = syllabus_save_question((object) ['syllabusid' => $this->syllabus->id, 'kind' => 'scale', 'text' => 'Utile ?']);
        syllabus_set_window($this->syllabus, time() - 60, 0);
        $this->syllabus = $DB->get_record('syllabus', ['id' => $this->syllabus->id]);
        syllabus_submit_response($this->syllabus, $this->context, (int) $this->users['s1']->id, [$qid => 5]);
        $fw = syllabus_save_framework((object) ['name' => 'Réf', 'idnumber' => 'R']);
        syllabus_import_comps($fw, "Code;Intitulé\nC1;Une\n");
        $comp = (int) $DB->get_field('syllabus_comp', 'id', ['frameworkid' => $fw]);
        syllabus_set_links((int) $this->course->id, (int) $this->syllabus->id, $fw, [$comp => 2]);
        $stage = $this->getDataGenerator()->create_course();
        syllabus_set_links((int) $stage->id, 0, $fw, [$comp => 3]);

        course_delete_module($this->cm->id);
        foreach (
            ['syllabus_version', 'syllabus_teacher', 'syllabus_question', 'syllabus_response', 'syllabus_participant',
                'syllabus_complink'] as $table
        ) {
            $this->assertSame(0, $DB->count_records($table, ['syllabusid' => $this->syllabus->id]), $table);
        }
        $this->assertSame(0, $DB->count_records('syllabus_session'));
        $this->assertSame(0, $DB->count_records('syllabus_answer'));
        $this->assertSame(1, $DB->count_records('syllabus_complink', ['courseid' => $stage->id]));
        delete_course($stage, false);
        $this->assertSame(0, $DB->count_records('syllabus_complink'));
        $this->assertFalse(syllabus_delete_instance((int) $this->syllabus->id));
    }
}
