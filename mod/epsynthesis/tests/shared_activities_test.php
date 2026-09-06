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

namespace mod_epsynthesis;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

/**
 * EP académiques partagés : définis une seule fois dans une activité de suivi, ils sont proposés
 * au catalogue de toutes les promotions qu'elle suit, et leur responsable statue sur toutes leurs
 * inscriptions — c'est bien le même EP, avec les mêmes places, pour des étudiants de promotions
 * différentes.
 *
 * @package    mod_epsynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_get_catalog_activities
 * @covers     ::ep_get_activity_type
 * @covers     ::ep_can_validate_credit
 * @covers     ::epsynthesis_get_followup_activities
 */
final class shared_activities_test extends \advanced_testcase {

    /** @var \stdClass Cours de suivi portant la synthèse. */
    protected $followupcourse;

    /** @var \stdClass Instance mod_epsynthesis. */
    protected $epsynthesis;

    /** @var \context_module */
    protected $synthesiscontext;

    /** @var \stdClass Instance mod_ep de la promotion A3. */
    protected $epa;

    /** @var \stdClass Instance mod_ep de la promotion A4, non suivie par la synthèse. */
    protected $epb;

    /** @var \stdClass Étudiant de la promotion A3. */
    protected $studenta;

    /** @var \stdClass Responsable de l'EP partagé, enseignant du seul cours de suivi. */
    protected $owner;

    /** @var \stdClass EP partagé de 4 ECTS et 1 place. */
    protected $activity;

    /**
     * Deux promotions, un cours de suivi qui ne suit que la première, et un EP partagé de 4 ECTS
     * dont le responsable n'a aucun rôle dans les promotions.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        $coursea = $generator->create_course();
        $courseb = $generator->create_course();
        $this->followupcourse = $generator->create_course();

        $this->epa = $generator->create_module('ep', ['course' => $coursea->id, 'currentstudyyear' => 3]);
        $this->epb = $generator->create_module('ep', ['course' => $courseb->id, 'currentstudyyear' => 4]);
        $this->epsynthesis = $generator->create_module('epsynthesis', ['course' => $this->followupcourse->id]);
        $this->synthesiscontext = \context_module::instance($this->epsynthesis->cmid);

        $this->studenta = $generator->create_and_enrol($coursea, 'student');
        $this->owner = $generator->create_and_enrol($this->followupcourse, 'teacher');

        $syngenerator = $generator->get_plugin_generator('mod_epsynthesis');
        $syngenerator->link_ep($this->epsynthesis, $this->epa->cmid);
        $this->activity = $syngenerator->create_shared_activity($this->epsynthesis,
            ['name' => 'Clinique équine', 'ects' => 4, 'capacity' => 1]);
        \ep_set_activity_teachers($this->activity->id, [$this->owner->id]);
    }

    /**
     * L'EP partagé est proposé au catalogue des promotions suivies par la synthèse, et d'elles
     * seules : une promotion qui n'y est pas liée ne le voit pas.
     */
    public function test_shared_activity_is_offered_to_linked_cohorts_only(): void {
        $catalogue = ep_get_catalog_activities($this->epa);
        $this->assertArrayHasKey($this->activity->id, $catalogue);

        $this->assertArrayNotHasKey($this->activity->id, ep_get_catalog_activities($this->epb));
    }

    /**
     * L'inscription à un EP partagé compte sous le type académique interne de la promotion de
     * l'étudiant : les plafonds qui s'appliquent sont ceux de sa promotion, pas ceux de la
     * synthèse, qui n'en a pas.
     */
    public function test_registration_uses_the_academic_type_of_the_student_cohort(): void {
        global $DB;

        $type = ep_get_activity_type($this->epa, $this->activity);
        $this->assertEquals(ep_get_type_by_code($this->epa->id, EP_TYPE_ACADEMIC)->id, $type->id);

        $creditid = ep_register_to_activity($this->epa, $this->activity, $this->studenta->id, 3);
        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);

