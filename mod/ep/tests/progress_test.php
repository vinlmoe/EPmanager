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
 * Décompte des ECTS d'enseignement personnalisé : application des plafonds par type (annuel puis
 * cursus), minimums par année et minimum de cursus.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_get_student_progress
 * @covers     ::ep_filter_due_years
 */
final class progress_test extends \advanced_testcase {
    /** @var \stdClass Instance de l'activité. */
    protected $ep;

    /** @var \stdClass Étudiant. */
    protected $student;

    /** @var \mod_ep_generator */
    protected $generator;

    /**
     * Un cours, une activité, un étudiant : le décor minimal commun à tous les tests ci-dessous.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->ep = $this->getDataGenerator()->create_module('ep', [
            'course' => $course->id,
            'currentstudyyear' => 3,
        ]);
        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_ep');
    }

    /**
     * Les six types sont créés avec l'instance : sans eux, aucun EP ne pourrait être rattaché à
     * quoi que ce soit.
     */
    public function test_default_types_are_created_with_the_instance(): void {
        $types = ep_get_types($this->ep->id);
        $this->assertCount(6, $types);

        foreach (array_keys(ep_default_type_definitions()) as $code) {
            $this->assertNotFalse(ep_get_type_by_code($this->ep->id, $code), "type manquant : $code");
        }

        // Seul le type stage est attribué sans vérification d'un enseignant, et seul le type
        // académique interne passe par le catalogue.
        $this->assertEquals(1, ep_get_type_by_code($this->ep->id, EP_TYPE_STAGE)->autovalidate);
        $this->assertEquals(0, ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT)->autovalidate);
        $this->assertEquals(1, ep_get_type_by_code($this->ep->id, EP_TYPE_ACADEMIC)->catalog);
    }

    /**
     * Sans plafond, tout ce qui est validé est retenu.
     */
    public function test_without_cap_everything_is_retained(): void {
        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 4, 2);
        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 3, 3);

        $progress = ep_get_student_progress($this->ep, $this->student->id);

        $this->assertEquals(7, $progress->totalretained);
        $this->assertEquals(7, $progress->totalvalidated);
        $this->assertEquals(0, $progress->totalcapped);
    }

    /**
     * Le plafond de cursus d'un type écrête le total, sans toucher aux crédits eux-mêmes : ce qui
     * dépasse est signalé à part (« non retenus ») et reviendra au décompte si le plafond est
     * relevé.
     */
    public function test_cursus_cap_limits_the_total_of_its_type_only(): void {
        $this->generator->configure_type($this->ep, EP_TYPE_SPORT, ['maxects' => 5]);

        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 4, 2);
        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 4, 3);
        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_ENGAGEMENT, 3, 3);

        $progress = ep_get_student_progress($this->ep, $this->student->id);

        $sport = ep_get_type_by_code($this->ep->id, EP_TYPE_SPORT);
        $this->assertEquals(8, $progress->types[$sport->id]->validated);
        $this->assertEquals(5, $progress->types[$sport->id]->retained);
        $this->assertEquals(3, $progress->types[$sport->id]->capped);

        // Le plafond d'un type ne déborde pas sur les autres.
        $engagement = ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT);
        $this->assertEquals(3, $progress->types[$engagement->id]->retained);
        $this->assertEquals(8, $progress->totalretained);
    }

    /**
     * Le plafond de cursus se consomme par années croissantes : les ECTS acquis en premier sont
     * retenus en premier, une année déjà validée ne peut donc pas être remise en cause par un EP
     * saisi plus tard sur une année ultérieure.
     */
    public function test_cursus_cap_is_consumed_from_the_earliest_year(): void {
        $this->generator->configure_type($this->ep, EP_TYPE_SPORT, ['maxects' => 5]);

        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 4, 3);
        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 4, 2);

        $progress = ep_get_student_progress($this->ep, $this->student->id);

        // L'année 2 prend ses 4 ECTS en entier, l'année 3 n'a plus qu'un ECTS sous le plafond.
        $this->assertEquals(4, $progress->years[2]->retained);
        $this->assertEquals(1, $progress->years[3]->retained);
        $this->assertEquals(3, $progress->years[3]->capped);
    }

    /**
     * Le plafond annuel s'applique avant celui du cursus : une année seule ne peut pas dépasser
     * son propre maximum, même si le plafond de cursus le permettrait.
     */
    public function test_yearly_cap_applies_before_the_cursus_cap(): void {
        $this->generator->configure_type(
            $this->ep,
            EP_TYPE_PROFESSIONAL,
            ['maxectsperyear' => 2, 'maxects' => 10]
        );

        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_PROFESSIONAL, 5, 2);
        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_PROFESSIONAL, 5, 3);

        $progress = ep_get_student_progress($this->ep, $this->student->id);

        $this->assertEquals(2, $progress->years[2]->retained);
        $this->assertEquals(2, $progress->years[3]->retained);
        $this->assertEquals(4, $progress->totalretained);
        $this->assertEquals(6, $progress->totalcapped);
    }

    /**
     * Le minimum annuel porte sur les ECTS retenus, tous types confondus : ce qui est écrêté par
     * un plafond ne compte pas pour l'atteindre.
     */
    public function test_year_minimum_counts_retained_credits_only(): void {
        ep_set_year_requirement($this->ep->id, 2, 6);
        $this->generator->configure_type($this->ep, EP_TYPE_SPORT, ['maxects' => 4]);
        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 6, 2);

        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $this->assertFalse($progress->years[2]->done);

        // Un EP d'un autre type comble le manque : le plafond du sport ne le limite pas.
        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_ENGAGEMENT, 2, 2);
        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $this->assertTrue($progress->years[2]->done);
    }

    /**
     * Le minimum de cursus s'ajoute aux minimums annuels : les atteindre tous ne suffit pas
     * nécessairement à valider l'ensemble.
     */
    public function test_cursus_minimum_is_independent_from_the_yearly_ones(): void {
        global $DB;

        ep_set_year_requirement($this->ep->id, 2, 2);
        $DB->set_field('ep', 'mincursusects', 10, ['id' => $this->ep->id]);
        $this->ep = $DB->get_record('ep', ['id' => $this->ep->id], '*', MUST_EXIST);

        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_ENGAGEMENT, 3, 2);

        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $this->assertTrue($progress->years[2]->done);
        $this->assertFalse($progress->cursusdone);
        $this->assertFalse($progress->complete);

        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_ENGAGEMENT, 7, 3);
        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $this->assertTrue($progress->cursusdone);
        $this->assertTrue($progress->complete);
    }

    /**
     * Les années à venir ne sont pas encore exigibles : un minimum défini pour l'année 5 ne fait
     * pas apparaître en retard une promotion qui n'en est qu'à sa troisième année.
     */
    public function test_future_years_are_not_yet_due(): void {
        ep_set_year_requirement($this->ep->id, 2, 2);
        ep_set_year_requirement($this->ep->id, 5, 8);
        $this->generator->create_validated_credit($this->ep, $this->student->id, EP_TYPE_ENGAGEMENT, 2, 2);

        $progress = ep_get_student_progress($this->ep, $this->student->id);

        // L'année 5 figure bien dans le bilan, mais n'entre pas dans le décompte des années dues.
        $this->assertArrayHasKey(5, $progress->years);
        $this->assertSame(1, $progress->yearstotal);
        $this->assertSame(1, $progress->yearsdone);
        $this->assertTrue($progress->complete);
    }

    /**
     * Une demande en attente est comptée à part : elle ne fait pas atteindre un minimum tant que
     * personne ne l'a validée.
     */
    public function test_pending_requests_do_not_count_towards_minimums(): void {
        ep_set_year_requirement($this->ep->id, 3, 4);
        $type = ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT);
        ep_create_credit($this->ep, $this->student->id, $type, [
            'studyyear' => 3,
            'name' => 'Association étudiante',
            'claimedects' => 4,
        ]);

        $progress = ep_get_student_progress($this->ep, $this->student->id);

        $this->assertEquals(0, $progress->totalretained);
        $this->assertEquals(4, $progress->totalpending);
        $this->assertFalse($progress->years[3]->done);
    }
}
