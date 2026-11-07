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

namespace mod_syllabusstaff;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');

/**
 * Suivi des modules : fenêtres en lot, moyennes comparées, saisie de date.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabusstaff_set_windows
 * @covers    ::syllabus_common_means
 * @covers    ::syllabusstaff_booklet_file
 * @covers    ::syllabusstaff_scope_courses
 * @covers    ::syllabusstaff_navlinks
 */
final class staff_test extends \advanced_testcase {
    /**
     * La DEVE ne fixe la fenêtre que des modules où elle a le rôle de la DEVE.
     */
    public function test_set_windows_respects_rights(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $deve = $gen->create_user();
        $mine = $gen->create_course();
        $other = $gen->create_course();
        $gen->enrol_user($deve->id, $mine->id, 'manager');
        $this->setAdminUser();
        $a = $gen->create_module('syllabus', ['course' => $mine->id]);
        $b = $gen->create_module('syllabus', ['course' => $other->id]);
        $this->setUser($deve);
        $open = time() + DAYSECS;
        $result = syllabusstaff_set_windows([$a->id, $b->id], $open, $open + WEEKSECS);
        $this->assertSame(1, $result->done);
        $this->assertSame(1, $result->skipped);
        $this->assertEquals($open, $DB->get_field('syllabus', 'evalopen', ['id' => $a->id]));
        $this->assertEquals(0, $DB->get_field('syllabus', 'evalopen', ['id' => $b->id]));
    }

    /**
     * Moyennes par question commune d'origine, seulement au-delà du seuil.
     */
    public function test_common_means(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $sgen = $gen->get_plugin_generator('mod_syllabus');
        $common = $sgen->create_question(['text' => 'Organisation']);
        $course = $gen->create_course();
        $students = [];
        for ($i = 0; $i < 3; $i++) {
            $students[] = $gen->create_and_enrol($course, 'student');
        }
        $this->setAdminUser();
        $record = $gen->create_module('syllabus', ['course' => $course->id, 'evalopen' => time() - 10]);
        $syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
        $context = \context_module::instance($record->cmid);
        syllabus_freeze_questions($syllabus);
        $copy = $DB->get_record('syllabus_question', ['syllabusid' => $syllabus->id, 'sourceid' => $common]);
        set_config('resultsthreshold', 3, 'mod_syllabus');
        foreach ([2, 4, 5] as $i => $value) {
            $this->setUser($students[$i]);
            syllabus_submit_response($syllabus, $context, (int) $students[$i]->id, [$copy->id => $value]);
            $means = syllabus_common_means([(int) $syllabus->id]);
            $this->assertSame($i < 2 ? [] : [(int) $syllabus->id => [$common => 3.67]], $means);
        }
    }

    /**
     * Saisie d'un champ datetime-local.
     */
    public function test_parse_datetime(): void {
        $this->resetAfterTest();
        $this->assertSame(0, syllabusstaff_parse_datetime(''));
        $this->assertSame(make_timestamp(2026, 12, 1, 8, 30), syllabusstaff_parse_datetime('2026-12-01T08:30'));
    }

    /**
     * Livret conservé : resservi tant que rien ne change, refait (et l'ancien retiré) sinon ;
     * un livret par choix d'édition.
     */
    public function test_booklet_file_is_kept(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $staff = $gen->create_module('syllabusstaff', ['course' => $course->id]);
        $instance = $DB->get_record('syllabusstaff', ['id' => $staff->id]);
        $context = \context_module::instance($staff->cmid);
        $record = $gen->create_module('syllabus', ['course' => $course->id]);
        $module = $DB->get_record('syllabus', ['id' => $record->id]);
        $module->coursename = $course->fullname;
        $module->published = null;
        $module->teachers = [];
        $builds = 0;
        $build = function () use (&$builds) {
            $builds++;
            return 'PDF ' . $builds;
        };
        $first = syllabusstaff_booklet_file($context, $instance, 'all', [$module], $build);
        $again = syllabusstaff_booklet_file($context, $instance, 'all', [$module], $build);
        $this->assertSame(1, $builds);
        $this->assertEquals($first->get_id(), $again->get_id());
        syllabusstaff_booklet_file($context, $instance, 'y3', [$module], $build);
        $module->timemodified++;
        $changed = syllabusstaff_booklet_file($context, $instance, 'all', [$module], $build);
        $this->assertSame(3, $builds);
        $this->assertSame('PDF 3', $changed->get_content());
        $files = get_file_storage()->get_area_files($context->id, 'mod_syllabusstaff', 'booklet', 0, 'id', false);
        $this->assertCount(2, $files);
    }

    /**
     * Périmètre : la catégorie et ses sous-catégories, ou tout le site sans la page d'accueil.
     */
    public function test_scope(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $cursus = $gen->create_category();
        $year = $gen->create_category(['parent' => $cursus->id]);
        $elsewhere = $gen->create_category();
        $a = $gen->create_course(['category' => $cursus->id]);
        $b = $gen->create_course(['category' => $year->id]);
        $c = $gen->create_course(['category' => $elsewhere->id]);
        $scoped = (object) ['categoryid' => $cursus->id];
        $ids = array_map('intval', array_keys(syllabusstaff_scope_courses($scoped)));
        sort($ids);
        $this->assertSame([(int) $a->id, (int) $b->id], $ids);
        $this->assertTrue(syllabusstaff_course_in_scope($scoped, $b));
        $this->assertFalse(syllabusstaff_course_in_scope($scoped, $c));
        $site = array_keys(syllabusstaff_scope_courses((object) ['categoryid' => 0]));
        $this->assertNotContains(SITEID, $site);
        $this->assertContains((int) $c->id, array_map('intval', $site));
    }

    /**
     * Onglets : ceux de la DEVE seulement avec le droit de tout gérer.
     */
    public function test_navlinks(): void {
        $cm = (object) ['id' => 7];
        $teacher = syllabusstaff_navlinks($cm, 'view', false);
        $deve = syllabusstaff_navlinks($cm, 'view', true);
        foreach (['view.php', 'competencies.php', 'progression.php', 'booklet.php'] as $page) {
            $this->assertStringContainsString($page, $teacher);
        }
        foreach (['all.php', 'review.php', 'results.php', 'referential.php', 'frameworks.php'] as $page) {
            $this->assertStringNotContainsString($page, $teacher);
            $this->assertStringContainsString($page, $deve);
        }
    }
}
