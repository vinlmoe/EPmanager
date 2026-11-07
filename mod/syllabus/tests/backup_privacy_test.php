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
 * Sauvegarde et RGPD.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \backup_syllabus_activity_structure_step
 * @covers    \mod_syllabus\privacy\provider
 */
final class backup_privacy_test extends \core_privacy\tests\provider_testcase {
    /**
     * Module avec fiche publiée, séance, question figée, une réponse.
     *
     * @return array [course, syllabus, context, teacher, student]
     */
    protected function fixture(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $record = $gen->create_module('syllabus', ['course' => $course->id, 'evalopen' => time() - 10]);
        $syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
        $context = \context_module::instance($record->cmid);
        $version = syllabus_start_working($syllabus, (int) $teacher->id);
        syllabus_save_working($version, (object) ['objectives' => 'Savoir'], [['title' => 'Séance 1', 'duration' => 2,
            'teacherid' => $teacher->id]], (int) $teacher->id);
        syllabus_publish($syllabus, syllabus_get_working((int) $syllabus->id), get_admin()->id, '');
        $gen->get_plugin_generator('mod_syllabus')->create_question(['syllabusid' => $syllabus->id, 'kind' => 'text',
            'text' => 'Avis']);
        syllabus_freeze_questions($syllabus);
        $question = $DB->get_record('syllabus_question', ['syllabusid' => $syllabus->id]);
        $this->setUser($student);
        syllabus_submit_response($syllabus, $context, (int) $student->id, [$question->id => 'Très bien']);
        return [$course, $syllabus, $context, $teacher, $student];
    }

    /**
     * Copie du cours avec les données des utilisateurs : fiche, séances, questions, réponses.
     */
    public function test_backup_restore(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        [$course] = $this->fixture();
        $this->setAdminUser();
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);
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
        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $restored = $DB->get_record('syllabus', ['course' => $newcourse->id], '*', MUST_EXIST);
        $published = syllabus_get_published((int) $restored->id);
        $this->assertSame('Savoir', $published->objectives);
        $this->assertCount(1, syllabus_get_sessions((int) $published->id));
        $this->assertCount(1, syllabus_get_module_questions((int) $restored->id));
        $this->assertSame(1, (int) $restored->evalfrozen);
    }

    /**
     * Export et effacement : la participation et les responsabilités sont personnelles, la
     * réponse anonyme reste.
     */
    public function test_privacy(): void {
        global $DB;
        $this->resetAfterTest();
        [, $syllabus, $context, $teacher, $student] = $this->fixture();

        $this->assertContains((int) $context->id, array_map('intval', \mod_syllabus\privacy\provider::get_contexts_for_userid(
            (int) $student->id
        )->get_contextids()));
        $contextlist = new \core_privacy\local\request\approved_contextlist($student, 'mod_syllabus', [$context->id]);
        \mod_syllabus\privacy\provider::export_user_data($contextlist);
        $data = \core_privacy\local\request\writer::with_context($context)->get_data([get_string('pluginname', 'mod_syllabus')]);
        $this->assertSame(get_string('yes'), $data->evaluated);

        \mod_syllabus\privacy\provider::delete_data_for_user($contextlist);
        $this->assertFalse(syllabus_has_responded((int) $syllabus->id, (int) $student->id));
        $this->assertSame(1, $DB->count_records('syllabus_response', ['syllabusid' => $syllabus->id]));

        $contextlist = new \core_privacy\local\request\approved_contextlist($teacher, 'mod_syllabus', [$context->id]);
        \mod_syllabus\privacy\provider::delete_data_for_user($contextlist);
        $this->assertSame([], syllabus_get_teachers((int) $syllabus->id));
        $this->assertSame(0, $DB->count_records('syllabus_version', ['authorid' => $teacher->id]));
        $this->assertSame(0, $DB->count_records('syllabus_session', ['teacherid' => $teacher->id]));

        \mod_syllabus\privacy\provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('syllabus_response', ['syllabusid' => $syllabus->id]));
    }
}
