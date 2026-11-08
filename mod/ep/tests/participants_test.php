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
 * Catalogue et participants : état et période d'inscription d'un EP, prérequis, ajout et retrait
 * de participants, saisie des notes en lot, correction par la DEVE, clôture, import des notes.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_activity_registration_open
 * @covers     ::ep_activity_registration_window
 * @covers     ::ep_set_registration_window
 * @covers     ::ep_activity_is_listed
 * @covers     ::ep_get_activities
 * @covers     ::ep_registration_problem
 * @covers     ::ep_get_missing_prerequisites
 * @covers     ::ep_set_activity_prerequisites
 * @covers     ::ep_save_activity
 * @covers     ::ep_activity_code_used
 * @covers     ::ep_activity_grading
 * @covers     ::ep_add_participants
 * @covers     ::ep_cancel_credit
 * @covers     ::ep_apply_participant_decisions
 * @covers     ::ep_hold_notifications
 * @covers     ::ep_correct_grade
 * @covers     ::ep_close_activity
 * @covers     ::ep_rights_can_manage_participants
 * @covers     ::ep_get_activities_status_counts
 * @covers     \mod_ep\local\grades_importer
 */
final class participants_test extends \advanced_testcase {
    /** @var \stdClass Instance. */
    private $ep;

    /** @var \context_module */
    private $context;

    /** @var \stdClass[] Étudiants. */
    private $students = [];

    /** @var \stdClass Responsable de l'EP. */
    private $owner;

    /** @var \stdClass EP du catalogue, deux places. */
    private $activity;

    /** @var \mod_ep_generator */
    private $gen;

    /**
     * Quatre étudiants, un responsable, un EP du catalogue à deux places.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 3]);
        $this->context = \context_module::instance($this->ep->cmid);
        foreach (['Alice', 'Bruno', 'Chloé', 'David'] as $i => $name) {
            $this->students[$name] = $gen->create_and_enrol($course, 'student', [
                'firstname' => $name, 'lastname' => 'Nom' . $i, 'idnumber' => 'E' . $i,
                'email' => strtolower(\core_text::specialtoascii($name)) . '@example.com',
            ]);
        }
        $this->owner = $gen->create_and_enrol($course, 'teacher');
        $this->gen = $gen->get_plugin_generator('mod_ep');
        $this->activity = $this->gen->create_activity($this->ep, ['name' => 'Échographie', 'ects' => 3, 'capacity' => 2]);
        ep_set_activity_teachers($this->activity->id, [$this->owner->id]);
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
     * Droits d'un utilisateur dans l'instance.
     *
     * @param \stdClass $user
     * @return \stdClass
     */
    private function rights(\stdClass $user): \stdClass {
        return ep_get_user_rights($this->ep, $this->context, $user->id);
    }

    /**
     * L'état et la période d'inscription décident de l'ouverture ; les EP archivés, en brouillon
     * ou proposés ne figurent pas au catalogue des étudiants.
     */
    public function test_state_and_registration_window(): void {
        $now = time();
        $activity = clone $this->activity;
        $this->assertTrue(ep_activity_registration_open($activity, $now));

        $activity->registrationstart = $now + 3600;
        $this->assertFalse(ep_activity_registration_open($activity, $now));
        $activity->registrationstart = $now - 3600;
        $activity->registrationend = $now - 60;
        $this->assertFalse(ep_activity_registration_open($activity, $now));
        $activity->registrationend = $now + 60;
        $this->assertTrue(ep_activity_registration_open($activity, $now));

        $activity->state = EP_ACTIVITY_CLOSED;
        $this->assertFalse(ep_activity_registration_open($activity, $now));
        $this->assertTrue(ep_activity_is_listed($activity));
        $activity->state = EP_ACTIVITY_ARCHIVED;
        $this->assertFalse(ep_activity_is_listed($activity));

        $this->gen->create_activity($this->ep, ['state' => EP_ACTIVITY_ARCHIVED]);
        $this->gen->create_activity($this->ep, ['state' => EP_ACTIVITY_DRAFT]);
        $this->gen->create_activity($this->ep, ['state' => EP_ACTIVITY_CLOSED]);
        $this->assertCount(4, ep_get_activities($this->ep->id));
        $this->assertCount(2, ep_get_activities($this->ep->id, true));

        // Inscription refusée hors période, même sous le verrou.
        global $DB;
        $DB->set_field('ep_activity', 'registrationend', $now - 60, ['id' => $this->activity->id]);
        $result = ep_register_to_activity($this->ep, $this->activity, $this->students['Alice']->id, 3);
        $this->assertSame('errorregisterclosed', $result->problem);
    }

