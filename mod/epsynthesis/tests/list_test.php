<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_epsynthesis;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Liste combinée des crédits d'un enseignant sur plusieurs promotions : une requête, triée et
 * paginée par la base.
 *
 * @package    mod_epsynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::epsynthesis_get_filtered_credits
 * @covers     ::epsynthesis_filtered_credits_sql
 * @covers     ::epsynthesis_get_active_links
 */
final class list_test extends \advanced_testcase {
    /**
     * Deux promotions où l'enseignant est référent d'un étudiant chacune, plus un étudiant hors de
     * son périmètre : seuls ses étudiants apparaissent, triés globalement, paginés, filtrés.
     */
    public function test_combined_list(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $epgen = $gen->get_plugin_generator('mod_ep');
        $teacher = $gen->create_user();
        $cmids = [];
        $mine = [];
        foreach (['A', 'B'] as $k => $name) {
            $course = $gen->create_course(['fullname' => "Promotion $name"]);
            $stage = $gen->create_module('stage', ['course' => $course->id]);
            $ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 3]);
            $cmids[] = (int) $ep->cmid;
            $gen->enrol_user($teacher->id, $course->id, 'teacher');
            $student = $gen->create_and_enrol($course, 'student', ['lastname' => "Étudiant $name"]);
            $other = $gen->create_and_enrol($course, 'student', ['lastname' => "Autre $name"]);
            $gen->get_plugin_generator('mod_stage')->assign_teacher($stage, $student->id, $teacher->id);
            $record = $DB->get_record('ep', ['id' => $ep->id]);
            for ($i = 1; $i <= 3; $i++) {
                $mine[] = $epgen->create_validated_credit($record, $student->id, EP_TYPE_SPORT, $i, 3)->id;
            }
            $epgen->create_validated_credit($record, $other->id, EP_TYPE_SPORT, 1, 3);
        }
        $team = $gen->create_course();
        $synthesis = $gen->create_module('epsynthesis', ['course' => $team->id]);
        epsynthesis_set_links($synthesis->id, $cmids);
        $links = epsynthesis_get_active_links($synthesis->id, $teacher->id);
        $this->assertCount(2, $links);

        [$rows, $total] = epsynthesis_get_filtered_credits($links, [], 'ects', 'DESC', 0, 4);
        $this->assertSame(6, $total);
        $this->assertCount(4, $rows);
        $this->assertEquals([3, 3, 2, 2], array_map(fn($r) => (float) $r->claimedects, $rows));
        [$next] = epsynthesis_get_filtered_credits($links, [], 'ects', 'DESC', 4, 4);
        $this->assertCount(2, $next);
        $ids = array_merge(array_map(fn($r) => (int) $r->id, $rows), array_map(fn($r) => (int) $r->id, $next));
        sort($ids);
        sort($mine);
        $this->assertSame(array_map('intval', $mine), $ids);

        [$rows, $total] = epsynthesis_get_filtered_credits($links, ['search' => 'Étudiant B'], 'course', 'ASC', 0, 10);
        $this->assertSame(3, $total);
        $this->assertSame(['Promotion B'], array_values(array_unique(array_map(fn($r) => $r->coursename, $rows))));
        $this->assertNotSame('-', epsynthesis_credit_student_name($rows[0]));

        $typeid = (int) $DB->get_field('ep_type', 'id', ['epid' => $links[$cmids[0]]->ep->id, 'code' => EP_TYPE_SPORT]);
        [, $total] = epsynthesis_get_filtered_credits($links, ['typefilter' => [$cmids[0], $typeid]], 'timecreated', 'DESC');
        $this->assertSame(3, $total);
    }
}
