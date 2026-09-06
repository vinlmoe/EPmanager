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
 * Règles de calcul du nombre d'ECTS demandés par une déclaration : proposé par l'étudiant,
 * forfaitaire (un ECTS par déclaration de sport, par exemple) ou compté à la semaine.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_type_ects_mode
 * @covers     ::ep_type_claimed_ects
 * @covers     ::ep_type_declared_weeks
 * @covers     ::ep_type_ects_rule_is_set
 */
final class ects_rules_test extends \advanced_testcase {

    /** @var \stdClass Instance mod_ep. */
    protected $ep;

    /** @var \stdClass */
    protected $student;

    /**
     * Une activité et un étudiant ; chaque test règle lui-même la règle du type qu'il éprouve.
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
    }

    /**
     * Par défaut, le nombre d'ECTS reste celui que l'étudiant propose.
     */
    public function test_free_mode_keeps_the_number_proposed_by_the_student(): void {
        $type = ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT);

        $this->assertSame(EP_ECTS_MODE_FREE, ep_type_ects_mode($type));
        $this->assertEquals(2.5, ep_type_claimed_ects($type, 2.5, 0));
        $this->assertEquals(0, ep_type_declared_weeks($type, 4));
    }

    /**
     * Forfait : toute déclaration vaut le même nombre d'ECTS, quel que soit celui que l'étudiant
     * aurait pu envoyer — le champ ne lui est même pas proposé.
     */
    public function test_flat_mode_awards_the_same_amount_whatever_is_submitted(): void {
        global $DB;

        $type = $this->configure(EP_TYPE_SPORT, EP_ECTS_MODE_FLAT, 1);

        $this->assertEquals(1, ep_type_claimed_ects($type, 0, 0));
        $this->assertEquals(1, ep_type_claimed_ects($type, 12, 8));

        $creditid = ep_create_credit($this->ep, $this->student->id, $type, [
            'name' => 'Championnat universitaire',
            'claimedects' => ep_type_claimed_ects($type, 12, 0),
            'weeks' => ep_type_declared_weeks($type, 12),
            'studyyear' => 3,
        ]);

        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        $this->assertEquals(1, $credit->claimedects);
        $this->assertEquals(0, $credit->weeks);
    }

    /**
     * Comptage par semaine : l'étudiant déclare une durée, les ECTS en découlent, et la durée est
     * conservée — c'est elle qui explique le nombre au validateur.
     */
    public function test_weekly_mode_multiplies_the_declared_weeks_by_the_rate(): void {
        global $DB;

        $type = $this->configure(EP_TYPE_PROFESSIONAL, EP_ECTS_MODE_WEEKLY, 0.5);

        $this->assertEquals(1.5, ep_type_claimed_ects($type, 0, 3));
        // Le nombre que l'étudiant aurait pu proposer n'entre pas dans le calcul.
        $this->assertEquals(1.5, ep_type_claimed_ects($type, 10, 3));

        $creditid = ep_create_credit($this->ep, $this->student->id, $type, [
            'name' => 'Assistanat en clinique',
            'claimedects' => ep_type_claimed_ects($type, 10, 3),
            'weeks' => ep_type_declared_weeks($type, 3),
            'studyyear' => 3,
        ]);

        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        $this->assertEquals(1.5, $credit->claimedects);
        $this->assertEquals(3, $credit->weeks);

        // Une fois validée, la demande compte pour ce qu'elle vaut.
        ep_validate_credit($credit, 0, $credit->claimedects);
        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $this->assertEquals(1.5, $progress->totalretained);
    }

    /**
     * Un forfait ou un barème laissé à 0 est un type que la DEVE n'a pas fini de régler : il est
     * signalé comme tel plutôt que de produire des déclarations sans ECTS.
     */
    public function test_a_rate_left_at_zero_is_reported_as_unset(): void {
        $free = ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT);
        $this->assertTrue(ep_type_ects_rule_is_set($free));

        $flat = $this->configure(EP_TYPE_SPORT, EP_ECTS_MODE_FLAT, 0);
        $this->assertFalse(ep_type_ects_rule_is_set($flat));

        $flat = $this->configure(EP_TYPE_SPORT, EP_ECTS_MODE_FLAT, 1);
        $this->assertTrue(ep_type_ects_rule_is_set($flat));
    }

    /**
     * Les types du catalogue et ceux attribués automatiquement ne relèvent pas de ces règles :
     * l'EP du catalogue porte ses propres ECTS, et le type stage son barème par jour. Une valeur
     * enregistrée par erreur sur eux reste sans effet.
     */
    public function test_catalog_and_automatic_types_ignore_the_rule(): void {
        global $DB;

        foreach ([EP_TYPE_ACADEMIC, EP_TYPE_STAGE] as $code) {
            $type = ep_get_type_by_code($this->ep->id, $code);
            $type->ectsmode = EP_ECTS_MODE_FLAT;
            $type->ectsvalue = 5;
            $DB->update_record('ep_type', $type);

            $this->assertSame(EP_ECTS_MODE_FREE, ep_type_ects_mode($type));
            $this->assertEquals(3, ep_type_claimed_ects($type, 3, 0));
        }
    }

    /**
     * Règle la façon dont un type calcule les ECTS d'une déclaration.
     *
     * @param string $code Un des EP_TYPE_*.
     * @param string $mode Un des EP_ECTS_MODE_*.
     * @param float $value Forfait, ou nombre d'ECTS par semaine.
     * @return \stdClass Le type mis à jour.
     */
    protected function configure($code, $mode, $value) {
        return $this->getDataGenerator()->get_plugin_generator('mod_ep')
            ->configure_type($this->ep, $code, ['ectsmode' => $mode, 'ectsvalue' => $value]);
    }
}
