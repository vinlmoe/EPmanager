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
 * Référentiel de compétences tenu dans le suivi des modules : arbre, contrôles, import CSV, liens
 * des modules et des cours, matrice, sauvegarde.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabus_save_comp
 * @covers    ::syllabus_import_comps
 * @covers    ::syllabus_set_links
 * @covers    ::syllabus_competency_matrix
 */
final class competency_test extends \advanced_testcase {
    /** @var int */
    protected $frameworkid;
    /** @var array idnumber => id */
    protected $comps = [];
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $syllabus;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->frameworkid = syllabus_save_framework((object) ['name' => 'Référentiel vétérinaire', 'idnumber' => 'REF']);
        $csv = "Code;Code parent;Intitulé;Description\nC1.1;C1;Examen clinique;\nC1;;Démarche clinique;Domaine\n"
            . "C1.2;C1;Diagnostic;\nC2;;Thérapeutique;\nC2.1;C2;Prescrire;\n";
        $result = syllabus_import_comps($this->frameworkid, $csv);
        $this->assertSame(5, $result->created);
        $this->assertSame([], $result->errors);
        $this->comps = $DB->get_records_menu('syllabus_comp', ['frameworkid' => $this->frameworkid], '', 'idnumber, id');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->setAdminUser();
        $record = $gen->create_module('syllabus', ['course' => $this->course->id]);
        $this->syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
    }

    /**
     * Import : un parent cité avant d'être défini est rattaché ; l'arbre suit l'ordre des sœurs.
     */
    public function test_import_builds_tree(): void {
        $tree = syllabus_get_framework_tree($this->frameworkid);
        $this->assertSame(['C1', 'C1.1', 'C1.2', 'C2', 'C2.1'], array_values(array_map(fn($c) => $c->idnumber, $tree)));
        $this->assertSame([0, 1, 1, 0, 1], array_values(array_map(fn($c) => $c->depth, $tree)));
        // Réimport : mise à jour, pas de doublon.
        $result = syllabus_import_comps($this->frameworkid, "Code;Intitulé\nC2;Thérapeutique et prescription\n");
        $this->assertSame(1, $result->updated);
        $tree = syllabus_get_framework_tree($this->frameworkid);
        $this->assertSame('Thérapeutique et prescription', $tree[$this->comps['C2']]->name);
    }

    /**
     * Contrôles : code unique dans le référentiel, parent qui ne descend pas de la compétence.
     */
    public function test_save_comp_checks(): void {
        try {
            syllabus_save_comp((object) ['frameworkid' => $this->frameworkid, 'idnumber' => 'C1', 'name' => 'Doublon']);
            $this->fail('Code en double accepté');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorduplicatecode', $e->errorcode);
        }
        $this->expectExceptionMessage(get_string('errorparent', 'mod_syllabus'));
        syllabus_save_comp((object) ['id' => $this->comps['C1'], 'frameworkid' => $this->frameworkid,
            'parentid' => $this->comps['C1.1'], 'idnumber' => 'C1', 'name' => 'Démarche clinique']);
    }

    /**
     * Déplacement parmi les sœurs.
     */
    public function test_move(): void {
        syllabus_move_comp((int) $this->comps['C1.2'], -1);
        $tree = syllabus_get_framework_tree($this->frameworkid);
        $this->assertSame(['C1', 'C1.2', 'C1.1', 'C2', 'C2.1'], array_values(array_map(fn($c) => $c->idnumber, $tree)));
    }

    /**
     * Liens du module et d'un cours sans module ; matrice ; suppression refusée tant qu'une
     * compétence est visée ; fiche.
     */
    public function test_links_and_matrix(): void {
        $result = syllabus_set_links(
            (int) $this->course->id,
            (int) $this->syllabus->id,
            $this->frameworkid,
            [$this->comps['C1.1'] => 1, $this->comps['C1.2'] => 3, $this->comps['C2.1'] => 0]
        );
        $this->assertSame(2, $result->added);
        $result = syllabus_set_links(
            (int) $this->course->id,
            (int) $this->syllabus->id,
            $this->frameworkid,
            [$this->comps['C1.1'] => 2]
        );
        $this->assertSame([1, 1, 0], [$result->updated, $result->removed, $result->added]);
        $this->assertStringContainsString('Examen clinique', \mod_syllabus\output\fiche::competencies($this->syllabus));

        $other = $this->getDataGenerator()->create_course();
        syllabus_set_links((int) $other->id, 0, $this->frameworkid, [$this->comps['C2.1'] => 3]);
        $matrix = syllabus_competency_matrix($this->frameworkid, [(int) $this->course->id, (int) $other->id]);
        $this->assertSame(2, $matrix[$this->course->id][$this->comps['C1.1']]->level);
        $this->assertFalse($matrix[$this->course->id][$this->comps['C1.1']]->coursewide);
        $this->assertTrue($matrix[$other->id][$this->comps['C2.1']]->coursewide);
        $this->assertCount(1, $matrix[$this->course->id]);

        $this->assertFalse(syllabus_delete_comp((int) $this->comps['C2']));
        $this->assertFalse(syllabus_delete_framework($this->frameworkid));
        delete_course($other, false);
        $this->assertTrue(syllabus_delete_comp((int) $this->comps['C2']));
        $this->assertCount(3, syllabus_get_framework_tree($this->frameworkid));
    }

    /**
     * Copie du cours : les compétences du module suivent, retrouvées par leurs codes.
     */
    public function test_backup_restore_keeps_links(): void {
        global $DB, $USER;
        syllabus_set_links((int) $this->course->id, (int) $this->syllabus->id, $this->frameworkid, [$this->comps['C1.2'] => 3]);
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
        $links = syllabus_get_module_competencies($restored);
        $this->assertCount(1, $links);
        $this->assertEquals(3, reset($links)->level);
        $this->assertSame('C1.2', reset($links)->idnumber);
    }
}
