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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Supplément au diplôme, validation d'année, rentrée et transfert d'un étudiant.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_set_year_validation
 * @covers     ::ep_get_year_validations
 * @covers     ::ep_validate_years_in_bulk
 * @covers     ::ep_rollover
 * @covers     ::ep_create_default_supplement_categories
 * @covers     ::ep_save_supplement_category
 * @covers     ::ep_create_supplement_item
 * @covers     ::ep_update_supplement_item
 * @covers     ::ep_decide_supplement_item
 * @covers     ::ep_cancel_supplement_item
 * @covers     ::ep_rights_can_decide_supplement_item
 * @covers     ::ep_build_supplement
 * @covers     ::ep_get_student_birthdate
 * @covers     ::ep_set_supplement_text
 * @covers     ::ep_issue_supplement
 * @covers     ::ep_verify_supplement
 * @covers     ::ep_transfer_student
 * @covers     \mod_ep\pdf\supplement_pdf
 */
final class supplement_test extends \advanced_testcase {
    /** @var \stdClass Instance. */
    private $ep;

    /** @var \context_module */
    private $context;

    /** @var \stdClass Étudiant. */
    private $student;

    /** @var \stdClass Enseignant référent de l'étudiant. */
    private $referent;

    /** @var \mod_ep_generator */
    private $gen;

    /**
     * Un cours avec une activité « Gestion des stages » (référent), une activité EP en année 3
     * avec un minimum de 4 ECTS en année 3, un étudiant et son référent.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course->id]);
        $this->ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 3]);
        $this->context = \context_module::instance($this->ep->cmid);
        $this->student = $gen->create_and_enrol($course, 'student', [
            'firstname' => 'Zoé', 'lastname' => 'Martin', 'idnumber' => 'VET2026-001',
        ]);
        $this->referent = $gen->create_and_enrol($course, 'teacher');
        $gen->get_plugin_generator('mod_stage')->assign_teacher($stage, $this->student->id, $this->referent->id);
        $this->gen = $gen->get_plugin_generator('mod_ep');
        ep_set_year_requirement($this->ep->id, 3, 4);
        $this->ep = $DB->get_record('ep', ['id' => $this->ep->id]);
    }

    /**
     * Décision d'année : enregistrée avec les ECTS retenus, remplacée par une nouvelle décision ;
     * en lot, seules les années atteintes sont validées, sauf demande contraire.
     */
    public function test_year_validation(): void {
        $other = $this->getDataGenerator()->create_and_enrol(get_course($this->ep->course), 'student');
        $this->gen->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 5, 3);

        $result = ep_validate_years_in_bulk($this->ep, 3, [$this->student->id, $other->id], get_admin()->id);
        $this->assertSame([1, 0, 1], [$result->validated, $result->rejected, $result->skipped]);
        $validations = ep_get_year_validations($this->ep->id);
        $this->assertSame(1, (int) $validations[$this->student->id][3]->decision);
        $this->assertEquals(5, $validations[$this->student->id][3]->retainedects);
        $this->assertArrayNotHasKey($other->id, $validations);

        $result = ep_validate_years_in_bulk($this->ep, 3, [$other->id], get_admin()->id, true);
        $this->assertSame(1, $result->rejected);
        ep_set_year_validation($this->ep, $other->id, 3, true, 'Dérogation du jury', get_admin()->id);
        $validations = ep_get_year_validations($this->ep->id, [$other->id]);
        $this->assertSame(1, (int) $validations[$other->id][3]->decision);
        $this->assertSame('Dérogation du jury', $validations[$other->id][3]->comment);

