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
 * Accès simultanés : chaque test rejoue l'entrelacement « une page lit le crédit, un autre
 * intervenant le modifie, puis la page écrit » avec une copie périmée du crédit, comme la page la
 * détient, et vérifie que la décision prise entre-temps n'est pas écrasée.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_update_credit_if
 * @covers     ::ep_validate_credit
 * @covers     ::ep_reject_credit
 * @covers     ::ep_cancel_credit
 * @covers     ::ep_update_declaration
 * @covers     ::ep_register_to_activity
 * @covers     ::ep_registration_problem
 * @covers     ::ep_sync_stage_credits_if_due
 * @covers     ::ep_get_activity_lock
 */
final class concurrency_regression_test extends \advanced_testcase {
    /**
     * Activité, étudiant, responsable, EP du catalogue à deux places et déclaration en attente.
     *
     * @return array [stdClass $ep, stdClass $student, stdClass $owner, stdClass $activity, stdClass $credit]
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 3]);
        $student = $gen->create_and_enrol($course, 'student');
        $owner = $gen->create_and_enrol($course, 'teacher');
        $epgen = $gen->get_plugin_generator('mod_ep');
        $activity = $epgen->create_activity($ep, ['ects' => 2, 'capacity' => 2]);
        ep_set_activity_teachers($activity->id, [$owner->id]);

        $type = ep_get_type_by_code($ep->id, EP_TYPE_ENGAGEMENT);
        $creditid = ep_create_credit($ep, $student->id, $type, [
            'name' => 'Tutorat', 'claimedects' => 1.5, 'studyyear' => 3,
        ]);
        $credit = $this->credit($creditid);
        return [$ep, $student, $owner, $activity, $credit];
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
     * La validation préparée par le responsable n'écrase pas le refus que la DEVE a enregistré
     * entre-temps, et réciproquement : la première décision écrite l'emporte.
     */
    public function test_second_decision_on_the_same_request_is_not_written(): void {
        [, , $owner, , $credit] = $this->fixture();
        $stale = clone $credit;

        $this->assertTrue(ep_reject_credit(clone $credit, get_admin()->id, 'Hors périmètre', 'deve'));
        $this->assertFalse(ep_validate_credit($stale, $owner->id, 1.5, 'Très bien'));

        $after = $this->credit($credit->id);
        $this->assertSame(EP_STATUS_REJECTED, (int) $after->status);
        $this->assertSame('Hors périmètre', $after->validatorcomment);
        $this->assertSame(0.0, (float) $after->retainedects);

        // Un second refus sur une demande déjà refusée n'est pas écrit non plus.
        $this->assertFalse(ep_reject_credit(clone $stale, $owner->id, 'Autre motif'));
        $this->assertSame('Hors périmètre', $this->credit($credit->id)->validatorcomment);
    }

    /**
     * L'étudiant ne retire pas une demande que le responsable vient de valider ; la DEVE, elle,
     * peut retirer un crédit validé, mais pas un crédit déjà annulé (l'auteur et le motif du
     * premier retrait sont conservés).
     */
    public function test_cancellation_depends_on_the_role_and_the_current_state(): void {
        [, $student, $owner, , $credit] = $this->fixture();
        $stale = clone $credit;

        $this->assertTrue(ep_validate_credit(clone $credit, $owner->id, 1.5));
        $this->assertFalse(ep_cancel_credit($stale, $student->id, 'Je retire', 'student'));
        $this->assertSame(EP_STATUS_VALIDATED, (int) $this->credit($credit->id)->status);

        $this->assertTrue(ep_cancel_credit(clone $stale, get_admin()->id, 'Retrait DEVE', 'deve'));
        $this->assertFalse(ep_cancel_credit(clone $stale, $owner->id, 'Second retrait', 'deve'));
        $after = $this->credit($credit->id);
        $this->assertSame(EP_STATUS_CANCELLED, (int) $after->status);
        $this->assertSame('Retrait DEVE', $after->validatorcomment);
        $this->assertSame((int) get_admin()->id, (int) $after->validatedby);
    }

    /**
     * La modification d'une déclaration par l'étudiant n'est pas enregistrée si elle a été tranchée
     * pendant qu'il la modifiait.
     */
    public function test_edit_is_dropped_once_the_request_is_decided(): void {
        [, , $owner, , $credit] = $this->fixture();
        $stale = clone $credit;
        $stale->name = 'Tutorat modifié';
        $stale->claimedects = 3;

        $this->assertTrue(ep_validate_credit(clone $credit, $owner->id, 1.5));
        $this->assertFalse(ep_update_declaration($stale));
        $after = $this->credit($credit->id);
        $this->assertSame('Tutorat', $after->name);
        $this->assertSame(1.5, (float) $after->claimedects);

        // Sur une demande encore en attente, la modification passe.
        $fresh = $this->fixture()[4];
        $fresh->name = 'Tutorat modifié';
        $this->assertTrue(ep_update_declaration($fresh));
        $this->assertSame('Tutorat modifié', $this->credit($fresh->id)->name);
    }

