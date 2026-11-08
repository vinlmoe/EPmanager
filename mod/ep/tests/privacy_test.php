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

namespace mod_ep;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_ep\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Données personnelles (fournisseur de confidentialité) et réinitialisation du cours.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_ep\privacy\provider
 * @covers     ::ep_reset_userdata
 * @covers     ::ep_reset_course_form_defaults
 */
final class privacy_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass */
    private $course;

    /** @var \stdClass */
    private $ep;

    /** @var \context_module */
    private $context;

    /** @var \stdClass Étudiante. */
    private $student;

    /** @var \stdClass Autre étudiant. */
    private $other;

    /** @var \stdClass Enseignant : responsable d'un EP, auteur de son syllabus, intervenant. */
    private $teacher;

    /** @var \stdClass Membre de la DEVE : décisions, émission, import. */
    private $deve;

    /**
     * Une activité avec, pour l'étudiante : un EP du catalogue validé (justificatif), un élément
     * du supplément (preuve), une décision d'année, un supplément émis ; un crédit de l'autre
     * étudiant décidé par l'enseignant ; un syllabus rédigé par l'enseignant, revu par la DEVE.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $ep = $gen->create_module('ep', ['course' => $this->course->id, 'currentstudyyear' => 3]);
        $this->context = \context_module::instance($ep->cmid);
        $this->ep = $DB->get_record('ep', ['id' => $ep->id]);
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->other = $gen->create_and_enrol($this->course, 'student');
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->deve = $gen->create_and_enrol($this->course, 'manager');
        $epgen = $gen->get_plugin_generator('mod_ep');
        $fs = get_file_storage();

        $activity = $epgen->create_activity($this->ep, ['code' => 'ANA1', 'name' => 'Anatomie']);
        ep_set_activity_teachers($activity->id, [$this->teacher->id]);
        $DB->set_field('ep_activity', 'proposedby', $this->teacher->id, ['id' => $activity->id]);
        // Fiche d'avant la version 1.1, pas encore reprise dans mod_syllabus (tables ep_syllabus et
        // ep_session) : le fournisseur de mod_ep la couvre jusqu'à sa reprise ; ensuite, c'est
        // celui de mod_syllabus.
        $now = time();
        $syllabusid = $DB->insert_record('ep_syllabus', (object) ['activityid' => $activity->id, 'version' => 1,
            'status' => 'published', 'objectives' => 'Objectifs', 'language' => '', 'personalhours' => 0,
            'authorid' => $this->teacher->id, 'reviewerid' => $this->deve->id, 'reviewcomment' => 'Parfait',
            'timesubmitted' => $now, 'timereviewed' => $now, 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('ep_session', (object) ['syllabusid' => $syllabusid, 'sortorder' => 10, 'title' => 'Cours',
            'kind' => 'lecture', 'duration' => 2, 'teacherid' => $this->teacher->id, 'teachername' => '',
            'timecreated' => $now]);

        $creditid = ep_register_to_activity($this->ep, $activity, $this->student->id, 3)->creditid;
        $credit = $DB->get_record('ep_credit', ['id' => $creditid]);
        ep_validate_credit($credit, $this->deve->id, 2, 'Bien', EP_ROLE_DEVE);
        $fs->create_file_from_string(
            ['contextid' => $this->context->id, 'component' => 'mod_ep',
            'filearea' => EP_EVIDENCE_FILEAREA, 'itemid' => $credit->id, 'filepath' => '/', 'filename' => 'attestation.pdf'],
            '%PDF-1.4'
        );
        $othercredit = $DB->get_record('ep_credit', ['id' => ep_register_to_activity($this->ep, $activity, $this->other->id, 3)
            ->creditid]);
        ep_validate_credit($othercredit, $this->teacher->id, 2, 'Assidu', EP_ROLE_OWNER);

        $categories = ep_get_supplement_categories($this->ep->id);
        $itemid = ep_create_supplement_item($this->ep, $this->student->id, reset($categories), (object) ['title' => 'Tutorat']);
        $fs->create_file_from_string(
            ['contextid' => $this->context->id, 'component' => 'mod_ep',
            'filearea' => EP_SUPPLEMENT_EVIDENCE_FILEAREA, 'itemid' => $itemid, 'filepath' => '/', 'filename' => 'preuve.pdf'],
            '%PDF-1.4'
        );
        ep_decide_supplement_item(
            $this->ep,
            $DB->get_record('ep_supplement_item', ['id' => $itemid]),
            $this->deve->id,
            true,
            '',
            EP_ROLE_DEVE
        );
        ep_set_year_validation($this->ep, $this->student->id, 3, true, 'Validée', $this->deve->id);
        ep_issue_supplement($this->ep, $this->context, $this->student->id, $this->deve->id, 'fr', true);
        ep_log_import($this->ep, 'credits', $this->deve->id, 3, (object) ['created' => 3]);
    }

    /**
     * Métadonnées : chaque table décrite a ses chaînes.
     */
    public function test_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_ep'));
        $strings = get_string_manager();
        foreach ($collection->get_collection() as $item) {
            $this->assertTrue($strings->string_exists($item->get_summary(), 'mod_ep'), $item->get_summary());
            foreach ($item->get_privacy_fields() as $field => $identifier) {
                $this->assertTrue($strings->string_exists($identifier, 'mod_ep'), $identifier);
            }
        }
    }

    /**
     * Contextes et utilisateurs : l'étudiante, l'enseignant et la DEVE y figurent ; un inconnu non.
     */
    public function test_contexts_and_users(): void {
        foreach ([$this->student, $this->other, $this->teacher, $this->deve] as $user) {
            $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($user->id)->get_contextids());
        }
        $stranger = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid($stranger->id)->get_contextids());

        $userlist = new userlist($this->context, 'mod_ep');
        provider::get_users_in_context($userlist);
        $expected = [$this->student->id, $this->other->id, $this->teacher->id, $this->deve->id];
        sort($expected);
        $actual = $userlist->get_userids();
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Export : dossier complet de l'étudiante avec ses fichiers ; actions de l'enseignant, sans
     * nommer l'étudiant concerné.
     */
    public function test_export(): void {
        global $DB;
        $this->export_context_data_for_user($this->student->id, $this->context, 'mod_ep');
        $writer = writer::with_context($this->context);
        $credits = $writer->get_data([get_string('privacy:path:credits', 'mod_ep')]);
        $this->assertCount(1, $credits->credits);
        $this->assertSame('Anatomie', $credits->credits[0]->name);
        $this->assertNotEmpty($credits->credits[0]->decisions);
        $this->assertCount(1, $writer->get_data([get_string('privacy:path:supplementitems', 'mod_ep')])->items);
        $this->assertCount(1, $writer->get_data([get_string('privacy:path:years', 'mod_ep')])->years);
        $this->assertCount(1, $writer->get_data([get_string('privacy:path:supplements', 'mod_ep')])->supplements);
        $creditid = $DB->get_field('ep_credit', 'id', ['userid' => $this->student->id]);
        $this->assertArrayHasKey('attestation.pdf', $writer->get_files([get_string('privacy:path:credits', 'mod_ep'), $creditid]));

        writer::reset();
        $this->export_context_data_for_user($this->teacher->id, $this->context, 'mod_ep');
        $staff = writer::with_context($this->context)->get_data([get_string('privacy:path:staff', 'mod_ep')]);
        $this->assertSame('Assidu', $staff->creditdecisions[0]->comment);
        $this->assertSame('ANA1', $staff->activitiesincharge[0]->code);
        $this->assertNotEmpty($staff->syllabi);
        $this->assertNotEmpty($staff->sessions);
        $this->assertStringNotContainsString(fullname($this->other), json_encode($staff, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Effacement de l'étudiante : son dossier et ses fichiers disparaissent, pas celui de l'autre.
     * Effacement de l'enseignant : ses références sont vidées, les dossiers restent.
     */
    public function test_delete_for_user(): void {
        global $DB;
        $fs = get_file_storage();
        provider::delete_data_for_user(new approved_contextlist($this->student, 'mod_ep', [$this->context->id]));
        foreach (['ep_credit', 'ep_supplement_item', 'ep_year_validation', 'ep_supplement_issue'] as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $this->student->id]), $table);
        }
        $this->assertTrue($DB->record_exists('ep_credit', ['userid' => $this->other->id]));
        foreach ([EP_EVIDENCE_FILEAREA, EP_SUPPLEMENT_EVIDENCE_FILEAREA, EP_SUPPLEMENT_ISSUED_FILEAREA] as $area) {
            $this->assertEmpty($fs->get_area_files($this->context->id, 'mod_ep', $area, false, 'id', false), $area);
        }

        provider::delete_data_for_user(new approved_contextlist($this->teacher, 'mod_ep', [$this->context->id]));
        $this->assertSame(1, $DB->count_records('ep_credit', ['epid' => $this->ep->id]));
        $this->assertFalse($DB->record_exists('ep_credit_decision', ['userid' => $this->teacher->id]));
        $this->assertFalse($DB->record_exists('ep_activity_teacher', ['teacherid' => $this->teacher->id]));
        $this->assertFalse($DB->record_exists('ep_syllabus', ['authorid' => $this->teacher->id]));
        $this->assertFalse($DB->record_exists('ep_session', ['teacherid' => $this->teacher->id]));
        $this->assertFalse($DB->record_exists('ep_activity', ['proposedby' => $this->teacher->id]));
        $this->assertTrue($DB->record_exists('ep_syllabus', ['reviewerid' => $this->deve->id]));
        $this->assertEmpty(provider::get_contexts_for_userid($this->teacher->id)->get_contextids());

        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_ep', [$this->deve->id]));
        $this->assertFalse($DB->record_exists('ep_import_log', ['userid' => $this->deve->id]));
        $this->assertEmpty(provider::get_contexts_for_userid($this->deve->id)->get_contextids());
        $this->assertTrue($DB->record_exists('ep_credit', ['userid' => $this->other->id]));
    }

    /**
     * Effacement de tout le contexte : plus aucune donnée personnelle, le catalogue et son
     * syllabus restent.
     */
    public function test_delete_all_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->context);
        foreach ([$this->student, $this->other, $this->teacher, $this->deve] as $user) {
            $this->assertEmpty(provider::get_contexts_for_userid($user->id)->get_contextids());
        }
        $this->assertSame(1, $DB->count_records('ep_activity', ['epid' => $this->ep->id]));
        $this->assertTrue($DB->record_exists('ep_syllabus', ['activityid' => $DB->get_field(
            'ep_activity',
            'id',
            ['epid' => $this->ep->id]
        )]));
    }

    /**
     * Réinitialisation du cours avec les choix par défaut : dossiers et décisions d'année
     * effacés, suppléments émis et journal conservés ; puis effacement des suppléments.
     */
    public function test_course_reset(): void {
        global $DB;
        $data = (object) (['courseid' => $this->course->id] + ep_reset_course_form_defaults($this->course));
        $status = ep_reset_userdata($data);
        $this->assertCount(2, $status);
        $this->assertSame(0, $DB->count_records('ep_credit', ['epid' => $this->ep->id]));
        $this->assertSame(0, $DB->count_records('ep_year_validation', ['epid' => $this->ep->id]));
        $this->assertSame(1, $DB->count_records('ep_supplement_issue', ['epid' => $this->ep->id]));
        $this->assertSame(1, $DB->count_records('ep_import_log', ['epid' => $this->ep->id]));
        $this->assertSame(1, $DB->count_records('ep_activity', ['epid' => $this->ep->id]));

        ep_reset_userdata((object) ['courseid' => $this->course->id, 'reset_ep_supplement' => 1, 'reset_ep_importlog' => 1]);
        $this->assertSame(0, $DB->count_records('ep_supplement_issue', ['epid' => $this->ep->id]));
        $this->assertSame(0, $DB->count_records('ep_supplement_item', ['epid' => $this->ep->id]));
        $this->assertSame(0, $DB->count_records('ep_import_log', ['epid' => $this->ep->id]));
        $this->assertEmpty(get_file_storage()->get_area_files(
            $this->context->id,
            'mod_ep',
            EP_SUPPLEMENT_ISSUED_FILEAREA,
            false,
            'id',
            false
        ));
    }
}
