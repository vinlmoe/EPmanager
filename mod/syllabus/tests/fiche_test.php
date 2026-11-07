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
 * Fiche du module : responsables, droits, versions, relecture par la DEVE, listes.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabus_get_rights
 * @covers    ::syllabus_start_working
 * @covers    ::syllabus_submit
 * @covers    ::syllabus_review
 * @covers    ::syllabus_list_instances
 */
final class fiche_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $syllabus;
    /** @var \stdClass */
    protected $cm;
    /** @var \context_module */
    protected $context;
    /** @var \stdClass Responsable (enseignant éditeur qui a créé l'activité). */
    protected $leader;
    /** @var \stdClass Autre enseignant du cours. */
    protected $colleague;
    /** @var \stdClass */
    protected $student;
    /** @var \stdClass DEVE (gestionnaire). */
    protected $deve;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->leader = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->colleague = $gen->create_and_enrol($this->course, 'teacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->deve = $gen->create_and_enrol($this->course, 'manager');
        $this->setUser($this->leader);
        $this->syllabus = $gen->create_module('syllabus', ['course' => $this->course->id]);
        $this->cm = get_coursemodule_from_instance('syllabus', $this->syllabus->id);
        $this->context = \context_module::instance($this->cm->id);
        $this->syllabus = $GLOBALS['DB']->get_record('syllabus', ['id' => $this->syllabus->id]);
    }

    /**
     * Celui qui crée l'activité en devient responsable ; un collègue non désigné ne rédige pas ;
     * l'étudiant évalue ; la DEVE peut tout.
     */
    public function test_rights(): void {
        $leader = syllabus_get_rights($this->syllabus, $this->context, (int) $this->leader->id);
        $this->assertTrue($leader->isteacher);
        $this->assertTrue($leader->canedit);
        $this->assertFalse($leader->canmanage);
        $colleague = syllabus_get_rights($this->syllabus, $this->context, (int) $this->colleague->id);
        $this->assertFalse($colleague->canedit);
        $student = syllabus_get_rights($this->syllabus, $this->context, (int) $this->student->id);
        $this->assertFalse($student->canedit);
        $this->assertTrue($student->canevaluate);
        $deve = syllabus_get_rights($this->syllabus, $this->context, (int) $this->deve->id);
        $this->assertTrue($deve->canmanage);
        $this->assertTrue($deve->canedit);
        $this->assertTrue($deve->canseeresults);

        // Un étudiant ne peut pas être désigné responsable.
        $count = syllabus_set_teachers($this->syllabus, $this->context, [$this->leader->id, $this->colleague->id,
            $this->student->id]);
        $this->assertSame(2, $count);
        $this->assertTrue(syllabus_get_rights($this->syllabus, $this->context, (int) $this->colleague->id)->canedit);
    }

    /**
     * Un module sans responsable reste rédigeable par les enseignants du cours.
     */
    public function test_orphan_module_can_be_edited_by_teachers(): void {
        syllabus_set_teachers($this->syllabus, $this->context, []);
        $this->assertTrue(syllabus_get_rights($this->syllabus, $this->context, (int) $this->colleague->id)->canedit);
    }

    /**
     * Circuit avec relecture : brouillon, soumission, renvoi, nouvelle soumission, publication ;
     * puis mise à jour à partir de la version publiée, qui reste visible jusqu'à la suivante.
     */
    public function test_review_workflow(): void {
        global $DB;
        set_config('reviewrequired', 1, 'mod_syllabus');

        $version = syllabus_start_working($this->syllabus, (int) $this->leader->id);
        syllabus_save_working(
            $version,
            (object) ['objectives' => 'Comprendre', 'language' => 'Français',
            'personalhours' => 20],
            [['title' => 'Introduction', 'kind' => 'lecture', 'duration' => 2], ['title' => '']],
            (int) $this->leader->id
        );
        $this->assertCount(1, syllabus_get_sessions((int) $version->id));
        $this->assertSame('submitted', syllabus_submit($this->syllabus, $this->cm, $version, (int) $this->leader->id));
        $this->assertNull(syllabus_get_published((int) $this->syllabus->id));

        $sink = $this->redirectMessages();
        $version = syllabus_get_working((int) $this->syllabus->id);
        $this->assertTrue(syllabus_review($this->syllabus, $this->cm, $version, (int) $this->deve->id, 'return', 'Préciser'));
        $this->assertSame(1, $sink->count());
        $this->assertFalse(syllabus_review($this->syllabus, $this->cm, $version, (int) $this->deve->id, 'publish', ''));

        $version = syllabus_get_working((int) $this->syllabus->id);
        $this->assertSame('returned', $version->status);
        syllabus_save_working($version, (object) ['objectives' => 'Comprendre et appliquer'], null, (int) $this->leader->id);
        syllabus_submit($this->syllabus, $this->cm, syllabus_get_working((int) $this->syllabus->id), (int) $this->leader->id);
        syllabus_review(
            $this->syllabus,
            $this->cm,
            syllabus_get_working((int) $this->syllabus->id),
            (int) $this->deve->id,
            'publish',
            ''
        );
        $published = syllabus_get_published((int) $this->syllabus->id);
        $this->assertSame('Comprendre et appliquer', $published->objectives);
        $this->assertNull(syllabus_get_working((int) $this->syllabus->id));

        // Mise à jour : copie de la version publiée, séances comprises.
        $update = syllabus_start_working($this->syllabus, (int) $this->leader->id);
        $this->assertSame(2, (int) $update->version);
        $this->assertSame('Comprendre et appliquer', $update->objectives);
        $this->assertCount(1, syllabus_get_sessions((int) $update->id));
        syllabus_publish($this->syllabus, $update, (int) $this->deve->id, '');
        $this->assertSame(2, (int) syllabus_get_published((int) $this->syllabus->id)->version);
        $this->assertSame('superseded', $DB->get_field('syllabus_version', 'status', ['id' => $published->id]));
    }

    /**
     * Sans relecture, la soumission publie aussitôt.
     */
    public function test_submit_without_review_publishes(): void {
        set_config('reviewrequired', 0, 'mod_syllabus');
        $version = syllabus_start_working($this->syllabus, (int) $this->leader->id);
        $this->assertSame('published', syllabus_submit($this->syllabus, $this->cm, $version, (int) $this->leader->id));
        $this->assertNotNull(syllabus_get_published((int) $this->syllabus->id));
    }

    /**
     * Une version soumise ne se modifie plus.
     */
    public function test_submitted_version_is_locked(): void {
        set_config('reviewrequired', 1, 'mod_syllabus');
        $version = syllabus_start_working($this->syllabus, (int) $this->leader->id);
        syllabus_submit($this->syllabus, $this->cm, $version, (int) $this->leader->id);
        $this->expectException(\moodle_exception::class);
        syllabus_save_working(
            syllabus_get_working((int) $this->syllabus->id),
            (object) ['objectives' => 'x'],
            null,
            (int) $this->leader->id
        );
    }

    /**
     * Volume horaire par nature, avec le travail personnel.
     */
    public function test_hours(): void {
        $hours = syllabus_hours([(object) ['kind' => 'lecture', 'duration' => 2], (object) ['kind' => 'lecture', 'duration' => 1.5],
            (object) ['kind' => 'practical', 'duration' => 3]], 20);
        $this->assertEquals(3.5, $hours->bykind['lecture']);
        $this->assertEquals(6.5, $hours->sessions);
        $this->assertEquals(26.5, $hours->total);
    }

    /**
     * Listes des synthèses : par responsable, par catégorie, par recherche, par état de version.
     */
    public function test_list_instances(): void {
        $gen = $this->getDataGenerator();
        $category = $gen->create_category();
        $other = $gen->create_course(['category' => $category->id, 'fullname' => 'Pharmacologie']);
        $gen->enrol_user($this->colleague->id, $other->id, 'editingteacher');
        $this->setUser($this->colleague);
        $gen->create_module('syllabus', ['course' => $other->id, 'name' => 'Pharmacologie générale', 'code' => 'PHA1']);

        [$rows, $total] = syllabus_list_instances(['teacherid' => $this->leader->id]);
        $this->assertSame(1, $total);
        $this->assertEquals($this->syllabus->id, reset($rows)->id);
        [, $total] = syllabus_list_instances(['categoryid' => $category->id]);
        $this->assertSame(1, $total);
        [, $total] = syllabus_list_instances(['search' => 'pha1']);
        $this->assertSame(1, $total);
        [, $total] = syllabus_list_instances(['status' => 'nopublished']);
        $this->assertSame(2, $total);
        [, $total] = syllabus_list_instances(['courseids' => [$other->id]]);
        $this->assertSame(1, $total);
    }
}