    /**
     * Période d'inscription fixée par la DEVE pour tout le catalogue : un EP sans période propre la
     * suit, un EP qui a la sienne la garde, sauf alignement demandé. Hors période, l'étudiant ne
     * s'inscrit plus, mais le responsable ou la DEVE peuvent encore l'ajouter.
     */
    public function test_catalog_registration_window(): void {
        global $DB;
        $now = time();
        $own = $this->gen->create_activity($this->ep, ['registrationstart' => $now - DAYSECS,
            'registrationend' => $now + DAYSECS]);
        $ep = $DB->get_record('ep', ['id' => $this->ep->id]);
        ep_set_registration_window($ep, $now - 2 * DAYSECS, $now - DAYSECS);

        $activity = $DB->get_record('ep_activity', ['id' => $this->activity->id]);
        $this->assertSame([$now - 2 * DAYSECS, $now - DAYSECS], ep_activity_registration_window($activity));
        $this->assertFalse(ep_activity_registration_open($activity, $now));
        $this->assertTrue(ep_activity_registration_open($own, $now));
        $this->assertNotSame('', ep_activity_registration_label($activity));

        $alice = $this->students['Alice']->id;
        $this->assertSame('errorregisterclosed', ep_register_to_activity($ep, $activity, $alice, 3)->problem);
        $added = ep_add_participants($ep, $activity, [$alice], $this->owner->id);
        $this->assertCount(1, $added->added);

        try {
            ep_set_registration_window($ep, $now, $now - 1);
            $this->fail('Une période inversée devrait être refusée.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorregistrationrange', $e->errorcode);
        }

        ep_set_registration_window($ep, 0, $now - DAYSECS, true);
        $own = $DB->get_record('ep_activity', ['id' => $own->id]);
        $this->assertSame([0, 0], [(int) $own->registrationstart, (int) $own->registrationend]);
        $this->assertFalse(ep_activity_registration_open($own, $now));

        ep_set_registration_window($ep, 0, 0);
        $this->assertTrue(ep_activity_registration_open($own, $now));
    }

    /**
     * Prérequis : EP exigés et ECTS déjà retenus ; bloquants, ils empêchent l'inscription ; non
     * bloquants, ils sont seulement signalés.
     */
    public function test_prerequisites(): void {
        global $DB;
        $alice = $this->students['Alice'];
        $intro = $this->gen->create_activity($this->ep, ['name' => 'Introduction', 'ects' => 1]);
        ep_set_activity_prerequisites($this->activity, [$intro->id, $this->activity->id, 999999]);
        $this->assertSame([(int) $intro->id], ep_get_activity_prerequisites($this->activity->id));
        $DB->set_field('ep_activity', 'minects', 2, ['id' => $this->activity->id]);
        $activity = $DB->get_record('ep_activity', ['id' => $this->activity->id]);

        $missing = ep_get_missing_prerequisites($this->ep, $activity, $alice->id);
        $this->assertCount(2, $missing);
        $this->assertStringContainsString('Introduction', $missing[0]);
        $this->assertSame('errorprerequisites', ep_register_to_activity($this->ep, $activity, $alice->id, 3)->problem);

        // Introduction validée (1 ECTS) : reste le minimum d'ECTS.
        $introcredit = ep_register_to_activity($this->ep, $intro, $alice->id, 3)->creditid;
        ep_validate_credit($this->credit($introcredit), get_admin()->id, 1, '', EP_ROLE_DEVE);
        $this->assertCount(1, ep_get_missing_prerequisites($this->ep, $activity, $alice->id));

        // Prérequis non bloquants : l'inscription passe.
        $DB->set_field('ep_activity', 'prereqblocking', 0, ['id' => $activity->id]);
        $activity->prereqblocking = 0;
        $this->assertNull(ep_register_to_activity($this->ep, $activity, $alice->id, 3)->problem);

        // Bruno a les ECTS requis mais pas l'EP exigé : seul ce dernier manque.
        $this->gen->create_validated_credit($this->ep, $this->students['Bruno']->id, EP_TYPE_SPORT, 2, 3);
        $brunomissing = ep_get_missing_prerequisites($this->ep, $activity, $this->students['Bruno']->id);
        $this->assertCount(1, $brunomissing);
        $this->assertStringContainsString('Introduction', $brunomissing[0]);
    }

