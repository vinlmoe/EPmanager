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

use mod_ep\local\global_archive;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Non-régression des corrections de la revue de sécurité de la version 1.0.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_update_declaration
 * @covers     ::ep_student_can_edit
 * @covers     ::ep_update_supplement_item
 * @covers     ::ep_cancel_credit
 * @covers     ::ep_validate_credit
 * @covers     ::ep_supplement_seal
 * @covers     ::ep_verify_supplement
 * @covers     ::ep_import_settings
 */
final class security_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;

    /** @var \stdClass */
    private $ep;

    /** @var \context_module */
    private $context;

    /** @var \stdClass */
    private $student;

    /** @var \stdClass */
    private $referent;

    /**
     * Une activité dont les déclarations de sport suivent le circuit « référent puis DEVE », un
     * étudiant et son référent.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $this->course->id]);
        $ep = $gen->create_module('ep', ['course' => $this->course->id, 'currentstudyyear' => 3,
            'supplementflow' => 'referent,deve']);
        $this->context = \context_module::instance($ep->cmid);
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->referent = $gen->create_and_enrol($this->course, 'teacher');
        $gen->get_plugin_generator('mod_stage')->assign_teacher($stage, $this->student->id, $this->referent->id);
        $DB->set_field('ep_type', 'validationflow', 'referent,deve', ['epid' => $ep->id, 'code' => EP_TYPE_SPORT]);
        $DB->set_field('ep', 'supplementflow', 'referent,deve', ['id' => $ep->id]);
        $this->ep = $DB->get_record('ep', ['id' => $ep->id]);
    }

    /**
     * Une déclaration validée par le référent et qui attend la DEVE ne se modifie plus, mais se
     * retire encore ; le référent ne retient pas plus que demandé.
     */
    public function test_declaration_locked_after_first_step(): void {
        global $DB;
        $type = ep_get_type_by_code($this->ep->id, EP_TYPE_SPORT);
        $creditid = ep_create_credit($this->ep, $this->student->id, $type, ['name' => 'Rugby', 'claimedects' => 2,
            'studyyear' => 3]);
        $credit = $DB->get_record('ep_credit', ['id' => $creditid]);
        $this->assertTrue(ep_student_can_edit($credit));

        $this->assertTrue(ep_validate_credit($credit, $this->referent->id, 60, '', EP_ROLE_REFERENT));
        $credit = $DB->get_record('ep_credit', ['id' => $creditid]);
        $this->assertSame(EP_ROLE_DEVE, $credit->pendingrole);
        $this->assertEquals(2, $credit->retainedects);
        $this->assertFalse(ep_student_can_edit($credit));
        $this->assertTrue(ep_student_can_cancel($credit));

        $credit->claimedects = 20;
        $this->assertFalse(ep_update_declaration($credit));
        $this->assertEquals(2, $DB->get_field('ep_credit', 'claimedects', ['id' => $creditid]));
    }

    /**
     * Changer le type d'une déclaration en attente fait suivre le circuit du nouveau type : un type
     * validé par la seule DEVE ne peut plus être tranché par le référent.
     */
    public function test_type_change_follows_new_flow(): void {
        global $DB;
        $DB->set_field('ep_type', 'validationflow', 'referent', ['epid' => $this->ep->id, 'code' => EP_TYPE_SPORT]);
        $DB->set_field('ep_type', 'validationflow', 'deve', ['epid' => $this->ep->id, 'code' => EP_TYPE_ENGAGEMENT]);
        $sport = ep_get_type_by_code($this->ep->id, EP_TYPE_SPORT);
        $engagement = ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT);
        $creditid = ep_create_credit($this->ep, $this->student->id, $sport, ['name' => 'Club', 'claimedects' => 2,
            'studyyear' => 3]);
        $credit = $DB->get_record('ep_credit', ['id' => $creditid]);
        $this->assertSame(EP_ROLE_REFERENT, $credit->pendingrole);

        $credit->typeid = $engagement->id;
        $this->assertTrue(ep_update_declaration($credit));
        $credit = $DB->get_record('ep_credit', ['id' => $creditid]);
        $this->assertSame(EP_ROLE_DEVE, $credit->pendingrole);
        $this->assertFalse(ep_validate_credit($credit, $this->referent->id, 60, '', EP_ROLE_REFERENT));
        $this->assertEquals(EP_STATUS_PENDING, $DB->get_field('ep_credit', 'status', ['id' => $creditid]));
    }

    /**
     * Un élément du supplément validé par le référent ne se modifie plus.
     */
    public function test_supplement_item_locked_after_first_step(): void {
        global $DB;
        $categories = ep_get_supplement_categories($this->ep->id);
        $itemid = ep_create_supplement_item($this->ep, $this->student->id, reset($categories), (object) ['title' => 'BDE']);
        $item = $DB->get_record('ep_supplement_item', ['id' => $itemid]);
        $this->assertTrue(ep_decide_supplement_item($this->ep, $item, $this->referent->id, true, 'Vu', EP_ROLE_REFERENT));
        $item = $DB->get_record('ep_supplement_item', ['id' => $itemid]);
        $item->title = 'Président du BDE et trésorier';
        $this->assertFalse(ep_update_supplement_item($item));
        $this->assertSame('BDE', $DB->get_field('ep_supplement_item', 'title', ['id' => $itemid]));
    }

    /**
     * Le responsable d'un EP ne retire plus une inscription qu'il a validée et qui attend la DEVE.
     */
    public function test_owner_cannot_cancel_after_own_step(): void {
        global $DB;
        $DB->set_field('ep_type', 'validationflow', 'owner,deve', ['epid' => $this->ep->id, 'code' => EP_TYPE_ACADEMIC]);
        $activity = $this->getDataGenerator()->get_plugin_generator('mod_ep')->create_activity($this->ep);
        ep_set_activity_teachers($activity->id, [$this->referent->id]);
        $creditid = ep_register_to_activity($this->ep, $activity, $this->student->id, 3)->creditid;
        $credit = $DB->get_record('ep_credit', ['id' => $creditid]);
        $this->assertTrue(ep_validate_credit($credit, $this->referent->id, 2, '', EP_ROLE_OWNER));
        $credit = $DB->get_record('ep_credit', ['id' => $creditid]);
        $this->assertFalse(ep_cancel_credit($credit, $this->referent->id, 'retrait', EP_ROLE_OWNER));
        $this->assertSame(EP_STATUS_PENDING, (int) $DB->get_field('ep_credit', 'status', ['id' => $creditid]));
        $this->assertTrue(ep_cancel_credit($credit, get_admin()->id, 'retrait', EP_ROLE_DEVE));
    }

    /**
     * Seule une émission scellée par le site, au PDF intact, est reconnue : pas une copie
     * restaurée, pas une émission dont le PDF ou la ligne ont été modifiés.
     */
    public function test_only_sealed_issues_verify(): void {
        global $DB;
        $issue = ep_issue_supplement($this->ep, $this->context, $this->student->id, get_admin()->id, 'fr', true)->issue;
        $code = substr($issue->verifycode, 0, 16);
        $this->assertNotNull(ep_verify_supplement($issue->number, $code));

        // Copie restaurée dans une autre activité : conservée, non reconnue.
        $other = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->student->id, $other->id, 'student');
        $target = $this->getDataGenerator()->create_module('ep', ['course' => $other->id]);
        $targetcontext = \context_module::instance($target->cmid);
        $archive = global_archive::open(global_archive::export($this->ep, $this->context));
        global_archive::restore($DB->get_record('ep', ['id' => $target->id]), $targetcontext, $archive, ['data'], get_admin()->id);
        $copy = $DB->get_record('ep_supplement_issue', ['epid' => $target->id]);
        $this->assertNull($copy->seal);
        $this->assertNull(ep_verify_supplement($copy->number, $code));
        $this->assertNotNull(ep_verify_supplement($issue->number, $code));

        // Date antidatée en base : le sceau ne correspond plus.
        $DB->set_field('ep_supplement_issue', 'timeissued', $issue->timeissued - YEARSECS, ['id' => $issue->id]);
        $this->assertNull(ep_verify_supplement($issue->number, $code));
        $DB->set_field('ep_supplement_issue', 'timeissued', $issue->timeissued, ['id' => $issue->id]);

        // PDF remplacé : l'empreinte ne correspond plus.
        $file = ep_get_supplement_issue_file($this->context, $issue->id);
        $record = ['contextid' => $file->get_contextid(), 'component' => 'mod_ep', 'filearea' => $file->get_filearea(),
            'itemid' => $file->get_itemid(), 'filepath' => '/', 'filename' => $file->get_filename()];
        $file->delete();
        get_file_storage()->create_file_from_string($record, '%PDF-1.4 faux');
        $this->assertNull(ep_verify_supplement($issue->number, $code));
    }

    /**
     * Un paramétrage forgé ne peut pas écrire de valeur que les formulaires refuseraient.
     */
    public function test_settings_import_cleans_instance_values(): void {
        global $DB;
        $settings = ep_export_settings($this->ep, false);
        $settings['instance']['supplementprefix'] = '<script>alert(1)</script>-TRES-LONG-PREFIXE';
        $settings['instance']['supplementflow'] = 'student,deve';
        $settings['instance']['supplementevidence'] = 9;
        $settings['instance']['birthdatefield'] = 'password';
        ep_import_settings($this->ep, $settings, ['instance'], get_admin()->id);
        $ep = $DB->get_record('ep', ['id' => $this->ep->id]);
        $this->assertDoesNotMatchRegularExpression('/[<>]/', $ep->supplementprefix);
        $this->assertLessThanOrEqual(20, \core_text::strlen($ep->supplementprefix));
        $this->assertSame(EP_ROLE_DEVE, $ep->supplementflow);
        $this->assertEquals(EP_EVIDENCE_REQUIRED, $ep->supplementevidence);
        $this->assertSame('', $ep->birthdatefield);
    }
}