        $this->assertEquals($this->epa->id, $credit->epid);
        $this->assertEquals($type->id, $credit->typeid);
        $this->assertEquals(4, $credit->claimedects);
        $this->assertEquals(EP_STATUS_PENDING, $credit->status);
    }

    /**
     * Le responsable d'un EP partagé statue sur ses inscriptions sans avoir de rôle dans la
     * promotion de l'étudiant : c'est ce que la DEVE lui délègue en le désignant, et sans quoi un
     * EP ouvert à plusieurs promotions n'aurait personne pour le suivre.
     */
    public function test_owner_decides_without_a_role_in_the_student_cohort(): void {
        global $DB;

        $creditid = ep_register_to_activity($this->epa, $this->activity, $this->studenta->id, 3);
        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        $epacontext = \context_module::instance($this->epa->cmid);

        $this->assertFalse(has_capability('mod/ep:evaluateteacher', $epacontext, $this->owner->id));
        $this->assertTrue(ep_can_validate_credit($this->epa, $credit, $epacontext, $this->owner->id));

        // Un enseignant du cours de suivi qui n'est pas responsable de cet EP n'a rien à y dire.
        $other = $this->getDataGenerator()->create_and_enrol($this->followupcourse, 'teacher');
        $this->assertFalse(ep_can_validate_credit($this->epa, $credit, $epacontext, $other->id));
    }

    /**
     * Le suivi EP par EP montre au responsable son EP et l'état de ses inscriptions, toutes
     * promotions confondues. Le nombre de places ne bloque rien : la seconde inscription reste
     * possible sur un EP d'une place, et c'est lui qui arbitre.
     */
    public function test_followup_aggregates_registrations_across_cohorts(): void {
        global $DB;

        // Une seconde promotion, suivie elle aussi : deux étudiants, un seul EP.
        $courseb = get_course($this->epb->course);
        $studentb = $this->getDataGenerator()->create_and_enrol($courseb, 'student');
        $this->getDataGenerator()->get_plugin_generator('mod_epsynthesis')
            ->link_ep($this->epsynthesis, $this->epb->cmid);

        $firstid = ep_register_to_activity($this->epa, $this->activity, $this->studenta->id, 3);
        ep_register_to_activity($this->epb, $this->activity, $studentb->id, 4);

        $counts = ep_get_activity_registration_counts($this->activity->id);
        $this->assertSame(2, $counts->pending);
        $this->assertSame(0, $counts->taken);

        ep_accept_registration($DB->get_record('ep_credit', ['id' => $firstid], '*', MUST_EXIST),
            $this->owner->id);

        $followup = epsynthesis_get_followup_activities($this->epsynthesis,
            get_coursemodule_from_id('epsynthesis', $this->epsynthesis->cmid, 0, false, MUST_EXIST),
            $this->synthesiscontext, $this->owner->id);

        $this->assertArrayHasKey($this->activity->id, $followup);
        $row = $followup[$this->activity->id];
        $this->assertTrue($row->shared);
        $this->assertTrue($row->isresponsible);
        $this->assertSame(1, $row->counts->pending);
        $this->assertSame(1, $row->counts->enrolled);

        // Les deux inscriptions, quelle que soit la promotion, sont bien celles du même EP.
        $registrations = ep_get_activity_registrations($this->activity->id);
        $this->assertCount(2, $registrations);
    }

    /**
     * Un enseignant qui n'est responsable d'aucun EP et n'a pas le droit de tout suivre ne voit
     * rien dans le suivi EP par EP : la synthèse n'accorde aucun droit qu'on n'ait déjà.
     */
    public function test_followup_shows_nothing_to_an_unrelated_teacher(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->followupcourse, 'teacher');

        $followup = epsynthesis_get_followup_activities($this->epsynthesis,
            get_coursemodule_from_id('epsynthesis', $this->epsynthesis->cmid, 0, false, MUST_EXIST),
            $this->synthesiscontext, $other->id);

        $this->assertEmpty($followup);
    }
}
