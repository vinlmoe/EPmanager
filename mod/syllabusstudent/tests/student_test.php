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

namespace mod_syllabusstudent;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/syllabusstudent/locallib.php');

/**
 * Mes modules : cours où l'étudiant est inscrit, modules visibles, évaluations à faire.
 *
 * @package   mod_syllabusstudent
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabusstudent_get_modules
 */
final class student_test extends \advanced_testcase {
    /**
     * Seuls les modules visibles des cours de l'étudiant ; état d'évaluation de chacun.
     */
    public function test_modules_of_student(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $c1 = $gen->create_course();
        $c2 = $gen->create_course();
        $c3 = $gen->create_course();
        $gen->enrol_user($student->id, $c1->id, 'student');
        $gen->enrol_user($student->id, $c2->id, 'student');
        $this->setAdminUser();
        $open = $gen->create_module('syllabus', ['course' => $c1->id, 'evalopen' => time() - 10]);
        $upcoming = $gen->create_module('syllabus', ['course' => $c1->id, 'evalopen' => time() + DAYSECS]);
        $gen->create_module('syllabus', ['course' => $c2->id, 'visible' => 0]);
        $gen->create_module('syllabus', ['course' => $c3->id]);

        $modules = syllabusstudent_get_modules((int) $student->id, 0);
        $this->assertSame([(int) $open->id, (int) $upcoming->id], array_map('intval', array_keys($modules)));
        $this->assertNull($modules[$open->id]->evalproblem);
        $this->assertSame('evalupcoming', $modules[$upcoming->id]->evalproblem);
    }
}
