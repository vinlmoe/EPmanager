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

namespace mod_ep;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Import Excel/CSV par la DEVE : le catalogue des EP d'une promotion, et les EP portés au crédit
 * des étudiants (inscriptions au catalogue et déclarations hors catalogue confondues).
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_parse_import_csv
 * @covers     ::ep_import_activities
 * @covers     ::ep_import_credits
 */
final class import_test extends \advanced_testcase {

    /** @var \stdClass Instance mod_ep. */
    protected $ep;

    /** @var \context_module */
    protected $context;

    /** @var \stdClass */
    protected $student;

    /** @var \stdClass */
    protected $deve;

    /**
     * Une promotion, un étudiant inscrit et un utilisateur DEVE.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->ep = $this->getDataGenerator()->create_module('ep', [
            'course' => $course->id,
            'currentstudyyear' => 3,
        ]);
        $this->context = \context_module::instance($this->ep->cmid);

        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->deve = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
    }

    /**
     * Le lecteur CSV accepte aussi bien les points-virgules (export Excel francophone) que les
     * virgules, et ignore les lignes vides.
     */
    public function test_parse_import_csv_accepts_both_delimiters(): void {
        $columns = ['a', 'b'];

        $semicolon = ep_parse_import_csv("a;b\nun;deux\n\ntrois;quatre\n", $columns);
        $this->assertNull($semicolon->error);
        $this->assertSame(['a' => 'un', 'b' => 'deux'], $semicolon->rows[2]);
        $this->assertSame(['a' => 'trois', 'b' => 'quatre'], $semicolon->rows[4]);

        $comma = ep_parse_import_csv("a,b\nun,deux\n", $columns);
        $this->assertNull($comma->error);
        $this->assertSame(['a' => 'un', 'b' => 'deux'], $comma->rows[2]);
    }

    /**
     * Import du catalogue : les lignes valides sont créées, une ligne fautive (ECTS nul, type
     * inconnu) est signalée sans empêcher l'import des autres, et un intitulé déjà présent au
     * catalogue est refusé plutôt que dupliqué.
     */
    public function test_import_activities_creates_valid_rows_and_reports_others(): void {
        global $DB;

        $this->getDataGenerator()->get_plugin_generator('mod_ep')
            ->create_activity($this->ep, ['name' => 'Déjà au catalogue']);

        $csv = "name;type;ects;minstudyyear;maxstudyyear;capacity;sortorder;visible\n"
            . "Clinique équine;academic;4;3;5;10;1;1\n"
            . "Engagement associatif;engagement;2;;;;;\n"
            . "Sans ECTS;academic;0;;;;;\n"
            . "Type inconnu;xyz;2;;;;;\n"
            . "Déjà au catalogue;academic;3;;;;;\n";

        $parsed = ep_parse_import_csv($csv, ep_import_activity_columns());
        $this->assertNull($parsed->error);

        $result = ep_import_activities($this->ep, $parsed->rows);

        $this->assertSame(2, $result->created);
        $this->assertCount(3, $result->errors);

        $created = $DB->get_record('ep_activity', ['epid' => $this->ep->id, 'name' => 'Clinique équine'],
            '*', MUST_EXIST);
        $this->assertEquals(4, $created->ects);
        $this->assertEquals(3, $created->minstudyyear);
        $this->assertEquals(5, $created->maxstudyyear);
        $this->assertEquals(10, $created->capacity);
        $this->assertEquals(ep_get_type_by_code($this->ep->id, EP_TYPE_ACADEMIC)->id, $created->typeid);

        $engagement = $DB->get_record('ep_activity', ['epid' => $this->ep->id, 'name' => 'Engagement associatif'],
            '*', MUST_EXIST);
        $this->assertEquals(ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT)->id, $engagement->typeid);

