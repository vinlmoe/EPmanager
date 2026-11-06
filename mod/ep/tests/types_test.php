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
require_once($CFG->dirroot . '/mod/ep/db/upgrade.php');

/**
 * Types paramétrés : circuits de validation à étapes, notation, historique des décisions,
 * gestion des types par la DEVE, migration des anciens types.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_flow_steps
 * @covers     ::ep_normalise_flow
 * @covers     ::ep_flow_options
 * @covers     ::ep_type_record
 * @covers     ::ep_save_type
 * @covers     ::ep_suggest_type_code
 * @covers     ::ep_type_is_deletable
 * @covers     ::ep_move_type
 * @covers     ::ep_create_credit
 * @covers     ::ep_validate_credit
 * @covers     ::ep_reject_credit
 * @covers     ::ep_grade_credit
 * @covers     ::ep_credit_grading
 * @covers     ::ep_add_credit_decision
 * @covers     ::ep_get_credit_decisions
 * @covers     ::ep_can_validate_credit
 * @covers     ::ep_rights_can_validate
 * @covers     ::ep_rights_deciding_role
 * @covers     ::ep_get_credits_awaiting
 * @covers     ::ep_legacy_type_parameters
 */
final class types_test extends \advanced_testcase {
    /** @var \stdClass Instance. */
    private $ep;

    /** @var \context_module */
    private $context;

    /** @var \stdClass Étudiant. */
    private $student;

    /** @var \stdClass Enseignant référent de l'étudiant et responsable de l'EP du catalogue. */
    private $teacher;

    /** @var \stdClass Un autre enseignant, sans périmètre. */
    private $other;

    /** @var \stdClass EP du catalogue. */
    private $activity;

    /** @var \mod_ep_generator */
    private $gen;

    /**
     * Un cours avec une activité « Gestion des stages » (pour l'attribution du référent), un
     * étudiant, deux enseignants, un EP du catalogue dont le premier est responsable.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course->id]);
        $this->ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 2]);
        $this->context = \context_module::instance($this->ep->cmid);
        $this->student = $gen->create_and_enrol($course, 'student');
        $this->teacher = $gen->create_and_enrol($course, 'teacher');
        $this->other = $gen->create_and_enrol($course, 'teacher');
        $gen->get_plugin_generator('mod_stage')->assign_teacher($stage, $this->student->id, $this->teacher->id);

        $this->gen = $gen->get_plugin_generator('mod_ep');
        $this->activity = $this->gen->create_activity($this->ep, ['name' => 'Échographie', 'ects' => 2]);
        ep_set_activity_teachers($this->activity->id, [$this->teacher->id]);
    }

    /**
     * Relit un crédit.
     *
     * @param int $creditid
     * @return \stdClass
     */
    private function credit(int $creditid): \stdClass {
        global $DB;
        return $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
    }

    /**
     * Un circuit se lit, se nettoie et se met en accord avec le mode d'acquisition.
     */
    public function test_flow_parsing_and_normalisation(): void {
        $this->assertSame(['owner', 'deve'], ep_flow_steps(' owner , deve,owner,bogus'));
        $this->assertSame([], ep_flow_steps(''));
        $this->assertSame([], ep_normalise_flow('owner,deve', EP_ACQ_STAGE));
        $this->assertSame([], ep_normalise_flow('referent', EP_ACQ_DEVE));
        $this->assertSame(['referent', 'deve'], ep_normalise_flow('owner,referent,deve', EP_ACQ_DECLARATION));
        $this->assertSame(['owner', 'referent', 'deve'], ep_normalise_flow('owner,referent,deve', EP_ACQ_CATALOG));

        $this->assertSame([''], array_keys(ep_flow_options(EP_ACQ_STAGE)));
        $this->assertArrayHasKey('owner,deve', ep_flow_options(EP_ACQ_CATALOG));
        $this->assertArrayNotHasKey('owner', ep_flow_options(EP_ACQ_DECLARATION));
        $this->assertArrayHasKey('referent,deve', ep_flow_options(EP_ACQ_DECLARATION));
    }

