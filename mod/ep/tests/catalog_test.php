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
 * Circuit des EP académiques internes : inscription au catalogue, décompte des places, et
 * validation par le responsable de l'EP — qui est le seul, avec la DEVE, à pouvoir se prononcer.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_register_to_activity
 * @covers     ::ep_get_activity_remaining_places
 * @covers     ::ep_can_validate_credit
 * @covers     ::ep_validate_credit
 */
final class catalog_test extends \advanced_testcase {

    /** @var \stdClass */
    protected $ep;

    /** @var \context_module */
    protected $context;

    /** @var \stdClass */
    protected $activity;

    /** @var \stdClass */
    protected $student;

    /** @var \stdClass Responsable de l'EP du catalogue. */
    protected $owner;

    /** @var \stdClass Enseignant sans lien avec cet EP. */
    protected $otherteacher;

    /**
     * Un cours, une activité, un EP de catalogue à 3 ECTS et 2 places, son responsable, un autre
     * enseignant et un étudiant.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('ep', [
            'course' => $course->id,
            'currentstudyyear' => 3,
        ]);
        $this->ep = $cm;
        $this->context = \context_module::instance($cm->cmid);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_ep');
        $this->activity = $generator->create_activity($this->ep, ['ects' => 3, 'capacity' => 2]);

        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->owner = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->otherteacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        ep_set_activity_teachers($this->activity->id, [$this->owner->id]);
    }

    /**
     * S'inscrire ne donne pas d'ECTS : la demande reste en attente, avec le nombre d'ECTS de
     * l'EP, jusqu'à ce que son responsable se prononce.
     */
    public function test_registration_awards_nothing_until_validated(): void {
        $creditid = ep_register_to_activity($this->ep, $this->activity, $this->student->id, 3);

        $credit = $this->credit($creditid);
        $this->assertEquals(EP_STATUS_PENDING, $credit->status);
        $this->assertEquals(3, $credit->claimedects);
        $this->assertEquals(0, $credit->retainedects);

        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $this->assertEquals(0, $progress->totalretained);
        $this->assertEquals(3, $progress->totalpending);

        ep_validate_credit($this->credit($creditid), $this->owner->id, 3);

        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $this->assertEquals(3, $progress->totalretained);
        $this->assertEquals(0, $progress->totalpending);
    }

    /**
     * Seul le responsable de l'EP peut valider l'inscription : ni l'enseignant référent de
     * l'étudiant (il n'a pas suivi cet EP), ni un enseignant quelconque du cours.
     */
    public function test_only_the_activity_owner_validates_the_registration(): void {
        $creditid = ep_register_to_activity($this->ep, $this->activity, $this->student->id, 3);
        $credit = $this->credit($creditid);

        $this->assertTrue(ep_can_validate_credit($this->ep, $credit, $this->context, $this->owner->id));
        $this->assertFalse(ep_can_validate_credit($this->ep, $credit, $this->context, $this->otherteacher->id));
        $this->assertFalse(ep_can_validate_credit($this->ep, $credit, $this->context, $this->student->id));
    }

    /**
     * Le responsable peut ne retenir qu'une partie des ECTS demandés, sans avoir à refuser toute
     * l'inscription.
     */
    public function test_owner_may_retain_only_part_of_the_credits(): void {
        $creditid = ep_register_to_activity($this->ep, $this->activity, $this->student->id, 3);
        ep_validate_credit($this->credit($creditid), $this->owner->id, 1.5, 'Participation partielle');

        $credit = $this->credit($creditid);
        $this->assertEquals(EP_STATUS_VALIDATED, $credit->status);
        $this->assertEquals(1.5, $credit->retainedects);

        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $this->assertEquals(1.5, $progress->totalretained);
    }

    /**
     * Les inscriptions en attente occupent une place : ouvrir plus de places que ce que le
     * responsable pourra valider reviendrait à en promettre une qui n'existe pas. Une demande
     * retirée ou refusée libère la sienne.
     */
    public function test_pending_registrations_take_up_a_place(): void {
        $this->assertSame(2, ep_get_activity_remaining_places($this->activity));

        $firstid = ep_register_to_activity($this->ep, $this->activity, $this->student->id, 3);
        $this->assertSame(1, ep_get_activity_remaining_places($this->activity));

        $other = $this->getDataGenerator()->create_user();
        ep_register_to_activity($this->ep, $this->activity, $other->id, 3);
        $this->assertSame(0, ep_get_activity_remaining_places($this->activity));

        ep_cancel_credit($this->credit($firstid), $this->student->id);
        $this->assertSame(1, ep_get_activity_remaining_places($this->activity));
    }

    /**
     * Un EP sans limite de places n'en décompte aucune.
     */
    public function test_unlimited_activity_has_no_remaining_count(): void {
        $unlimited = $this->getDataGenerator()->get_plugin_generator('mod_ep')
            ->create_activity($this->ep, ['capacity' => 0]);

        $this->assertNull(ep_get_activity_remaining_places($unlimited));
    }

    /**
     * Une inscription refusée ou retirée n'en est plus une : l'étudiant peut se réinscrire, une
     * inscription en attente ou validée l'en empêche.
     */
    public function test_active_registration_blocks_a_second_one(): void {
        $creditid = ep_register_to_activity($this->ep, $this->activity, $this->student->id, 3);
        $this->assertNotFalse(ep_get_active_registration($this->activity->id, $this->student->id));

        ep_reject_credit($this->credit($creditid), $this->owner->id, 'Absences répétées');
        $this->assertFalse(ep_get_active_registration($this->activity->id, $this->student->id));
    }

    /**
     * Un EP du catalogue ne peut être rattaché qu'à un type non automatique : sur le type stage,
     * l'inscription elle-même vaudrait attribution, sans que personne n'ait rien validé.
     */
    public function test_catalogable_types_exclude_automatic_ones(): void {
        $catalogable = ep_get_catalogable_types($this->ep->id);

        $this->assertArrayNotHasKey(ep_get_type_by_code($this->ep->id, EP_TYPE_STAGE)->id, $catalogable);
        $this->assertArrayHasKey(ep_get_type_by_code($this->ep->id, EP_TYPE_ACADEMIC)->id, $catalogable);
    }

    /**
     * Relit un crédit depuis la base : les fonctions de validation prennent l'enregistrement à
     * jour, pas la copie que le test garderait en mémoire.
     *
     * @param int $creditid
     * @return \stdClass
     */
    protected function credit($creditid) {
        global $DB;

        return $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
    }
}
