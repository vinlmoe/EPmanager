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
 * Cursus : ordre des périodes, choix d'édition, livret PDF, progression d'une compétence.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabus_sort_by_period
 * @covers    ::syllabus_filter_by_selection
 * @covers    ::syllabus_progression_summary
 * @covers    ::syllabus_competency_links
 * @covers    \mod_syllabus\pdf\booklet_pdf
 */
final class cursus_test extends \advanced_testcase {
    /**
     * Module fictif pour le tri.
     *
     * @param int $year
     * @param string $semester
     * @param string $code
     * @return \stdClass
     */
    protected function module(int $year, string $semester, string $code): \stdClass {
        return (object) ['studyyear' => $year, 'semester' => $semester, 'code' => $code, 'name' => $code];
    }

    /**
     * Ordre du cursus : année, période en ordre naturel, non précisé en dernier ; choix d'édition.
     */
    public function test_sort_and_selections(): void {
        $modules = [
            1 => $this->module(0, '', 'X'), 2 => $this->module(2, 'S10', 'B'), 3 => $this->module(2, 'S3', 'A2'),
            4 => $this->module(1, 'S2', 'A10'), 5 => $this->module(1, 'S2', 'A9'), 6 => $this->module(1, '', 'Z'),
            7 => $this->module(1, 'S1', 'C'),
        ];
        $this->assertSame([7, 5, 4, 6, 3, 2, 1], array_keys(syllabus_sort_by_period($modules)));
        $this->assertSame([3, 2], array_keys(syllabus_filter_by_selection($modules, 'y2')));
        $this->assertSame([5, 4], array_keys(syllabus_filter_by_selection($modules, 's' . 's2')));
        $this->assertCount(7, syllabus_filter_by_selection($modules, 'all'));
        $options = syllabus_period_selections($modules);
        $this->assertSame(['y1', 'y2'], array_keys(reset($options[1])));
        $this->assertSame(['sS1', 'sS2', 'sS3', 'sS10'], array_keys(reset($options[2])));
        $this->assertSame(['2|s3', syllabus_studyyear_label(2) . ' · S3'], syllabus_period_of($modules[3]));
    }

    /**
     * Constats : premier niveau atteint par période, et anomalies de progression.
     */
    public function test_progression_summary(): void {
        $step = fn(string $label, array $levels) => (object) ['label' => $label, 'levels' => $levels];
        $good = syllabus_progression_summary([$step('S1', [1]), $step('S2', []), $step('S3', [2, 1]), $step('S5', [3])]);
        $this->assertSame([1 => 'S1', 2 => 'S3', 3 => 'S5'], $good->first);
        $this->assertSame([], $good->warnings);
        $bad = syllabus_progression_summary([$step('S1', [2]), $step('S3', [3]), $step('S5', [1])]);
        $this->assertSame(['noprogression', 'introducedlate'], $bad->warnings);
        $this->assertSame(['nevermastered'], syllabus_progression_summary([$step('S1', [1, 2])])->warnings);
        $this->assertSame(['notcovered'], syllabus_progression_summary([$step('S1', [])])->warnings);
    }

    /**
     * Liens d'une compétence, avec ou sans ses sous-compétences, limités aux cours donnés.
     */
    public function test_competency_links(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $fw = syllabus_save_framework((object) ['name' => 'Réf', 'idnumber' => 'R']);
        syllabus_import_comps($fw, "Code;Code parent;Intitulé\nD1;;Domaine\nD1.1;D1;Une\nD1.2;D1;Deux\n");
        $ids = $DB->get_records_menu('syllabus_comp', ['frameworkid' => $fw], '', 'idnumber, id');
        $gen = $this->getDataGenerator();
        $a = $gen->create_course();
        $b = $gen->create_course();
        $module = $gen->create_module('syllabus', ['course' => $a->id]);
        syllabus_set_links($a->id, $module->id, $fw, [$ids['D1.1'] => 1, $ids['D1.2'] => 3]);
        syllabus_set_links($b->id, 0, $fw, [$ids['D1'] => 2]);
        $this->assertCount(3, syllabus_competency_links($ids['D1'], true, [$a->id, $b->id]));
        $this->assertCount(1, syllabus_competency_links($ids['D1'], false, [$a->id, $b->id]));
        $this->assertCount(2, syllabus_competency_links($ids['D1'], true, [$a->id]));
        $this->assertSame([], syllabus_competency_links($ids['D1'], true, []));
    }

    /**
     * Livret : une fiche par module publié, signets par période, récapitulatif des crédits.
     */
    public function test_booklet_pdf(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $modules = [];
        foreach ([['Anatomie', 1, 'S1', true], ['Physiologie', 1, 'S2', true], ['Brouillon', 1, 'S2', false]] as $k => $m) {
            $course = $gen->create_course(['fullname' => 'Cours ' . $m[0]]);
            $record = $gen->create_module('syllabus', ['course' => $course->id, 'name' => $m[0], 'code' => 'UE' . $k,
                'studyyear' => $m[1], 'semester' => $m[2], 'ects' => 3]);
            $syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
            if ($m[3]) {
                $version = syllabus_start_working($syllabus, (int) get_admin()->id);
                syllabus_save_working($version, (object) ['objectives' => 'Objectif ' . $m[0]], null, (int) get_admin()->id);
                syllabus_publish($syllabus, syllabus_get_working((int) $syllabus->id), (int) get_admin()->id, '');
            }
            $syllabus->coursename = $course->fullname;
            $syllabus->courseshortname = $course->shortname;
            $syllabus->published = syllabus_get_published((int) $syllabus->id);
            $syllabus->teachers = [];
            $modules[] = $syllabus;
        }
        $pdf = new pdf\booklet_pdf('Livret', ['Tout le cursus'], syllabus_sort_by_period($modules));
        // Titre, sommaire, récapitulatif, deux fiches (le brouillon n'est que dans le récapitulatif).
        $this->assertSame(5, $pdf->getNumPages());
        $titles = array_map(fn($o) => $o['t'], $pdf->get_outlines());
        $year = syllabus_studyyear_label(1);
        $this->assertSame([get_string('bookletcontents', 'mod_syllabus'), get_string('bookletsummary', 'mod_syllabus'),
            $year . ' · S1', 'UE0 - Anatomie', $year . ' · S2', 'UE1 - Physiologie'], $titles);
        $this->assertStringStartsWith('%PDF', $pdf->Output('', 'S'));
    }
}
