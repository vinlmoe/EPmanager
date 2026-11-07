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
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Compétences : liens de Moodle au cours et au module, niveaux, matrice, fiche, sauvegarde.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabus_set_module_competencies
 * @covers    ::syllabus_competency_matrix
 */
final class competency_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $syllabus;
    /** @var \stdClass */
    protected $cm;
    /** @var \stdClass */
    protected $leader;
    /** @var \stdClass */
    protected $framework;
    /** @var array Compétences : domaine, puis trois compétences. */
    protected $comps = [];

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $cgen = $gen->get_plugin_generator('core_competency');
        $this->framework = $cgen->create_framework(['shortname' => 'Référentiel vétérinaire', 'idnumber' => 'REF']);
        $fid = $this->framework->get('id');
        $domain = $cgen->create_competency(['competencyframeworkid' => $fid, 'shortname' => 'Clinique', 'idnumber' => 'C']);
        $this->comps[] = $domain;
        foreach (['Examen clinique', 'Diagnostic', 'Traitement'] as $i => $name) {
            $this->comps[] = $cgen->create_competency(['competencyframeworkid' => $fid, 'parentid' => $domain->get('id'),
                'shortname' => $name, 'idnumber' => 'C' . ($i + 1)]);
        }
        $this->course = $gen->create_course();
        $this->leader = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->leader);
        $record = $gen->create_module('syllabus', ['course' => $this->course->id]);
        $this->syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
        $this->cm = get_coursemodule_from_instance('syllabus', $record->id);
    }

    /**
     * Arbre du référentiel, avec la profondeur.
     */
    public function test_framework_tree(): void {
        $tree = syllabus_get_framework_tree($this->framework->get('id'));
        $this->assertSame(
            ['Clinique', 'Examen clinique', 'Diagnostic', 'Traitement'],
            array_values(array_map(fn($c) => $c->shortname, $tree))
        );
        $this->assertSame([0, 1, 1, 1], array_values(array_map(fn($c) => $c->depth, $tree)));
        $this->assertFalse(reset($tree)->isleaf);
    }

    /**
     * Lier : la compétence est liée au cours puis au module (liens de Moodle), avec son niveau ;
     * changer un niveau ; retirer du module garde le lien au cours.
     */
    public function test_link_update_unlink(): void {
        global $DB;
        [, $exam, $diag] = $this->comps;
        $fid = (int) $this->framework->get('id');
        $result = syllabus_set_module_competencies($this->syllabus, $this->cm, $fid, [$exam->get('id') => 1,
            $diag->get('id') => 3]);
        $this->assertSame(2, $result->added);
        $this->assertTrue($DB->record_exists('competency_coursecomp', ['courseid' => $this->course->id,
            'competencyid' => $exam->get('id')]));
        $this->assertTrue($DB->record_exists('competency_modulecomp', ['cmid' => $this->cm->id,
            'competencyid' => $diag->get('id')]));
        $linked = syllabus_get_module_competencies($this->syllabus, (int) $this->cm->id);
        $this->assertEquals(3, $linked[$diag->get('id')]->level);

        $result = syllabus_set_module_competencies($this->syllabus, $this->cm, $fid, [$exam->get('id') => 2]);
        $this->assertSame(1, $result->updated);
        $this->assertSame(1, $result->removed);
        $this->assertFalse($DB->record_exists('competency_modulecomp', ['cmid' => $this->cm->id,
            'competencyid' => $diag->get('id')]));
        $this->assertTrue($DB->record_exists('competency_coursecomp', ['courseid' => $this->course->id,
            'competencyid' => $diag->get('id')]));
        $this->assertFalse($DB->record_exists('syllabus_competency', ['competencyid' => $diag->get('id')]));

        // La fiche les montre.
        $this->assertStringContainsString('Examen clinique', \mod_syllabus\output\fiche::competencies($this->syllabus));
    }

    /**
     * Un enseignant non éditeur, même responsable, ne peut pas lier (capacité de Moodle).
     */
    public function test_non_editing_teacher_cannot_link(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        syllabus_set_teachers($this->syllabus, \context_module::instance($this->cm->id), [$teacher->id]);
        $rights = syllabus_get_rights($this->syllabus, \context_module::instance($this->cm->id), (int) $teacher->id);
        $this->assertTrue($rights->canedit);
        $this->setUser($teacher);
        $this->assertFalse(syllabus_can_link_competencies($rights, (int) $this->course->id));
        $this->expectException(\required_capability_exception::class);
        syllabus_set_module_competencies(
            $this->syllabus,
            $this->cm,
            (int) $this->framework->get('id'),
            [$this->comps[1]->get('id') => 1]
        );
    }

    /**
     * Matrice : niveau du module, ou lien au cours seulement (-1).
     */
    public function test_matrix(): void {
        [, $exam, $diag, $treat] = $this->comps;
        syllabus_set_module_competencies(
            $this->syllabus,
            $this->cm,
            (int) $this->framework->get('id'),
            [$exam->get('id') => 2]
        );
        \core_competency\api::add_competency_to_course($this->course->id, $treat->get('id'));
        $other = $this->getDataGenerator()->create_course();
        $matrix = syllabus_competency_matrix((int) $this->framework->get('id'), [(int) $this->course->id, (int) $other->id]);
        $this->assertSame([(int) $exam->get('id') => 2, (int) $treat->get('id') => -1], $matrix[$this->course->id]);
        $this->assertSame([], $matrix[$other->id]);
        $this->assertArrayNotHasKey($diag->get('id'), $matrix[$this->course->id]);
    }

    /**
     * Copie du cours : liens de Moodle et niveaux suivent.
     */
    public function test_backup_restore_keeps_levels(): void {
        global $DB, $USER;
        syllabus_set_module_competencies(
            $this->syllabus,
            $this->cm,
            (int) $this->framework->get('id'),
            [$this->comps[2]->get('id') => 3]
        );
        $this->setAdminUser();
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $this->course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();
        $newcourse = $this->getDataGenerator()->create_course();
        $rc = new \restore_controller(
            $backupid,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        $restored = $DB->get_record('syllabus', ['course' => $newcourse->id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('syllabus', $restored->id);
        $linked = syllabus_get_module_competencies($restored, (int) $cm->id);
        $this->assertCount(1, $linked);
        $this->assertEquals(3, reset($linked)->level);
    }
}
