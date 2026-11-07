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
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Attribution automatique des ECTS d'EP à partir des stages complémentaires (EP) validés par la
 * DEVE dans l'activité « Gestion des stages » du même cours, et identification des enseignants
 * référents, elle aussi lue dans mod_stage.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_sync_stage_credits
 * @covers     ::ep_get_eligible_stage_entries
 * @covers     ::ep_get_referent_students
 */
final class stage_sync_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;

    /** @var \stdClass Instance mod_ep. */
    protected $ep;

    /** @var \stdClass Instance mod_stage du même cours. */
    protected $stage;

    /** @var \stdClass */
    protected $theme;

    /** @var \stdClass */
    protected $student;

    /**
     * Un cours portant les deux activités, une thématique de stage et un étudiant.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->stage = $this->getDataGenerator()->create_module('stage', ['course' => $this->course->id]);
        $this->ep = $this->getDataGenerator()->create_module('ep', [
            'course' => $this->course->id,
            'currentstudyyear' => 3,
        ]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $stagegenerator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $this->theme = $stagegenerator->create_theme($this->stage, ['name' => 'Animaux de compagnie']);

        // 0,25 ECTS par jour de stage retenu : le barème de référence de tous les tests ci-dessous.
        $this->getDataGenerator()->get_plugin_generator('mod_ep')
            ->configure_type($this->ep, EP_TYPE_STAGE, ['ectsperday' => 0.25]);
    }

    /**
     * Crée un stage et lui applique le type et le statut demandés.
     *
     * @param string $stagetype 'obligatoire' ou 'complementaire'
     * @param bool $validated Validé par la DEVE.
     * @param int $days Durée retenue en jours.
     * @param int $studyyear
     * @return \stdClass La saisie de stage.
     */
    protected function create_stage_entry($stagetype, $validated, $days = 8, $studyyear = 3) {
        global $DB;

        $entry = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry(
            $this->stage,
            $this->student->id,
            $this->theme,
            ['declaredduration' => $days, 'studyyear' => $studyyear]
        );

        stage_set_entry_stagetype($entry->id, $stagetype);
        if ($validated) {
            stage_apply_deve_validation($entry, 0, $days);
        }

        return $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);
    }

    /**
     * Une page ouverte peu après une synchronisation voit quand même un stage que la DEVE vient
     * de valider : c'est le changement des stages qui déclenche le recalcul, pas l'horloge.
     */
    public function test_page_sync_sees_a_stage_validated_just_after_a_sync(): void {
        global $DB;

        $this->assertTrue(ep_sync_stage_credits_if_due($this->ep));
        $entry = $this->create_stage_entry('complementaire', true, 8);

        $this->assertTrue(ep_sync_stage_credits_if_due($this->ep));
        $this->assertEquals(2, $DB->get_field('ep_credit', 'retainedects', ['sourceref' => $entry->id]));
    }

    /**
     * La DEVE corrige la durée retenue dans « Gestion des stages » (sans événement) : la page
     * suivante du module en tient compte.
     */
    public function test_page_sync_follows_a_retained_duration_corrected_afterwards(): void {
        global $DB;

        $entry = $this->create_stage_entry('complementaire', true, 8);
        ep_sync_stage_credits_if_due($this->ep);
        $entry->retainedduration = 12;
        stage_update_entry_details(
            $entry,
            $entry->themeid,
            $entry->structure,
            $entry->datestart,
            $entry->dateend,
            $entry->declaredduration,
            $entry->studyyear,
            $entry->abroad
        );

        $this->assertTrue(ep_sync_stage_credits_if_due($this->ep));
        $this->assertEquals(3, $DB->get_field('ep_credit', 'retainedects', ['sourceref' => $entry->id]));
    }

    /**
     * Rien n'a changé dans les stages ni dans le barème : la page ne recalcule rien.
     */
    public function test_page_sync_does_nothing_when_nothing_changed(): void {
        $this->create_stage_entry('complementaire', true, 8);
        $this->assertTrue(ep_sync_stage_credits_if_due($this->ep));
        $this->assertFalse(ep_sync_stage_credits_if_due($this->ep));
    }

    /**
     * Un changement de barème du type stage est vu lui aussi.
     */
    public function test_page_sync_follows_a_rate_change(): void {
        global $DB;

        $entry = $this->create_stage_entry('complementaire', true, 8);
        ep_sync_stage_credits_if_due($this->ep);
        $type = $DB->get_record('ep_type', ['epid' => $this->ep->id, 'code' => 'stage']);
        $DB->set_field('ep_type', 'ectsperday', 0.5, ['id' => $type->id]);

        $this->assertTrue(ep_sync_stage_credits_if_due($this->ep));
        $this->assertEquals(4, $DB->get_field('ep_credit', 'retainedects', ['sourceref' => $entry->id]));
    }

    /**
     * La DEVE valide un stage puis, aussitôt, les années : le stage compte, sans attendre qu'une
     * page du module ou la tâche de la nuit ait recalculé les crédits.
     */
    public function test_year_validation_counts_a_stage_validated_just_before(): void {
        ep_set_year_requirement($this->ep->id, 3, 2);
        ep_sync_stage_credits($this->ep);
        $this->create_stage_entry('complementaire', true, 8);

        $result = ep_validate_years_in_bulk($this->ep, 3, [(int) $this->student->id], get_admin()->id);
        $this->assertSame(1, $result->validated);
    }

    /**
     * Même chose pour le supplément au diplôme : le stage validé juste avant y figure.
     */
    public function test_supplement_includes_a_stage_validated_just_before(): void {
        ep_sync_stage_credits($this->ep);
        $this->create_stage_entry('complementaire', true, 8);

        $data = ep_build_supplement($this->ep, \core_user::get_user($this->student->id));
        $this->assertCount(1, $data->credits);
    }

    /**
     * Un stage complémentaire validé par la DEVE donne des ECTS sans que personne n'ait à les
     * valider : le crédit naît déjà validé, sans validateur.
     */
    public function test_validated_complementary_stage_awards_credits_automatically(): void {
        global $DB;

        $entry = $this->create_stage_entry('complementaire', true, 8);
        $result = ep_sync_stage_credits($this->ep);

        $this->assertSame(1, $result->created);

        $credit = $DB->get_record(
            'ep_credit',
            ['epid' => $this->ep->id, 'source' => EP_SOURCE_STAGE, 'sourceref' => $entry->id],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(EP_STATUS_VALIDATED, $credit->status);
        $this->assertEquals(2, $credit->retainedects); // 8 jours x 0,25.
        $this->assertEquals(3, $credit->studyyear);
        $this->assertNull($credit->validatedby);
    }

    /**
     * Un stage obligatoire relève du cursus, pas de l'enseignement personnalisé : il ne donne
     * aucun ECTS d'EP, même validé.
     */
    public function test_mandatory_stage_awards_nothing(): void {
        global $DB;

        $this->create_stage_entry('obligatoire', true, 8);
        ep_sync_stage_credits($this->ep);

        $this->assertSame(0, $DB->count_records(
            'ep_credit',
            ['epid' => $this->ep->id, 'source' => EP_SOURCE_STAGE]
        ));
    }

    /**
     * Un stage complémentaire pas encore validé par la DEVE ne donne rien non plus : c'est la
     * validation DEVE qui déclenche l'attribution.
     */
    public function test_unvalidated_complementary_stage_awards_nothing(): void {
        global $DB;

        $this->create_stage_entry('complementaire', false, 8);
        ep_sync_stage_credits($this->ep);

        $this->assertSame(0, $DB->count_records(
            'ep_credit',
            ['epid' => $this->ep->id, 'source' => EP_SOURCE_STAGE]
        ));
    }

    /**
     * La durée retenue peut changer après coup : le crédit suit, il n'est pas figé à sa valeur
     * de création.
     */
    public function test_credit_follows_the_retained_duration(): void {
        global $DB;

        $entry = $this->create_stage_entry('complementaire', true, 8);
        ep_sync_stage_credits($this->ep);

        $entry->retainedduration = 12;
        $DB->update_record('stage_entry', $entry);
        $result = ep_sync_stage_credits($this->ep);

        $this->assertSame(1, $result->updated);
        $credit = $DB->get_record(
            'ep_credit',
            ['epid' => $this->ep->id, 'sourceref' => $entry->id],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(3, $credit->retainedects);
    }

    /**
     * Un stage qui cesse d'être un stage complémentaire validé voit son crédit disparaître :
     * laisser des ECTS acquis sans contrepartie serait pire que de les retirer.
     */
    public function test_credit_disappears_when_the_stage_no_longer_qualifies(): void {
        global $DB;

        $entry = $this->create_stage_entry('complementaire', true, 8);
        ep_sync_stage_credits($this->ep);
        $this->assertSame(1, $DB->count_records('ep_credit', ['epid' => $this->ep->id]));

        stage_set_entry_stagetype($entry->id, 'obligatoire');
        $result = ep_sync_stage_credits($this->ep);

        $this->assertSame(1, $result->deleted);
        $this->assertSame(0, $DB->count_records('ep_credit', ['epid' => $this->ep->id]));
    }

    /**
     * Sans barème, aucun ECTS n'est attribué et les crédits déjà créés sont retirés : c'est ainsi
     * qu'on désactive l'attribution automatique sans avoir à faire le ménage soi-même.
     */
    public function test_zero_rate_removes_automatic_credits(): void {
        global $DB;

        $this->create_stage_entry('complementaire', true, 8);
        ep_sync_stage_credits($this->ep);

        $this->getDataGenerator()->get_plugin_generator('mod_ep')
            ->configure_type($this->ep, EP_TYPE_STAGE, ['ectsperday' => 0]);
        ep_sync_stage_credits($this->ep);

        $this->assertSame(0, $DB->count_records('ep_credit', ['epid' => $this->ep->id]));
    }

    /**
     * Le plafond du type stage s'applique à ces ECTS comme aux autres : ce qui dépasse reste
     * attribué mais n'est plus compté.
     */
    public function test_stage_type_cap_applies_to_automatic_credits(): void {
        $this->getDataGenerator()->get_plugin_generator('mod_ep')
            ->configure_type($this->ep, EP_TYPE_STAGE, ['ectsperday' => 0.25, 'maxects' => 3]);

        $this->create_stage_entry('complementaire', true, 8, 2);
        $this->create_stage_entry('complementaire', true, 8, 3);
        ep_sync_stage_credits($this->ep);

        $progress = ep_get_student_progress($this->ep, $this->student->id);
        $stagetype = ep_get_type_by_code($this->ep->id, EP_TYPE_STAGE);

        $this->assertEquals(4, $progress->types[$stagetype->id]->validated);
        $this->assertEquals(3, $progress->types[$stagetype->id]->retained);
        $this->assertEquals(1, $progress->types[$stagetype->id]->capped);
    }

    /**
     * L'enseignant référent est celui déjà attribué dans mod_stage : rien n'est ressaisi ici, et
     * un retrait d'attribution dans l'activité d'origine se répercute immédiatement.
     */
    public function test_referent_students_come_from_the_stage_activity(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->assertSame([], ep_get_referent_students($this->ep, $teacher->id));

        stage_set_student_teachers($this->stage->id, $this->student->id, [$teacher->id]);
        $this->assertSame([(int) $this->student->id], ep_get_referent_students($this->ep, $teacher->id));

        stage_set_student_teachers($this->stage->id, $this->student->id, []);
        $this->assertSame([], ep_get_referent_students($this->ep, $teacher->id));
    }
}