    /**
     * Circuit « responsable puis DEVE » : la validation du responsable fait passer la demande à
     * la DEVE, qui seule la valide ; le responsable ne peut plus rien à la seconde étape, le
     * référent n'a jamais la main sur une inscription, et l'historique garde les deux décisions.
     */
    public function test_two_step_flow_owner_then_deve(): void {
        $academic = ep_get_type_by_code($this->ep->id, EP_TYPE_ACADEMIC);
        $this->gen->configure_type($this->ep, EP_TYPE_ACADEMIC, ['validationflow' => 'owner,deve']);
        $creditid = ep_register_to_activity($this->ep, $this->activity, $this->student->id, 2)->creditid;
        $credit = $this->credit($creditid);
        $this->assertSame(EP_ROLE_OWNER, $credit->pendingrole);
        $this->assertSame(0, (int) $credit->step);

        // Le référent (qui n'est pas responsable) et un enseignant quelconque ne peuvent rien.
        $teacherrights = ep_get_user_rights($this->ep, $this->context, $this->teacher->id);
        $otherrights = ep_get_user_rights($this->ep, $this->context, $this->other->id);
        $this->assertTrue(ep_rights_can_validate($teacherrights, $credit));
        $this->assertFalse(ep_rights_can_validate($otherrights, $credit));
        $this->assertSame(EP_ROLE_OWNER, ep_rights_deciding_role($teacherrights, $credit));
        $this->assertCount(1, ep_get_credits_awaiting($this->ep, $teacherrights));

        // Étape 1 : le responsable valide, la demande reste en attente, de la DEVE.
        $this->assertTrue(ep_validate_credit($credit, $this->teacher->id, 2, 'Assidu', EP_ROLE_OWNER));
        $credit = $this->credit($creditid);
        $this->assertSame(EP_STATUS_PENDING, (int) $credit->status);
        $this->assertSame(EP_ROLE_DEVE, $credit->pendingrole);
        $this->assertSame(1, (int) $credit->step);
        $this->assertEquals(0, ep_get_student_progress($this->ep, $this->student->id)->totalretained);
        $this->assertFalse(ep_rights_can_validate($teacherrights, $credit));
        $this->assertCount(0, ep_get_credits_awaiting($this->ep, $teacherrights));
        $this->assertFalse(ep_validate_credit(clone $credit, $this->teacher->id, 2, '', EP_ROLE_OWNER));

        // Étape 2 : la DEVE valide définitivement.
        $deverights = ep_get_user_rights($this->ep, $this->context, get_admin()->id);
        $this->assertTrue(ep_rights_can_validate($deverights, $credit));
        $this->assertTrue(ep_validate_credit($credit, get_admin()->id, 1.5, 'Partiel', EP_ROLE_DEVE));
        $credit = $this->credit($creditid);
        $this->assertSame(EP_STATUS_VALIDATED, (int) $credit->status);
        $this->assertSame('', $credit->pendingrole);
        $this->assertSame(1.5, (float) $credit->retainedects);
        $this->assertSame((int) get_admin()->id, (int) $credit->validatedby);

        $decisions = array_values(ep_get_credit_decisions($creditid));
        $this->assertCount(2, $decisions);
        $this->assertSame([EP_ROLE_OWNER, EP_ROLE_DEVE], [$decisions[0]->role, $decisions[1]->role]);
        $this->assertSame([0, 1], [(int) $decisions[0]->step, (int) $decisions[1]->step]);
        $this->assertSame('validated', $decisions[1]->decision);
        $this->assertSame('Assidu', $decisions[0]->comment);
    }

    /**
     * La DEVE tranche définitivement à n'importe quelle étape, et un refus à la première étape
     * est final.
     */
    public function test_deve_decides_at_any_step_and_rejection_is_final(): void {
        $this->gen->configure_type($this->ep, EP_TYPE_ACADEMIC, ['validationflow' => 'owner,referent,deve']);
        $first = $this->credit(ep_register_to_activity($this->ep, $this->activity, $this->student->id, 2)->creditid);
        $this->assertTrue(ep_validate_credit($first, get_admin()->id, 2, 'Direct', EP_ROLE_DEVE));
        $this->assertSame(EP_STATUS_VALIDATED, (int) $this->credit($first->id)->status);
        $this->assertCount(1, ep_get_credit_decisions($first->id));

        $this->assertTrue(ep_cancel_credit($this->credit($first->id), get_admin()->id, 'Erreur', EP_ROLE_DEVE));
        $second = $this->credit(ep_register_to_activity($this->ep, $this->activity, $this->student->id, 2)->creditid);
        $this->assertTrue(ep_reject_credit($second, $this->teacher->id, 'Absent', EP_ROLE_OWNER));
        $after = $this->credit($second->id);
        $this->assertSame(EP_STATUS_REJECTED, (int) $after->status);
        $this->assertSame('', $after->pendingrole);
        $decisions = ep_get_credit_decisions($second->id);
        $this->assertSame('rejected', reset($decisions)->decision);
    }