    /**
     * Enregistrement d'un EP : code unique, bornes, prérequis ; barème propre ou celui du type.
     */
    public function test_save_activity_and_grading(): void {
        global $DB;
        $id = ep_save_activity($this->ep, (object) [
            'typeid' => $this->activity->typeid, 'name' => ' Chirurgie ', 'code' => 'EP-CHIR', 'ects' => 2.555,
            'capacity' => -4, 'state' => 'bogus', 'grading' => EP_GRADING_GRADE, 'grademax' => 10, 'gradepass' => 12,
            'prerequisites' => [$this->activity->id],
        ]);
        $saved = $DB->get_record('ep_activity', ['id' => $id]);
        $this->assertSame('Chirurgie', $saved->name);
        $this->assertSame(2.56, (float) $saved->ects);
        $this->assertSame(0, (int) $saved->capacity);
        $this->assertSame(EP_ACTIVITY_PUBLISHED, $saved->state);
        $this->assertSame(10.0, (float) $saved->gradepass);
        $this->assertSame([(int) $this->activity->id], ep_get_activity_prerequisites($id));
        $this->assertTrue(ep_activity_code_used($this->ep->id, 'EP-CHIR'));
        $this->assertFalse(ep_activity_code_used($this->ep->id, 'EP-CHIR', $id));

        $grading = ep_activity_grading($saved);
        $this->assertSame(EP_GRADING_GRADE, $grading->grading);
        $this->assertSame(10.0, $grading->grademax);
        $this->assertSame(EP_GRADING_NONE, ep_activity_grading($this->activity)->grading);

        // Modification partielle : les champs absents sont conservés.
        ep_save_activity($this->ep, (object) ['id' => $id, 'state' => EP_ACTIVITY_CLOSED]);
        $after = $DB->get_record('ep_activity', ['id' => $id]);
        $this->assertSame('EP-CHIR', $after->code);
        $this->assertSame(EP_ACTIVITY_CLOSED, $after->state);
        $this->assertSame([(int) $this->activity->id], ep_get_activity_prerequisites($id));
    }

    /**
     * Ajout par le responsable ou la DEVE : pas de doublon, places respectées, la DEVE seule les
     * dépasse ; l'auteur de l'ajout est tracé.
     */
    public function test_add_participants(): void {
        $ids = array_map(fn($u) => (int) $u->id, array_values($this->students));
        ep_register_to_activity($this->ep, $this->activity, $ids[0], 3);

        $result = ep_add_participants($this->ep, $this->activity, $ids, $this->owner->id);
        $this->assertSame([$ids[1]], array_keys($result->added));
        $this->assertSame('erroralreadyregistered', $result->skipped[$ids[0]]);
        $this->assertSame('errornoplaceleft', $result->skipped[$ids[2]]);
        $added = $this->credit($result->added[$ids[1]]);
        $this->assertSame((int) $this->owner->id, (int) $added->addedby);
        $this->assertSame(EP_ROLE_OWNER, $added->pendingrole);

        $forced = ep_add_participants($this->ep, $this->activity, [$ids[2], $ids[3]], get_admin()->id, true);
        $this->assertCount(2, $forced->added);
        $this->assertSame(4, ep_get_activity_taken_places($this->activity->id));
        $counts = ep_get_activities_status_counts($this->ep->id);
        $this->assertSame(4, $counts[$this->activity->id][EP_STATUS_PENDING]);
    }

