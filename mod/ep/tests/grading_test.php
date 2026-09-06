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
 * Notation groupée d'un EP du catalogue : pour chaque inscription acceptée, le responsable saisit
 * une note sur 20 (qui décide seule de la validation ou du refus) ou coche « Valider » pour
 * retenir directement la totalité des ECTS sans note. Une ligne laissée vide n'est pas traitée.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_grade_credit
 * @covers     ::ep_process_activity_grading
 */
final class grading_test extends \advanced_testcase {

    /** @var \stdClass Instance mod_ep. */
    protected $ep;

    /** @var \stdClass EP du catalogue à 4 ECTS. */
    protected $activity;

    /** @var \stdClass Responsable de l'EP. */
    protected $owner;

    /**
     * Un EP du catalogue et son responsable.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->ep = $this->getDataGenerator()->create_module('ep', [
            'course' => $course->id,
            'currentstudyyear' => 3,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_ep');
        $this->activity = $generator->create_activity($this->ep, ['ects' => 4]);

        $this->owner = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        ep_set_activity_teachers($this->activity->id, [$this->owner->id]);
    }

    /**
     * Inscrit et accepte un étudiant sur l'EP de test, prêt pour la notation.
     *
     * @param \stdClass $student
     * @return int Identifiant du crédit.
     */
    protected function enrol($student) {
        global $DB;

        $creditid = ep_register_to_activity($this->ep, $this->activity, $student->id, 3);
        ep_accept_registration($DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST), $this->owner->id);

        return $creditid;
    }

    /**
     * Une note atteignant le seuil (10/20) vaut validation, à la totalité des ECTS demandés, et
     * la note elle-même est conservée.
     */
    public function test_passing_grade_validates_at_full_ects(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->ep->course, 'student');
        $creditid = $this->enrol($student);

        $processed = ep_process_activity_grading($this->activity->id, [$creditid => '14'], [], $this->owner->id);

        $this->assertSame(1, $processed);
        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        $this->assertEquals(EP_STATUS_VALIDATED, $credit->status);
        $this->assertEquals(4, $credit->retainedects);
        $this->assertEquals(14, $credit->grade);
        $this->assertEquals($this->owner->id, $credit->validatedby);
    }

    /**
     * Une note sous le seuil vaut refus, et la note reste conservée : un refus noté 6/20 en dit
     * plus qu'un refus muet.
     */
    public function test_failing_grade_rejects_and_keeps_the_grade(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->ep->course, 'student');
        $creditid = $this->enrol($student);

        ep_process_activity_grading($this->activity->id, [$creditid => '6'], [], $this->owner->id);

        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        $this->assertEquals(EP_STATUS_REJECTED, $credit->status);
        $this->assertEquals(0, $credit->retainedects);
        $this->assertEquals(6, $credit->grade);
    }

    /**
     * Cocher « Valider » sans note retient la totalité des ECTS sans qu'aucune note ne soit
     * enregistrée.
     */
    public function test_direct_validation_without_grade(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->ep->course, 'student');
        $creditid = $this->enrol($student);

        $processed = ep_process_activity_grading($this->activity->id, [], [$creditid => 1], $this->owner->id);

        $this->assertSame(1, $processed);
        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        $this->assertEquals(EP_STATUS_VALIDATED, $credit->status);
        $this->assertEquals(4, $credit->retainedects);
        $this->assertNull($credit->grade);
    }

    /**
     * Une note l'emporte sur la case « Valider » cochée par ailleurs : les deux ne sont pas
     * censées être utilisées ensemble, mais si elles le sont, la note reste la décision qui
     * compte, pas un simple raccourci qu'elle court-circuiterait.
     */
    public function test_grade_takes_precedence_over_direct_validation(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->ep->course, 'student');
        $creditid = $this->enrol($student);

        ep_process_activity_grading($this->activity->id, [$creditid => '4'], [$creditid => 1], $this->owner->id);

        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        $this->assertEquals(EP_STATUS_REJECTED, $credit->status);
        $this->assertEquals(4, $credit->grade);
    }

    /**
     * Une ligne sans note ni case cochée n'est pas traitée : le responsable peut noter une partie
     * du groupe et revenir plus tard pour le reste.
     */
    public function test_blank_row_is_left_untouched(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->ep->course, 'student');
        $creditid = $this->enrol($student);

        $processed = ep_process_activity_grading($this->activity->id, [$creditid => ''], [], $this->owner->id);

        $this->assertSame(0, $processed);
        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        $this->assertEquals(EP_STATUS_ENROLLED, $credit->status);
        $this->assertNull($credit->grade);
    }

    /**
     * La notation groupée ne porte que sur les inscriptions encore acceptées : un crédit déjà
     * validé ou refusé entre-temps n'est pas repris, même si le formulaire soumis le visait
     * encore (double soumission, deux onglets ouverts).
     */
    public function test_already_decided_credits_are_not_reprocessed(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->ep->course, 'student');
        $creditid = $this->enrol($student);

        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        ep_validate_credit($credit, $this->owner->id, 4);

        $processed = ep_process_activity_grading($this->activity->id, [$creditid => '20'], [], $this->owner->id);

        $this->assertSame(0, $processed);
        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        $this->assertNull($credit->grade);
    }

    /**
     * ep_render_credit_summary() affiche la note sur une ligne dédiée, y compris quand elle a
     * conduit à un refus.
     */
    public function test_grade_appears_in_the_credit_summary(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->ep->course, 'student');
        $creditid = $this->enrol($student);

        ep_process_activity_grading($this->activity->id, [$creditid => '8'], [], $this->owner->id);

        $summary = ep_render_credit_summary($DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST));
        $this->assertStringContainsString(get_string('grade', 'mod_ep'), $summary);
        $this->assertStringContainsString('8', $summary);
    }
}
