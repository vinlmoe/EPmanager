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
 * Avis des étudiants annuels : un recueil par année universitaire, les années passées archivées
 * (ouverture dans une nouvelle année, réinitialisation du cours), leurs résultats et leur
 * évolution, la synthèse de la DEVE par année, la sauvegarde.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::syllabus_academic_year
 * @covers    ::syllabus_prepare_window
 * @covers    ::syllabus_archive_evaluation
 * @covers    ::syllabus_archive_results
 * @covers    ::syllabus_results_evolution
 * @covers    ::syllabus_archived_means
 */
final class annual_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $syllabus;
    /** @var \context_module */
    protected $context;
    /** @var array */
    protected $students = [];
    /** @var int */
    protected $common;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        syllabus_save_settings((object) ['reviewrequired' => 1, 'resultsthreshold' => 2, 'teacherresults' => 'afterclose']);
        $this->common = syllabus_save_question((object) ['kind' => 'scale', 'text' => 'Organisation', 'required' => 1]);
        $this->course = $gen->create_course();
        for ($i = 0; $i < 4; $i++) {
            $this->students[] = $gen->create_and_enrol($this->course, 'student');
        }
        $record = $gen->create_module('syllabus', ['course' => $this->course->id]);
        $this->syllabus = $DB->get_record('syllabus', ['id' => $record->id]);
        $this->context = \context_module::instance($record->cmid);
        syllabus_save_question((object) ['syllabusid' => $this->syllabus->id, 'kind' => 'scale', 'text' => 'Supports']);
    }

    /**
     * Ouvre le recueil à une date donnée et fait répondre des étudiants (questions dans l'ordre :
     * commune, puis de l'enseignant).
     *
     * @param int $open
     * @param array $answers Une paire [commune, enseignant] par étudiant.
     */
    protected function collect(int $open, array $answers): void {
        global $DB;
        syllabus_set_window($this->syllabus, $open, time() + DAYSECS);
        $this->syllabus = $DB->get_record('syllabus', ['id' => $this->syllabus->id]);
        syllabus_freeze_questions($this->syllabus);
        $questions = array_values(syllabus_get_module_questions((int) $this->syllabus->id));
        foreach ($answers as $index => [$common, $teacher]) {
            $this->assertSame([], syllabus_submit_response(
                $this->syllabus,
                $this->context,
                (int) $this->students[$index]->id,
                [$questions[0]->id => $common, $questions[1]->id => $teacher]
            ));
        }
    }

    /**
     * Année universitaire selon le premier mois réglé (septembre par défaut).
     */
    public function test_academic_year(): void {
        $this->assertSame('2026-2027', syllabus_academic_year(make_timestamp(2026, 9, 1, 12)));
        $this->assertSame('2026-2027', syllabus_academic_year(make_timestamp(2027, 8, 31, 12)));
        $this->assertSame('2025-2026', syllabus_academic_year(make_timestamp(2026, 8, 31, 12)));
        syllabus_save_settings((object) ['yearstartmonth' => 1]);
        $this->assertSame('2027', syllabus_academic_year(make_timestamp(2027, 3, 1, 12)));
    }

    /**
     * Même année : la fenêtre change, les réponses restent. Nouvelle année : le recueil est
     * archivé, le module repart de zéro (questions défigées, celles de l'enseignant gardées) et les
     * étudiants peuvent répondre de nouveau.
     */
    public function test_new_year_archives(): void {
        global $DB;
        $last = time() - 400 * DAYSECS;
        $this->collect($last, [[4, 3], [2, 5]]);
        $lastyear = syllabus_academic_year($last);
        $this->assertSame($lastyear, $this->syllabus->evalyear);

        syllabus_set_window($this->syllabus, $last + HOURSECS, $last + 2 * DAYSECS);
        $this->assertSame(2, syllabus_count_responses((int) $this->syllabus->id));
        $this->assertSame([], syllabus_get_archives((int) $this->syllabus->id));

        syllabus_set_window($this->syllabus, time() - 60, time() + DAYSECS);
        $archives = syllabus_get_archives((int) $this->syllabus->id);
        $this->assertCount(1, $archives);
        $archive = reset($archives);
        $this->assertSame($lastyear, $archive->evalyear);
        $this->assertEquals(2, $archive->responses);
        $this->assertEquals(4, $archive->eligible);
        $this->assertSame(0, syllabus_count_responses((int) $this->syllabus->id));
        $this->assertSame(0, $DB->count_records('syllabus_participant', ['syllabusid' => $this->syllabus->id]));
        $this->assertSame(syllabus_academic_year(time()), $DB->get_field('syllabus', 'evalyear', ['id' => $this->syllabus->id]));
        $this->assertEquals(0, $DB->get_field('syllabus', 'evalfrozen', ['id' => $this->syllabus->id]));
        $this->assertSame(['teacher'], array_values(array_unique(array_map(
            fn($q) => $q->origin,
            syllabus_get_module_questions((int) $this->syllabus->id)
        ))));
        // Les mêmes étudiants répondent de nouveau cette année.
        $this->syllabus = $DB->get_record('syllabus', ['id' => $this->syllabus->id]);
        $this->collect(time() - 60, [[5, 5]]);
        $this->assertSame(1, syllabus_count_responses((int) $this->syllabus->id));

        // Résultats archivés : ceux de la page des résultats, et l'évolution d'une année à l'autre.
        $past = syllabus_archive_results($archive);
        $this->assertTrue($past->shown);
        $this->assertEquals(3, $past->questions[0]->mean);
        $this->assertSame((int) $this->common, $past->questions[0]->question->sourceid);
        $evolution = syllabus_results_evolution([$lastyear => $past, 'cette année' => syllabus_get_results($this->syllabus)]);
        $this->assertCount(2, $evolution);
        // Cette année, une seule réponse : sous le seuil, pas de moyenne.
        $this->assertSame('Organisation', $evolution[0]->text);
        $this->assertEquals(3, $evolution[0]->means[$lastyear]);
        $this->assertNull($evolution[0]->means['cette année']);
        $this->assertSame('Supports', $evolution[1]->text);
        $this->assertEquals(4, $evolution[1]->means[$lastyear]);

        // Synthèse de la DEVE pour l'année passée.
        $means = syllabus_archived_means([(int) $this->syllabus->id], $lastyear);
        $this->assertEquals(3, $means[$this->syllabus->id]['means'][$this->common]);
        $this->assertEquals(2, $means[$this->syllabus->id]['responses']);
        $this->assertSame([], syllabus_archived_means([(int) $this->syllabus->id], '1999-2000'));
    }

    /**
     * Sous le seuil d'anonymat, seul le nombre de réponses est archivé.
     */
    public function test_archive_below_threshold(): void {
        $this->collect(time() - 400 * DAYSECS, [[4, 3]]);
        $id = syllabus_archive_evaluation($this->syllabus, $this->context);
        $archive = syllabus_get_archives((int) $this->syllabus->id)[$id];
        $this->assertNull($archive->results);
        $this->assertEquals(1, $archive->responses);
        $this->assertFalse(syllabus_archive_results($archive)->shown);
        // Sans réponse, rien n'est archivé.
        $this->assertNull(syllabus_archive_evaluation($this->syllabus, $this->context));
    }

    /**
     * La réinitialisation du cours archive avant d'effacer ; la sauvegarde garde les archives et
     * l'année du recueil ; la suppression de l'activité efface les archives.
     */
    public function test_reset_backup_delete(): void {
        global $DB, $USER;
        $this->collect(time() - 60, [[4, 3], [2, 5]]);
        syllabus_reset_userdata((object) ['courseid' => $this->course->id, 'reset_syllabus_responses' => 1]);
        $this->assertCount(1, syllabus_get_archives((int) $this->syllabus->id));
        $this->assertSame(0, syllabus_count_responses((int) $this->syllabus->id));

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $this->course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_SAMESITE,
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
            \backup::MODE_SAMESITE,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        $restored = $DB->get_record('syllabus', ['course' => $newcourse->id], '*', MUST_EXIST);
        $this->assertSame($this->syllabus->evalyear, $restored->evalyear);
        $archives = syllabus_get_archives((int) $restored->id);
        $this->assertCount(1, $archives);
        $this->assertEquals(3, syllabus_archive_results(reset($archives))->questions[0]->mean);

        course_delete_module(get_coursemodule_from_instance('syllabus', $this->syllabus->id)->id);
        $this->assertSame(0, $DB->count_records('syllabus_evalarchive', ['syllabusid' => $this->syllabus->id]));
    }
}