    /**
     * Le responsable retire un participant en attente, pas un participant déjà validé ; la DEVE
     * retire l'un comme l'autre.
     */
    public function test_withdraw_participant(): void {
        $alice = $this->students['Alice'];
        $creditid = ep_register_to_activity($this->ep, $this->activity, $alice->id, 3)->creditid;
        $this->assertTrue(ep_cancel_credit($this->credit($creditid), $this->owner->id, 'Absent', EP_ROLE_OWNER));
        $this->assertSame(EP_STATUS_CANCELLED, (int) $this->credit($creditid)->status);

        $second = ep_register_to_activity($this->ep, $this->activity, $alice->id, 3)->creditid;
        ep_validate_credit($this->credit($second), $this->owner->id, 3, '', EP_ROLE_OWNER);
        $this->assertFalse(ep_cancel_credit($this->credit($second), $this->owner->id, 'Erreur', EP_ROLE_OWNER));
        $this->assertTrue(ep_cancel_credit($this->credit($second), get_admin()->id, 'Erreur', EP_ROLE_DEVE));
    }

    /**
     * Notes en lot sur un EP noté : la note décide au seuil, un enseignant sans droit ne fait rien,
     * une ligne vide est ignorée, une note hors barème aussi ; la DEVE corrige ensuite une note.
     */
    public function test_bulk_grades_and_correction(): void {
        global $DB;
        $DB->set_field('ep_activity', 'grading', EP_GRADING_GRADE, ['id' => $this->activity->id]);
        $activity = $DB->get_record('ep_activity', ['id' => $this->activity->id]);
        $added = ep_add_participants(
            $this->ep,
            $activity,
            array_map(fn($u) => $u->id, $this->students),
            get_admin()->id,
            true
        )->added;
        [$a, $b, $c, $d] = array_values($added);

        $other = $this->getDataGenerator()->create_and_enrol(get_course($this->ep->course), 'teacher');
        $nothing = ep_apply_participant_decisions($this->ep, $activity, [$a => ['grade' => 15]], $this->rights($other));
        $this->assertSame(1, $nothing->ignored);
        $this->assertSame(EP_STATUS_PENDING, (int) $this->credit($a)->status);

        $sink = $this->redirectMessages();
        $result = ep_apply_participant_decisions($this->ep, $activity, [
            $a => ['grade' => 15, 'comment' => 'Bien'],
            $b => ['grade' => 9.5],
            $c => ['grade' => 25],
            $d => ['grade' => null, 'decision' => ''],
        ], $this->rights($this->owner));
        // Les deux étudiants tranchés sont prévenus, une fois le lot enregistré et le verrou rendu.
        $this->assertCount(2, $sink->get_messages());
        $this->assertFalse(ep_hold_notifications(null));
        $sink->close();
        $this->assertSame(1, $result->validated);
        $this->assertSame(1, $result->rejected);
        $this->assertSame(2, $result->ignored);
        $this->assertSame(3.0, (float) $this->credit($a)->retainedects);
        $this->assertSame(9.5, (float) $this->credit($b)->grade);
        $this->assertSame(EP_STATUS_REJECTED, (int) $this->credit($b)->status);

        // Le responsable ne corrige pas une note déjà tranchée ; la DEVE si, et l'historique le dit.
        $owneredit = ep_apply_participant_decisions($this->ep, $activity, [$b => ['grade' => 12]], $this->rights($this->owner));
        $this->assertSame(1, $owneredit->ignored);
        $deve = ep_apply_participant_decisions(
            $this->ep,
            $activity,
            [$b => ['grade' => 12, 'comment' => 'Erreur de saisie']],
            $this->rights(get_admin())
        );
        $this->assertSame(1, $deve->corrected);
        $this->assertSame(EP_STATUS_VALIDATED, (int) $this->credit($b)->status);
        $this->assertSame(3.0, (float) $this->credit($b)->retainedects);
        $decisions = ep_get_credit_decisions($b);
        $this->assertSame('corrected', end($decisions)->decision);
    }

    /**
     * Décisions en lot sur un EP sans note, et clôture qui tranche ce qui reste en attente.
     */
    public function test_bulk_decisions_and_close(): void {
        global $DB;
        $added = ep_add_participants(
            $this->ep,
            $this->activity,
            array_map(fn($u) => $u->id, $this->students),
            get_admin()->id,
            true
        )->added;
        [$a, $b, $c, $d] = array_values($added);
        $owner = $this->rights($this->owner);

        $result = ep_apply_participant_decisions($this->ep, $this->activity, [
            $a => ['decision' => 'validate'],
            $b => ['decision' => 'reject', 'comment' => 'Absences'],
            $c => ['grade' => 12],
        ], $owner);
        $this->assertSame([1, 1, 1], [$result->validated, $result->rejected, $result->ignored]);

        $closed = ep_close_activity($this->ep, $this->activity, $owner, 'validate');
        $this->assertSame([2, 0, 0], [$closed->validated, $closed->rejected, $closed->kept]);
        $this->assertSame(EP_ACTIVITY_CLOSED, $DB->get_field('ep_activity', 'state', ['id' => $this->activity->id]));
        $this->assertSame(EP_STATUS_VALIDATED, (int) $this->credit($d)->status);
        $this->assertSame(get_string('closedactivitycomment', 'mod_ep'), $this->credit($d)->validatorcomment);
    }