    /**
     * Sans circuit, un crédit naît validé ; avec le circuit « référent », la déclaration relève
     * du référent de l'étudiant, puis d'un retrait possible par l'étudiant tant qu'elle attend.
     */
    public function test_no_flow_validates_outright(): void {
        $auto = $this->gen->create_type($this->ep, [
            'name' => 'Certification', 'acquisition' => EP_ACQ_DECLARATION, 'validationflow' => '',
        ]);
        $this->assertTrue(ep_type_is_automatic($auto));
        $creditid = ep_create_credit($this->ep, $this->student->id, $auto, ['name' => 'TOEIC', 'claimedects' => 1]);
        $credit = $this->credit($creditid);
        $this->assertSame(EP_STATUS_VALIDATED, (int) $credit->status);
        $this->assertSame(1.0, (float) $credit->retainedects);
        $this->assertNull($credit->validatedby);

        $referent = ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT);
        $declaredid = ep_create_credit($this->ep, $this->student->id, $referent, ['name' => 'BDE', 'claimedects' => 1]);
        $declared = $this->credit($declaredid);
        $this->assertSame(EP_ROLE_REFERENT, $declared->pendingrole);
        $this->assertTrue(ep_can_validate_credit($this->ep, $declared, $this->context, $this->teacher->id));
        $this->assertFalse(ep_can_validate_credit($this->ep, $declared, $this->context, $this->other->id));
        $this->assertTrue(ep_student_can_cancel($declared));
    }

    /**
     * Crédit noté : la note décide, au seuil près, et reste enregistrée ; une note hors barème
     * n'est pas acceptée ; le barème de l'EP du catalogue prime sur celui du type.
     */
    public function test_graded_credits(): void {
        global $DB;
        $this->gen->configure_type($this->ep, EP_TYPE_ACADEMIC, [
            'grading' => EP_GRADING_GRADE, 'grademax' => 20, 'gradepass' => 10,
        ]);
        $passed = $this->credit(ep_register_to_activity($this->ep, $this->activity, $this->student->id, 2)->creditid);
        $this->assertFalse(ep_grade_credit(clone $passed, $this->teacher->id, 21));
        $this->assertFalse(ep_grade_credit(clone $passed, $this->teacher->id, -1));
        $this->assertTrue(ep_grade_credit($passed, $this->teacher->id, 12.5, 'Bien'));
        $passed = $this->credit($passed->id);
        $this->assertSame(EP_STATUS_VALIDATED, (int) $passed->status);
        $this->assertSame(12.5, (float) $passed->grade);
        $this->assertSame(2.0, (float) $passed->retainedects);

        ep_cancel_credit($passed, get_admin()->id, 'Rejoué', EP_ROLE_DEVE);
        $failed = $this->credit(ep_register_to_activity($this->ep, $this->activity, $this->student->id, 2)->creditid);
        $this->assertTrue(ep_grade_credit($failed, $this->teacher->id, 9.75, 'Insuffisant'));
        $failed = $this->credit($failed->id);
        $this->assertSame(EP_STATUS_REJECTED, (int) $failed->status);
        $this->assertSame(9.75, (float) $failed->grade);
        $this->assertSame(0.0, (float) $failed->retainedects);
        $decisions = ep_get_credit_decisions($failed->id);
        $decision = end($decisions);
        $this->assertSame(9.75, (float) $decision->grade);

        // Sans barème propre à l'EP du catalogue, c'est celui du type qui s'applique.
        $grading = ep_credit_grading($failed);
        $this->assertSame(EP_GRADING_GRADE, $grading->grading);
        $this->assertSame(10.0, $grading->gradepass);
    }

    /**
     * Enregistrement d'un type : code déduit du libellé et rendu unique, circuit mis en accord
     * avec l'acquisition, bornes des nombres ; suppression refusée dès qu'il est utilisé ;
     * déplacement dans l'ordre.
     */
    public function test_type_administration(): void {
        global $DB;
        $id = ep_save_type($this->ep->id, (object) [
            'name' => 'Académique externe', 'acquisition' => EP_ACQ_DECLARATION,
            'validationflow' => 'owner,deve', 'grademax' => 10, 'gradepass' => 12, 'maxects' => -3,
        ]);
        $type = $DB->get_record('ep_type', ['id' => $id]);
        $this->assertSame('academiqueexterne', $type->code);
        $this->assertSame('deve', $type->validationflow);
        $this->assertSame(10.0, (float) $type->gradepass);
        $this->assertSame(0.0, (float) $type->maxects);
        $this->assertSame(70, (int) $type->sortorder);

        $second = ep_save_type($this->ep->id, (object) ['name' => 'Académique externe']);
        $this->assertSame('academiqueexterne2', $DB->get_field('ep_type', 'code', ['id' => $second]));

        // Modification : le code est conservé, le libellé change.
        ep_save_type($this->ep->id, (object) ['id' => $id, 'name' => 'Académique hors école', 'code' => 'academiqueexterne']);
        $this->assertSame('academiqueexterne', $DB->get_field('ep_type', 'code', ['id' => $id]));
        $this->assertSame('Académique hors école', $DB->get_field('ep_type', 'name', ['id' => $id]));

        $this->assertTrue(ep_type_is_deletable($id));
        $academic = ep_get_type_by_code($this->ep->id, EP_TYPE_ACADEMIC);
        $this->assertFalse(ep_type_is_deletable($academic->id));

        $before = array_keys(ep_get_types($this->ep->id));
        ep_move_type($DB->get_record('ep_type', ['id' => $second]), -1);
        $after = array_keys(ep_get_types($this->ep->id));
        $this->assertSame($before[6], $after[7]);
        $this->assertSame($before[7], $after[6]);
        ep_move_type($DB->get_record('ep_type', ['id' => $before[0]]), -1);
        $this->assertSame($before[0], array_keys(ep_get_types($this->ep->id))[0]);
    }

    /**
     * Les anciens types (drapeaux catalog et autovalidate) se convertissent à l'identique de leur
     * comportement.
     */
    public function test_legacy_type_parameters(): void {
        $stage = ep_legacy_type_parameters((object) ['code' => 'stage', 'catalog' => 0, 'autovalidate' => 1]);
        $this->assertSame(EP_ACQ_STAGE, $stage['acquisition']);
        $this->assertSame('', $stage['validationflow']);
        $academic = ep_legacy_type_parameters((object) ['code' => 'academic', 'catalog' => 1, 'autovalidate' => 0]);
        $this->assertSame(EP_ACQ_CATALOG, $academic['acquisition']);
        $this->assertSame(EP_ROLE_OWNER, $academic['validationflow']);
        $sport = ep_legacy_type_parameters((object) ['code' => 'sport', 'catalog' => 0, 'autovalidate' => 0]);
        $this->assertSame(EP_ACQ_DECLARATION, $sport['acquisition']);
        $this->assertSame(EP_ROLE_REFERENT, $sport['validationflow']);
        $this->assertSame(EP_EVIDENCE_OPTIONAL, $sport['evidence']);
    }

    /**
     * Deux types alimentés par les stages se partagent les stages selon leur portée : les stages
     * à l'étranger vont au premier type qui les couvre, les autres au type général.
     */
    public function test_stage_types_scope(): void {
        global $DB;
        $course = get_course($this->ep->course);
        $stage = $DB->get_record('stage', ['course' => $course->id], '*', MUST_EXIST);
        $sgen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $sgen->create_theme($stage, ['name' => 'Clinique']);
        $abroadtype = $this->gen->create_type($this->ep, [
            'name' => 'Stage à l\'étranger', 'acquisition' => EP_ACQ_STAGE, 'ectsperday' => 0.5,
            'stagescope' => 'abroad', 'sortorder' => 5,
        ]);
        $this->gen->configure_type($this->ep, EP_TYPE_STAGE, ['ectsperday' => 0.25]);

        // Deux stages complémentaires validés par la DEVE, l'un en France, l'autre à l'étranger.
        $entries = [];
        foreach ([0, 1] as $abroad) {
            $entry = $sgen->create_entry($stage, $this->student->id, $theme, ['declaredduration' => 4, 'studyyear' => 2]);
            $DB->set_field('stage_entry', 'abroad', $abroad, ['id' => $entry->id]);
            stage_set_entry_stagetype($entry->id, 'complementaire');
            stage_apply_deve_validation($entry, 0, 4);
            $entries[$abroad] = $entry;
        }

        $result = ep_sync_stage_credits($this->ep);
        $this->assertSame(2, $result->created);
        $credits = $DB->get_records('ep_credit', ['epid' => $this->ep->id, 'source' => EP_SOURCE_STAGE], 'sourceref');
        $bytype = [];
        foreach ($credits as $credit) {
            $bytype[(int) $credit->typeid] = (float) $credit->retainedects;
        }
        $this->assertSame(2.0, $bytype[(int) $abroadtype->id]);
        $this->assertSame(1.0, $bytype[(int) ep_get_type_by_code($this->ep->id, EP_TYPE_STAGE)->id]);
    }
}