        // Une seule ligne « Déjà au catalogue » au total : celle importée en amont, pas de doublon.
        $this->assertSame(1,
            $DB->count_records('ep_activity', ['epid' => $this->ep->id, 'name' => 'Déjà au catalogue']));
    }

    /**
     * Import de crédits : une inscription à un EP du catalogue et une déclaration hors catalogue
     * sur la même ligne d'en-tête, chacune avec son statut. Le crédit est enregistré comme saisi
     * par la DEVE, avec le compte à l'origine de l'import comme validateur.
     */
    public function test_import_credits_handles_registrations_and_declarations(): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_ep');
        $activity = $generator->create_activity($this->ep, ['name' => 'Clinique équine', 'ects' => 4]);
        $generator->configure_type($this->ep, EP_TYPE_SPORT,
            ['ectsmode' => EP_ECTS_MODE_FLAT, 'ectsvalue' => 1]);

        $csv = "email;ep;name;studyyear;claimedects;weeks;retainedects;status;comment\n"
            . "{$this->student->email};Clinique équine;;;;;;valide;Vu le dossier papier\n"
            . "{$this->student->email};sport;Championnat régional;3;;;;valide;\n";

        $parsed = ep_parse_import_csv($csv, ep_import_credit_columns());
        $this->assertNull($parsed->error);

        $result = ep_import_credits($this->ep, $this->context, $parsed->rows, $this->deve->id);

        $this->assertSame(2, $result->created);
        $this->assertEmpty($result->errors);

        $registration = $DB->get_record('ep_credit',
            ['epid' => $this->ep->id, 'activityid' => $activity->id, 'userid' => $this->student->id],
            '*', MUST_EXIST);
        $this->assertEquals(EP_STATUS_VALIDATED, $registration->status);
        $this->assertEquals(4, $registration->retainedects);
        $this->assertEquals(EP_SOURCE_DEVE, $registration->source);
        $this->assertEquals($this->deve->id, $registration->validatedby);
        $this->assertEquals('Vu le dossier papier', $registration->validatorcomment);

        $declaration = $DB->get_record('ep_credit',
            ['epid' => $this->ep->id, 'userid' => $this->student->id, 'name' => 'Championnat régional'],
            '*', MUST_EXIST);
        $this->assertEquals(1, $declaration->claimedects);
        $this->assertEquals(1, $declaration->retainedects);
        $this->assertEquals(EP_STATUS_VALIDATED, $declaration->status);
        $this->assertEquals(EP_SOURCE_DEVE, $declaration->source);

        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $this->assertEquals(5, $progress->totalretained);
    }

    /**
     * Un étudiant déjà inscrit sur le même EP n'est pas réinscrit : l'import ne doit pas écraser
     * un dossier déjà ouvert, comme une inscription en ligne ne le pourrait pas non plus.
     */
    public function test_import_credits_rejects_existing_registration(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_ep');
        $activity = $generator->create_activity($this->ep, ['name' => 'Clinique équine', 'ects' => 4]);
        ep_register_to_activity($this->ep, $activity, $this->student->id, 3);

        $csv = "email;ep;name;studyyear;claimedects;weeks;retainedects;status;comment\n"
            . "{$this->student->email};Clinique équine;;;;;;attente;\n";

        $parsed = ep_parse_import_csv($csv, ep_import_credit_columns());
        $result = ep_import_credits($this->ep, $this->context, $parsed->rows, $this->deve->id);

        $this->assertSame(0, $result->created);
        $this->assertCount(1, $result->errors);
    }

    /**
     * Le statut « accepté » n'a de sens que pour une inscription au catalogue : sur une
     * déclaration hors catalogue, il est refusé plutôt que silencieusement ramené à autre chose.
     */
    public function test_enrolled_status_is_rejected_for_a_declaration(): void {
        $csv = "email;ep;name;studyyear;claimedects;weeks;retainedects;status;comment\n"
            . "{$this->student->email};engagement;Tutorat;3;2;;;accepte;\n";

        $parsed = ep_parse_import_csv($csv, ep_import_credit_columns());
        $result = ep_import_credits($this->ep, $this->context, $parsed->rows, $this->deve->id);

        $this->assertSame(0, $result->created);
        $this->assertCount(1, $result->errors);
    }
}