    /**
     * Seuls la DEVE et les responsables de l'EP gèrent ses participants.
     */
    public function test_participant_management_rights(): void {
        $other = $this->getDataGenerator()->create_and_enrol(get_course($this->ep->course), 'teacher');
        $this->assertTrue(ep_rights_can_manage_participants($this->rights($this->owner), $this->activity));
        $this->assertTrue(ep_rights_can_manage_participants($this->rights(get_admin()), $this->activity));
        $this->assertFalse(ep_rights_can_manage_participants($this->rights($other), $this->activity));
        $this->assertFalse(ep_rights_can_manage_participants($this->rights($this->students['Alice']), $this->activity));
    }

    /**
     * L'import de notes reconnaît les en-têtes en français, le point-virgule, la virgule décimale,
     * l'adresse ou le numéro d'identification, et signale chaque ligne fautive.
     */
    public function test_grades_importer(): void {
        $added = ep_add_participants(
            $this->ep,
            $this->activity,
            array_map(fn($u) => $u->id, $this->students),
            get_admin()->id,
            true
        )->added;
        $participants = ep_get_participants($this->activity);
        $users = ep_get_credit_users($participants);
        $graded = (object) ['grading' => EP_GRADING_GRADE, 'grademax' => 20.0, 'gradepass' => 10.0];

        $csv = "\xEF\xBB\xBFNom;Adresse électronique;Note;Commentaire\n"
            . "Alice;ALICE@example.com;12,5;Bien\n"
            . "Bruno;inconnu@example.com;10;\n"
            . "Chloé;chloe@example.com;21;\n"
            . "Alice;alice@example.com;13;\n"
            . "\n";
        $read = local\grades_importer::read($csv, $participants, $users, $graded);
        $this->assertCount(1, $read->rows);
        $this->assertSame(12.5, $read->rows[0]->grade);
        $this->assertSame('Bien', $read->rows[0]->comment);
        $this->assertSame($added[$this->students['Alice']->id], $read->rows[0]->creditid);
        $this->assertCount(3, $read->errors);

        $ungraded = (object) ['grading' => EP_GRADING_NONE, 'grademax' => 20.0, 'gradepass' => 10.0];
        $csv = "idnumber,Décision\nE1,Validé\nE2,non validé\nE3,peut-être\n";
        $read = local\grades_importer::read($csv, $participants, $users, $ungraded);
        $this->assertSame(['validate', 'reject'], array_map(fn($row) => $row->decision, $read->rows));
        $this->assertCount(1, $read->errors);

        $nocolumn = local\grades_importer::read("Nom;Note\nAlice;12\n", $participants, $users, $graded);
        $this->assertSame([], $nocolumn->rows);
        $this->assertSame([get_string('importgradesnostudentcolumn', 'mod_ep')], $nocolumn->errors);

        // L'intitulé français de Moodle pour l'adresse (« Adresse de courriel ») est reconnu : la
        // liste exportée se réimporte telle quelle.
        $export = "Nom;Adresse de courriel;Note\nDavid;david@example.com;11\n";
        $this->assertCount(1, local\grades_importer::read($export, $participants, $users, $graded)->rows);

        // Fichier enregistré par Excel en Windows-1252.
        $latin = \core_text::convert("Courriel;Note\nchloe@example.com;14\n", 'utf-8', 'windows-1252');
        $this->assertSame(14.0, local\grades_importer::read($latin, $participants, $users, $graded)->rows[0]->grade);
        $this->assertSame(12.0, local\grades_importer::parse_number(' 12,00 '));
        $this->assertNull(local\grades_importer::parse_number('douze'));
    }
}
