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
 * Référentiel commun de la DEVE : réglages, types de module, questions communes et questions de
 * l'enseignant, état de l'évaluation.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabus_save_settings
 * @covers    ::syllabus_save_type
 * @covers    ::syllabus_delete_type
 * @covers    ::syllabus_save_question
 * @covers    ::syllabus_applicable_common_questions
 * @covers    ::syllabus_move_question
 * @covers    ::syllabus_delete_question
 * @covers    ::syllabus_eval_state
 */
final class referential_test extends \advanced_testcase {
    /**
     * Réglages : valeurs par défaut, bornes du seuil, visibilité des résultats.
     */
    public function test_settings(): void {
        $this->resetAfterTest();
        $this->assertEquals(1, syllabus_setting('reviewrequired'));
        $this->assertEquals(3, syllabus_setting('resultsthreshold'));
        syllabus_save_settings((object) ['reviewrequired' => 0, 'resultsthreshold' => 0, 'teacherresults' => 'nimporte']);
        $this->assertEquals(0, syllabus_setting('reviewrequired'));
        $this->assertEquals(1, syllabus_setting('resultsthreshold'));
        $this->assertSame('afterclose', syllabus_setting('teacherresults'));
        syllabus_save_settings((object) ['reviewrequired' => 1, 'resultsthreshold' => 500, 'teacherresults' => 'always']);
        $this->assertEquals(50, syllabus_setting('resultsthreshold'));
        $this->assertSame('always', syllabus_setting('teacherresults'));
    }

    /**
     * Types : nom obligatoire, renommage, suppression refusée tant qu'un module ou une question
     * commune le porte.
     */
    public function test_types(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $clinical = syllabus_save_type('Clinique');
        $basic = syllabus_save_type('Fondamental');
        $this->assertSame(['Clinique', 'Fondamental'], array_values(array_map(fn($t) => $t->name, syllabus_get_types())));
        syllabus_save_type('Clinique et pratique', $clinical);
        $this->assertSame('Clinique et pratique', syllabus_get_types()[$clinical]->name);
        $this->assertArrayHasKey(0, syllabus_type_options());
        try {
            syllabus_save_type('  ');
            $this->fail('Nom vide accepté');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorname', $e->errorcode);
        }
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id, 'typeid' => $clinical]);
        syllabus_save_question((object) ['origin' => 'type', 'typeid' => $basic, 'kind' => 'yesno', 'text' => 'Stage utile ?']);
        $this->assertFalse(syllabus_delete_type($clinical));
        $this->assertFalse(syllabus_delete_type($basic));
        $third = syllabus_save_type('Optionnel');
        $this->assertTrue(syllabus_delete_type($third));
        $this->assertCount(2, syllabus_get_types());
    }

    /**
     * Questions : contrôles de saisie ; questions communes applicables à un module (toutes, puis
     * celles de son type, actives seulement) ; ordre ; suppression ; module figé intouchable.
     */
    public function test_questions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        foreach (
            [['text' => ''], ['kind' => 'choice', 'text' => 'Choix', 'options' => "Un seul\n\n"],
                ['origin' => 'type', 'text' => 'Sans type']] as $bad
        ) {
            try {
                syllabus_save_question((object) $bad);
                $this->fail('Question invalide acceptée');
            } catch (\moodle_exception $e) {
                $this->assertContains($e->errorcode, ['errorquestiontext', 'errorchoices', 'errortype']);
            }
        }
        $clinical = syllabus_save_type('Clinique');
        $other = syllabus_save_type('Fondamental');
        $q1 = syllabus_save_question((object) ['kind' => 'scale', 'text' => 'Organisation', 'required' => 1]);
        $q2 = syllabus_save_question((object) ['kind' => 'text', 'text' => 'Remarques']);
        $off = syllabus_save_question((object) ['kind' => 'scale', 'text' => 'Désactivée', 'enabled' => 0]);
        $qtype = syllabus_save_question((object) ['origin' => 'type', 'typeid' => $clinical, 'kind' => 'yesno',
            'text' => 'Cas cliniques ?']);
        syllabus_save_question((object) ['origin' => 'type', 'typeid' => $other, 'kind' => 'yesno', 'text' => 'Autre type']);
        $course = $this->getDataGenerator()->create_course();
        $record = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id, 'typeid' => $clinical]);
        $syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
        $ids = fn() => array_map(fn($q) => (int) $q->id, syllabus_applicable_common_questions($syllabus));
        $this->assertSame([$q1, $q2, $qtype], $ids());
        $this->assertNotContains($off, $ids());

        syllabus_move_question($q2, 0, -1);
        $this->assertSame([$q2, $q1, $qtype], $ids());
        syllabus_move_question($q2, 0, -1);
        $this->assertSame([$q2, $q1, $qtype], $ids(), 'La première ne monte pas plus haut.');
        syllabus_delete_question($q2, 0);
        $this->assertSame([$q1, $qtype], $ids());

        $mine = syllabus_save_question((object) ['syllabusid' => $syllabus->id, 'kind' => 'scale', 'text' => 'Supports']);
        $this->assertSame('teacher', $DB->get_field('syllabus_question', 'origin', ['id' => $mine]));
        $DB->set_field('syllabus', 'evalfrozen', 1, ['id' => $syllabus->id]);
        foreach (
            [
            fn() => syllabus_save_question((object) ['syllabusid' => $syllabus->id, 'kind' => 'scale', 'text' => 'Nouvelle']),
            fn() => syllabus_delete_question($mine, (int) $syllabus->id),
            fn() => syllabus_move_question($mine, (int) $syllabus->id, 1),
            ] as $change
        ) {
            try {
                $change();
                $this->fail('Module figé modifié');
            } catch (\moodle_exception $e) {
                $this->assertSame('errorfrozen', $e->errorcode);
            }
        }
    }

    /**
     * État de l'évaluation selon la fenêtre.
     */
    public function test_eval_state(): void {
        $now = 1000000;
        $window = fn($open, $close) => (object) ['evalopen' => $open, 'evalclose' => $close];
        $this->assertSame(SYLLABUS_EVAL_NONE, syllabus_eval_state($window(0, 0), $now));
        $this->assertSame(SYLLABUS_EVAL_UPCOMING, syllabus_eval_state($window($now + 1, 0), $now));
        $this->assertSame(SYLLABUS_EVAL_OPEN, syllabus_eval_state($window($now, 0), $now));
        $this->assertSame(SYLLABUS_EVAL_OPEN, syllabus_eval_state($window($now - 10, $now + 1), $now));
        $this->assertSame(SYLLABUS_EVAL_CLOSED, syllabus_eval_state($window($now - 10, $now), $now));
    }
}