        $this->assertSame(4, ep_rollover($this->ep));
        $this->assertSame(6, ep_rollover((object) ['id' => $this->ep->id, 'currentstudyyear' => 6]));
    }

    /**
     * Éléments du supplément : circuit « référent puis DEVE », preuves, retrait, refus ; le référent
     * ne décide qu'à son étape.
     */
    public function test_supplement_items_flow(): void {
        global $DB;
        ep_create_default_supplement_categories($this->ep->id);
        $categories = ep_get_supplement_categories($this->ep->id);
        $this->assertCount(6, $categories);
        $category = reset($categories);
        $DB->set_field('ep', 'supplementflow', 'referent,deve', ['id' => $this->ep->id]);
        $ep = $DB->get_record('ep', ['id' => $this->ep->id]);

        $itemid = ep_create_supplement_item($ep, $this->student->id, $category, (object) [
            'title' => 'Présidente du BDE', 'titleen' => 'Student union president', 'periodstart' => 1700000000,
        ]);
        $item = $DB->get_record('ep_supplement_item', ['id' => $itemid]);
        $this->assertSame(EP_ROLE_REFERENT, $item->pendingrole);

        $referent = ep_get_user_rights($ep, $this->context, $this->referent->id);
        $this->assertTrue(ep_rights_can_decide_supplement_item($referent, $item));
        $item->title = 'Présidente du bureau des élèves';
        $this->assertTrue(ep_update_supplement_item($item));

        $sink = $this->redirectMessages();
        $this->assertTrue(ep_decide_supplement_item($ep, $item, $this->referent->id, true, 'Vu en réunion', EP_ROLE_REFERENT));
        $item = $DB->get_record('ep_supplement_item', ['id' => $itemid]);
        $this->assertSame(EP_ROLE_DEVE, $item->pendingrole);
        $this->assertSame('Vu en réunion', $item->validatorcomment);
        $this->assertFalse(ep_rights_can_decide_supplement_item($referent, $item));
        $this->assertFalse(ep_decide_supplement_item($ep, clone $item, $this->referent->id, true, '', EP_ROLE_REFERENT));
        $this->assertCount(0, $sink->get_messages());

        $this->assertTrue(ep_decide_supplement_item($ep, $item, get_admin()->id, true, 'Attestation fournie', EP_ROLE_DEVE));
        $this->assertSame(EP_STATUS_VALIDATED, (int) $DB->get_field('ep_supplement_item', 'status', ['id' => $itemid]));
        $this->assertCount(1, $sink->get_messages());
        $sink->close();

        // Retrait par l'étudiant : seulement en attente.
        $item = $DB->get_record('ep_supplement_item', ['id' => $itemid]);
        $this->assertFalse(ep_update_supplement_item($item));
        $this->assertFalse(ep_cancel_supplement_item($item, $this->student->id, EP_ROLE_STUDENT));
        $second = ep_create_supplement_item($ep, $this->student->id, $category, (object) ['title' => 'Tutorat']);
        $this->assertTrue(ep_cancel_supplement_item(
            $DB->get_record('ep_supplement_item', ['id' => $second]),
            $this->student->id,
            EP_ROLE_STUDENT
        ));

        // Sans circuit, l'élément naît validé.
        $DB->set_field('ep', 'supplementflow', '', ['id' => $this->ep->id]);
        $direct = ep_create_supplement_item(
            $DB->get_record('ep', ['id' => $this->ep->id]),
            $this->student->id,
            $category,
            (object) ['title' => 'Certificat Voltaire']
        );
        $this->assertSame(EP_STATUS_VALIDATED, (int) $DB->get_field('ep_supplement_item', 'status', ['id' => $direct]));
    }

    /**
     * Contenu du supplément : EP validés des types qui y figurent (avec la note s'il y en a une),
     * éléments validés seulement, identité, textes, informations manquantes.
     */
    public function test_build_supplement(): void {
        global $DB;
        $activity = $this->gen->create_activity($this->ep, ['name' => 'Échographie', 'code' => 'EP-ECHO', 'ects' => 2,
            'grading' => EP_GRADING_GRADE]);
        $creditid = ep_register_to_activity($this->ep, $activity, $this->student->id, 3)->creditid;
        ep_grade_credit($DB->get_record('ep_credit', ['id' => $creditid]), get_admin()->id, 14.5, '', EP_ROLE_DEVE);
        $this->gen->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 1, 3);
        $this->gen->configure_type($this->ep, EP_TYPE_ENGAGEMENT, ['insupplement' => 0]);
        $this->gen->create_validated_credit($this->ep, $this->student->id, EP_TYPE_ENGAGEMENT, 1, 3);
        $pending = ep_create_credit(
            $this->ep,
            $this->student->id,
            ep_get_type_by_code($this->ep->id, EP_TYPE_SPORT),
            ['name' => 'En attente', 'claimedects' => 1]
        );

        ep_create_default_supplement_categories($this->ep->id);
        $categories = ep_get_supplement_categories($this->ep->id);
        $category = reset($categories);
        $DB->set_field('ep', 'supplementflow', '', ['id' => $this->ep->id]);
        $ep = $DB->get_record('ep', ['id' => $this->ep->id]);
        ep_create_supplement_item($ep, $this->student->id, $category, (object) ['title' => 'Prix de thèse',
            'titleen' => 'Thesis award']);

        $data = ep_build_supplement($ep, $this->student, 'fr');
        $this->assertSame('Martin', $data->identity->lastname);
        $this->assertSame('VET2026-001', $data->identity->idnumber);
        $this->assertCount(2, $data->credits);
        $this->assertSame('EP-ECHO', $data->credits[0]->code);
        $this->assertSame(14.5, $data->credits[0]->grade);
        $this->assertNull($data->credits[1]->grade);
        $this->assertSame(3.0, $data->totalects);
        $this->assertCount(1, $data->items);
        $this->assertSame('Prix de thèse', $data->items[0]->title);
        $this->assertSame('Thesis award', ep_build_supplement($ep, $this->student, 'en')->items[0]->title);
        $this->assertCount(2, $data->missing);
        $this->assertNotEmpty($pending);

        // La date de naissance d'une convention de stage est reprise ; le signataire comble le reste.
        $stage = $DB->get_record('stage', ['course' => $this->ep->course]);
        $theme = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_theme($stage);
        $entry = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry($stage, $this->student->id, $theme);
        $DB->insert_record('stage_convention_detail', (object) ['entryid' => $entry->id,
            'studentbirthdate' => make_timestamp(2001, 5, 17), 'timecreated' => time(), 'timemodified' => time()]);
        ep_set_supplement_text($ep->id, 'signatoryname', 'fr', 'Pr Durand');
        $this->assertSame(make_timestamp(2001, 5, 17), ep_get_student_birthdate($ep, $this->student->id));
        $this->assertSame([], ep_build_supplement($ep, $this->student, 'fr')->missing);
    }

    /**
     * Émission : refusée tant qu'il manque des informations (sauf forcée), numérotée, empreinte
     * vérifiable, fichier conservé, émission précédente remplacée.
     */
    public function test_issue_and_verify(): void {
        global $DB;
        $this->gen->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 2, 3);
        $refused = ep_issue_supplement($this->ep, $this->context, $this->student->id, get_admin()->id);
        $this->assertNull($refused->issue);
        $this->assertNotEmpty($refused->missing);

        $DB->set_field('ep', 'supplementprefix', 'ENVT-SD', ['id' => $this->ep->id]);
        $ep = $DB->get_record('ep', ['id' => $this->ep->id]);
        $events = $this->redirectEvents();
        $first = ep_issue_supplement($ep, $this->context, $this->student->id, get_admin()->id, 'fr', true)->issue;
        $this->assertMatchesRegularExpression('/^ENVT-SD-\d{4}-' . $ep->id . '-00001$/', $first->number);
        $this->assertSame(64, strlen($first->verifycode));
        $file = ep_get_supplement_issue_file($this->context, $first->id);
        $this->assertNotNull($file);
        $this->assertStringStartsWith('%PDF', $file->get_content());
        $this->assertSame($first->filehash, sha1($file->get_content()));
        $this->assertInstanceOf(event\supplement_issued::class, $events->get_events()[0]);
        $events->close();

        $second = ep_issue_supplement($ep, $this->context, $this->student->id, get_admin()->id, 'en', true)->issue;
        $this->assertNotSame($first->number, $second->number);
        $this->assertEquals($second->id, $DB->get_field('ep_supplement_issue', 'replacedby', ['id' => $first->id]));
        $this->assertCount(1, ep_get_supplement_issues($ep->id, $this->student->id, true));

        $this->assertEquals($second->id, ep_verify_supplement($second->number, substr($second->verifycode, 0, 16))->id);
        $this->assertNull(ep_verify_supplement($second->number, 'deadbeefdeadbeef'));
        $this->assertNull(ep_verify_supplement($second->number, substr($second->verifycode, 0, 8)));
        $this->assertNull(ep_verify_supplement('inconnu', $second->verifycode));
    }

    /**
     * Transfert : crédits, justificatifs, éléments et décisions d'année passent à l'activité de
     * destination ; types et EP rapprochés par leur code, créés au besoin.
     */
    public function test_transfer_student(): void {
        global $DB;
        $course2 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->student->id, $course2->id, 'student');
        $target = $this->getDataGenerator()->create_module('ep', ['course' => $course2->id, 'currentstudyyear' => 3]);
        $targetcontext = \context_module::instance($target->cmid);

        $source = $this->gen->create_activity($this->ep, ['name' => 'Échographie', 'code' => 'EP-ECHO']);
        $this->gen->create_activity($target, ['name' => 'Échographie (copie)', 'code' => 'EP-ECHO']);
        $custom = $this->gen->create_type($this->ep, ['name' => 'Langues', 'code' => 'langues']);
        $registration = ep_register_to_activity($this->ep, $source, $this->student->id, 3)->creditid;
        $declared = ep_create_credit($this->ep, $this->student->id, $custom, ['name' => 'TOEIC', 'claimedects' => 1]);
        get_file_storage()->create_file_from_string([
            'contextid' => $this->context->id, 'component' => 'mod_ep', 'filearea' => EP_EVIDENCE_FILEAREA,
            'itemid' => $declared, 'filepath' => '/', 'filename' => 'toeic.pdf',
        ], '%PDF-1.4 test');
        ep_set_year_validation($this->ep, $this->student->id, 3, true, '', get_admin()->id);
        ep_create_default_supplement_categories($this->ep->id);
        $categories = ep_get_supplement_categories($this->ep->id);
        $category = reset($categories);
        ep_create_supplement_item($this->ep, $this->student->id, $category, (object) ['title' => 'BDE']);

        $result = ep_transfer_student($this->ep, $target, $this->student->id);
        $this->assertSame([2, 1, 1], [$result->credits, $result->items, $result->years]);
        $this->assertSame(0, $DB->count_records('ep_credit', ['epid' => $this->ep->id]));

        $moved = $DB->get_record('ep_credit', ['id' => $registration]);
        $this->assertEquals($target->id, $moved->epid);
        $this->assertEquals(ep_get_type_by_code($target->id, EP_TYPE_ACADEMIC)->id, $moved->typeid);
        $this->assertSame('Échographie (copie)', $DB->get_field('ep_activity', 'name', ['id' => $moved->activityid]));
        $newtype = ep_get_type_by_code($target->id, 'langues');
        $this->assertNotFalse($newtype);
        $this->assertEquals($newtype->id, $DB->get_field('ep_credit', 'typeid', ['id' => $declared]));
        $this->assertCount(1, ep_get_evidence_files($targetcontext, $declared));
        $this->assertCount(0, ep_get_evidence_files($this->context, $declared));
        $this->assertCount(1, ep_get_year_validations($target->id)[$this->student->id]);
        $this->assertCount(1, ep_get_supplement_items($target->id, ['userid' => $this->student->id]));
    }
}