    /**
     * Les crédits automatiques ne se valident, ne se refusent et ne se retirent pas à la main :
     * la condition d'écriture les exclut, quel que soit l'état lu.
     */
    public function test_automatic_credits_are_never_decided_by_hand(): void {
        global $DB;
        [$ep, $student, $owner] = $this->fixture();
        $type = ep_get_type_by_code($ep->id, EP_TYPE_STAGE);
        $creditid = ep_create_credit($ep, $student->id, $type, [
            'name' => 'Stage', 'claimedects' => 2, 'source' => EP_SOURCE_STAGE, 'sourceref' => 42,
        ]);
        // Un crédit automatique mis artificiellement en attente ne doit pas non plus être décidé.
        $DB->set_field('ep_credit', 'status', EP_STATUS_PENDING, ['id' => $creditid]);
        $credit = $this->credit($creditid);

        $this->assertFalse(ep_validate_credit(clone $credit, $owner->id, 2));
        $this->assertFalse(ep_reject_credit(clone $credit, $owner->id, 'Non'));
        $this->assertFalse(ep_cancel_credit(clone $credit, get_admin()->id, 'Non', 'deve'));
        $this->assertSame(EP_STATUS_PENDING, (int) $this->credit($creditid)->status);
    }

    /**
     * Les places sont recomptées sous le verrou à l'inscription : la dernière place n'est donnée
     * qu'une fois, même si la page d'un second étudiant l'avait vue libre ; un étudiant déjà
     * inscrit ne l'est pas deux fois ; une place libérée par un retrait est reprise.
     */
    public function test_registration_recounts_places_under_the_lock(): void {
        [$ep, $student, , $activity] = $this->fixture();
        $gen = $this->getDataGenerator();
        $second = $gen->create_user();
        $third = $gen->create_user();

        // Les trois pages ont vu deux places libres.
        $this->assertNull(ep_registration_problem($activity, ep_get_type_by_code($ep->id, EP_TYPE_ACADEMIC), $third->id));

        $first = ep_register_to_activity($ep, $activity, $student->id, 3);
        $this->assertNull($first->problem);
        $this->assertNull(ep_register_to_activity($ep, $activity, $second->id, 3)->problem);

        $late = ep_register_to_activity($ep, $activity, $third->id, 3);
        $this->assertSame('errornoplaceleft', $late->problem);
        $this->assertSame(0, $late->creditid);
        $this->assertSame(2, ep_get_activity_taken_places($activity->id));

        $twice = ep_register_to_activity($ep, $activity, $student->id, 3);
        $this->assertSame('erroralreadyregistered', $twice->problem);

        $this->assertTrue(ep_cancel_credit($this->credit($first->creditid), $student->id, 'Je retire', 'student'));
        $this->assertNull(ep_register_to_activity($ep, $activity, $third->id, 3)->problem);
        $this->assertSame(2, ep_get_activity_taken_places($activity->id));
    }

    /**
     * Un EP fermé entre l'affichage et la confirmation refuse l'inscription, et une année hors de
     * la plage de l'EP aussi.
     */
    public function test_registration_rechecks_closure_and_year(): void {
        global $DB;
        [$ep, $student, , $activity] = $this->fixture();

        $DB->set_field('ep_activity', 'minstudyyear', 4, ['id' => $activity->id]);
        $this->assertSame('errorwrongyear', ep_register_to_activity($ep, $activity, $student->id, 3)->problem);

        $DB->set_field('ep_activity', 'state', EP_ACTIVITY_CLOSED, ['id' => $activity->id]);
        $this->assertSame('errorregisterclosed', ep_register_to_activity($ep, $activity, $student->id, 4)->problem);
        $this->assertSame(0, ep_get_activity_taken_places($activity->id));
    }

    /**
     * La synchronisation d'opportunité relit la date de dernière synchronisation sous le verrou :
     * une instance que deux pages tiennent pour périmée n'est synchronisée qu'une fois. (Le cas
     * du verrou tenu par une autre requête ne se simule pas dans un seul processus, les verrous
     * étant réentrants : il est couvert par le banc de concurrence.)
     */
    public function test_opportunistic_sync_runs_once(): void {
        global $DB;
        [$ep] = $this->fixture();
        $ep->timesynced = 0;
        $stalecopy = clone $ep;

        ep_sync_stage_credits_if_due($ep);
        $first = (int) $DB->get_field('ep', 'timesynced', ['id' => $ep->id]);
        $this->assertGreaterThan(0, $first);

        // La seconde page détient encore timesynced = 0 : elle relit la base et ne relance rien.
        $DB->set_field('ep', 'timesynced', $first - 10, ['id' => $ep->id]);
        ep_sync_stage_credits_if_due($stalecopy);
        $this->assertSame($first - 10, (int) $DB->get_field('ep', 'timesynced', ['id' => $ep->id]));
        $this->assertSame($first - 10, (int) $stalecopy->timesynced);
    }
}
