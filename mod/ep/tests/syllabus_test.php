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
 * Propositions d'EP et syllabus : proposition par un enseignant, soumission, décision de la DEVE,
 * mise à jour d'un EP publié, séances et volume horaire, courriels.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_create_proposal
 * @covers     ::ep_delete_activity
 * @covers     ::ep_start_syllabus_revision
 * @covers     ::ep_save_syllabus
 * @covers     ::ep_set_syllabus_sessions
 * @covers     ::ep_syllabus_hours
 * @covers     ::ep_submit_syllabus
 * @covers     ::ep_review_syllabus
 * @covers     ::ep_update_proposal_activity
 * @covers     ::ep_can_edit_syllabus
 * @covers     ::ep_get_proposals
 * @covers     ::ep_send_notification
 * @covers     ::ep_get_notification_template
 * @covers     ::ep_set_notification_template
 * @covers     ::ep_render_template
 * @covers     ::ep_notify_credit_decided
 * @covers     ::ep_send_pending_reminders
 * @covers     \mod_ep\event\syllabus_submitted
 * @covers     \mod_ep\event\syllabus_reviewed
 */
final class syllabus_test extends \advanced_testcase {
    /** @var \stdClass Instance. */
    private $ep;

    /** @var \context_module */
    private $context;

    /** @var \stdClass Enseignant qui propose. */
    private $teacher;

    /** @var \stdClass Autre enseignant. */
    private $other;

    /** @var \stdClass Membre de la DEVE (enseignant éditeur). */
    private $deve;

    /**
     * Un cours, une activité, deux enseignants et un membre de la DEVE.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 2]);
        $this->context = \context_module::instance($this->ep->cmid);
        $this->teacher = $gen->create_and_enrol($course, 'teacher');
        $this->other = $gen->create_and_enrol($course, 'teacher');
        $this->deve = $gen->create_and_enrol($course, 'editingteacher');
    }

    /**
     * Crée une proposition rédigée par l'enseignant.
     *
     * @return array [stdClass $activity, stdClass $syllabus]
     */
    private function proposal(): array {
        global $DB;
        $academic = ep_get_type_by_code($this->ep->id, EP_TYPE_ACADEMIC);
        $created = ep_create_proposal($this->ep, (object) [
            'name' => 'Échographie abdominale', 'typeid' => $academic->id, 'ects' => 2, 'capacity' => 12,
        ], $this->teacher->id);
        $activity = $DB->get_record('ep_activity', ['id' => $created->activityid], '*', MUST_EXIST);
        $syllabus = $DB->get_record('ep_syllabus', ['id' => $created->syllabusid], '*', MUST_EXIST);
        return [$activity, $syllabus];
    }

    /**
     * Supprimer une proposition retire aussi ses versions de syllabus, leurs séances, ses
     * responsables et les prérequis qui la citent ; un EP auquel un crédit se rattache reste.
     */
    public function test_delete_activity_leaves_no_orphans(): void {
        global $DB;
        [$activity, $syllabus] = $this->proposal();
        ep_set_syllabus_sessions($syllabus->id, [['title' => 'Cours', 'kind' => 'lecture', 'duration' => 2]]);
        [$other] = $this->proposal();
        $DB->insert_record('ep_activity_prereq', (object) ['activityid' => $other->id, 'requiredid' => $activity->id,
            'timecreated' => time()]);

        $this->assertTrue(ep_delete_activity($this->ep, $activity));
        $this->assertFalse($DB->record_exists('ep_activity', ['id' => $activity->id]));
        $this->assertFalse($DB->record_exists('ep_syllabus', ['activityid' => $activity->id]));
        $this->assertFalse($DB->record_exists('ep_session', ['syllabusid' => $syllabus->id]));
        $this->assertFalse($DB->record_exists('ep_activity_teacher', ['activityid' => $activity->id]));
        $this->assertFalse($DB->record_exists('ep_activity_prereq', ['requiredid' => $activity->id]));

        $student = $this->getDataGenerator()->create_and_enrol(get_course($this->ep->course), 'student');
        ep_create_credit(
            $this->ep,
            $student->id,
            ep_get_type_by_code($this->ep->id, EP_TYPE_ACADEMIC),
            ['activityid' => $other->id, 'name' => $other->name, 'claimedects' => 2]
        );
        $this->assertFalse(ep_delete_activity($this->ep, $other));
        $this->assertTrue($DB->record_exists('ep_activity', ['id' => $other->id]));
    }

    /**
     * Relit une version de syllabus.
     *
     * @param int $id
     * @return \stdClass
     */
    private function syllabus(int $id): \stdClass {
        global $DB;
        return $DB->get_record('ep_syllabus', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Proposition : un EP en brouillon dont l'auteur est responsable, absent du catalogue des
     * étudiants, avec une première version de syllabus modifiable par lui seul (et la DEVE).
     */
    public function test_proposal_creates_a_draft_unit_owned_by_its_author(): void {
        [$activity, $syllabus] = $this->proposal();
        $this->assertSame(EP_ACTIVITY_DRAFT, $activity->state);
        $this->assertSame((int) $this->teacher->id, (int) $activity->proposedby);
        $this->assertTrue(ep_is_activity_teacher($activity->id, $this->teacher->id));
        $this->assertArrayNotHasKey($activity->id, ep_get_activities($this->ep->id, true));
        $this->assertSame('draft', $syllabus->status);
        $this->assertSame(1, (int) $syllabus->version);

        $this->assertTrue(ep_can_edit_syllabus($activity, $this->context, $this->teacher->id));
        $this->assertFalse(ep_can_edit_syllabus($activity, $this->context, $this->other->id));
        $this->assertTrue(ep_can_edit_syllabus($activity, $this->context, $this->deve->id));

        // Un type qui ne passe pas par le catalogue est refusé.
        $this->expectException(\moodle_exception::class);
        ep_create_proposal($this->ep, (object) [
            'name' => 'X', 'typeid' => ep_get_type_by_code($this->ep->id, EP_TYPE_SPORT)->id,
        ], $this->teacher->id);
    }

    /**
     * Rédaction : texte et séances enregistrés, volume horaire calculé ; une version soumise ne se
     * modifie plus.
     */
    public function test_syllabus_writing_and_hours(): void {
        [$activity, $syllabus] = $this->proposal();
        $sessions = [
            ['title' => 'Bases physiques', 'kind' => 'lecture', 'duration' => 2, 'teacherid' => $this->teacher->id,
                'teachername' => 'ignoré'],
            ['title' => 'Manipulation', 'kind' => 'practical', 'duration' => 3.5, 'teachername' => 'Dr Martin'],
            ['title' => '', 'kind' => 'lecture', 'duration' => 9],
            ['title' => 'Cas cliniques', 'kind' => 'bogus', 'duration' => -1],
        ];
        $this->assertTrue(ep_save_syllabus($syllabus, (object) [
            'objectives' => 'Savoir réaliser une échographie.', 'outcomes' => 'Identifier les organes.',
            'assessment' => 'Épreuve pratique notée sur 20.', 'language' => 'Français', 'personalhours' => 10,
        ], $sessions));
        $saved = ep_get_syllabus_sessions($syllabus->id);
        $this->assertCount(3, $saved);
        $saved = array_values($saved);
        $this->assertSame('', $saved[0]->teachername);
        $this->assertSame('Dr Martin', $saved[1]->teachername);
        $this->assertSame('other', $saved[2]->kind);
        $this->assertSame(0.0, (float) $saved[2]->duration);

        $hours = ep_syllabus_hours($saved, $this->syllabus($syllabus->id)->personalhours);
        $this->assertSame(5.5, $hours->sessions);
        $this->assertSame(15.5, $hours->total);
        $this->assertSame(3.5, $hours->bykind['practical']);

        $submitted = ep_submit_syllabus($this->ep, $activity, $this->syllabus($syllabus->id), $this->teacher->id);
        $this->assertSame('submitted', $submitted);
        $this->assertFalse(ep_save_syllabus($this->syllabus($syllabus->id), (object) ['objectives' => 'Modifié'], []));
        $this->assertSame('Savoir réaliser une échographie.', $this->syllabus($syllabus->id)->objectives);
        $this->assertCount(3, ep_get_syllabus_sessions($syllabus->id));
    }

    /**
     * Soumission puis décision de la DEVE : renvoi (retour en brouillon, commentaire), nouvelle
     * soumission, publication (l'EP entre au catalogue) ; la DEVE est prévenue de la soumission,
     * l'auteur de chaque décision ; une seconde décision sur la même version n'est pas écrite.
     */
    public function test_submit_return_and_publish(): void {
        global $DB;
        [$activity, $syllabus] = $this->proposal();
        $sink = $this->redirectMessages();
        $events = $this->redirectEvents();

        $this->assertSame('submitted', ep_submit_syllabus($this->ep, $activity, $syllabus, $this->teacher->id));
        $this->assertSame(EP_ACTIVITY_PROPOSED, $DB->get_field('ep_activity', 'state', ['id' => $activity->id]));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame((int) $this->deve->id, (int) $messages[0]->useridto);
        $this->assertStringContainsString('Échographie abdominale', $messages[0]->subject);
        $this->assertNull(ep_submit_syllabus($this->ep, $activity, $this->syllabus($syllabus->id), $this->teacher->id));

        $activity = $DB->get_record('ep_activity', ['id' => $activity->id]);
        $this->assertTrue(ep_review_syllabus(
            $this->ep,
            $activity,
            $this->syllabus($syllabus->id),
            $this->deve->id,
            'return',
            'Préciser les conditions d\'examen.'
        ));
        $this->assertSame(EP_ACTIVITY_DRAFT, $DB->get_field('ep_activity', 'state', ['id' => $activity->id]));
        $returned = $this->syllabus($syllabus->id);
        $this->assertSame('returned', $returned->status);
        $this->assertSame('Préciser les conditions d\'examen.', $returned->reviewcomment);
        $this->assertTrue(ep_save_syllabus($returned, (object) ['assessment' => 'Oral de 20 minutes.']));

        $activity = $DB->get_record('ep_activity', ['id' => $activity->id]);
        ep_submit_syllabus($this->ep, $activity, $this->syllabus($syllabus->id), $this->teacher->id);
        $activity = $DB->get_record('ep_activity', ['id' => $activity->id]);
        $this->assertTrue(ep_review_syllabus($this->ep, $activity, $this->syllabus($syllabus->id), $this->deve->id, 'publish'));
        $this->assertFalse(ep_review_syllabus($this->ep, $activity, $this->syllabus($syllabus->id), $this->deve->id, 'reject'));

        $activity = $DB->get_record('ep_activity', ['id' => $activity->id]);
        $this->assertSame(EP_ACTIVITY_PUBLISHED, $activity->state);
        $this->assertGreaterThan(0, (int) $activity->timepublished);
        $this->assertSame('published', $this->syllabus($syllabus->id)->status);
        $this->assertSame($syllabus->id, ep_get_syllabus($activity->id)->id);

        $messages = $sink->get_messages();
        $tostudent = array_filter($messages, fn($m) => (int) $m->useridto === (int) $this->teacher->id);
        $this->assertCount(2, $tostudent);
        $classes = array_map('get_class', $events->get_events());
        $this->assertContains(event\syllabus_submitted::class, $classes);
        $this->assertContains(event\syllabus_reviewed::class, $classes);
        $sink->close();
        $events->close();
    }

    /**
     * Mise à jour d'un EP publié : la version en vigueur reste au catalogue jusqu'à la publication
     * de la nouvelle, qui la remplace ; une seule révision à la fois. Avec la publication directe,
     * la mise à jour du responsable est publiée aussitôt.
     */
    public function test_revision_of_a_published_unit(): void {
        global $DB;
        [$activity, $syllabus] = $this->proposal();
        ep_save_syllabus($syllabus, (object) ['objectives' => 'V1'], [['title' => 'Séance 1', 'duration' => 2]]);
        ep_submit_syllabus($this->ep, $activity, $this->syllabus($syllabus->id), $this->teacher->id);
        $activity = $DB->get_record('ep_activity', ['id' => $activity->id]);
        ep_review_syllabus($this->ep, $activity, $this->syllabus($syllabus->id), $this->deve->id, 'publish');
        $activity = $DB->get_record('ep_activity', ['id' => $activity->id]);

        $revision = ep_start_syllabus_revision($activity, $this->teacher->id);
        $this->assertSame(2, (int) $revision->version);
        $this->assertSame('V1', $revision->objectives);
        $this->assertCount(1, ep_get_syllabus_sessions($revision->id));
        $this->assertEquals($revision->id, ep_start_syllabus_revision($activity, $this->teacher->id)->id);
        ep_save_syllabus($revision, (object) ['objectives' => 'V2']);
        $this->assertSame('V1', ep_get_syllabus($activity->id)->objectives);

        // Les caractéristiques d'un EP publié ne se changent plus par la proposition.
        $this->assertFalse(ep_update_proposal_activity($this->ep, $activity, (object) ['ects' => 6]));

        ep_submit_syllabus($this->ep, $activity, $this->syllabus($revision->id), $this->teacher->id);
        ep_review_syllabus($this->ep, $activity, $this->syllabus($revision->id), $this->deve->id, 'publish');
        $this->assertSame('V2', ep_get_syllabus($activity->id)->objectives);
        $this->assertSame('superseded', $this->syllabus($syllabus->id)->status);
        $this->assertSame(EP_ACTIVITY_PUBLISHED, $DB->get_field('ep_activity', 'state', ['id' => $activity->id]));

        // Publication directe.
        $DB->set_field('ep', 'proposalsdirectpublish', 1, ['id' => $this->ep->id]);
        $ep = $DB->get_record('ep', ['id' => $this->ep->id]);
        $third = ep_start_syllabus_revision($activity, $this->teacher->id);
        ep_save_syllabus($third, (object) ['objectives' => 'V3']);
        $this->assertSame('published', ep_submit_syllabus($ep, $activity, $this->syllabus($third->id), $this->teacher->id));
        $this->assertSame('V3', ep_get_syllabus($activity->id)->objectives);
    }

    /**
     * Refus d'une proposition : l'EP est archivé ; les propositions listées dépendent du rôle.
     */
    public function test_reject_and_proposal_lists(): void {
        global $DB;
        [$activity, $syllabus] = $this->proposal();
        [$second] = $this->proposal();
        ep_submit_syllabus($this->ep, $activity, $syllabus, $this->teacher->id);

        $this->assertCount(2, ep_get_proposals($this->ep, $this->context, $this->teacher->id));
        $this->assertCount(0, ep_get_proposals($this->ep, $this->context, $this->other->id));
        // La DEVE ne voit que ce qui lui est soumis.
        $deve = ep_get_proposals($this->ep, $this->context, $this->deve->id);
        $this->assertCount(1, $deve);
        $this->assertSame((int) $activity->id, (int) $deve[0]->activity->id);

        $activity = $DB->get_record('ep_activity', ['id' => $activity->id]);
        ep_review_syllabus($this->ep, $activity, $this->syllabus($syllabus->id), $this->deve->id, 'reject', 'Hors maquette.');
        $this->assertSame(EP_ACTIVITY_ARCHIVED, $DB->get_field('ep_activity', 'state', ['id' => $activity->id]));
        $this->assertSame('rejected', $this->syllabus($syllabus->id)->status);
        $this->assertTrue(ep_update_proposal_activity($this->ep, $second, (object) ['ects' => 4, 'name' => 'Renommé']));
        $this->assertEquals(4, $DB->get_field('ep_activity', 'ects', ['id' => $second->id]));
    }

    /**
     * Courriels : modèle par défaut ou personnalisé, variables remplacées, désactivation ;
     * l'étudiant est prévenu de la décision finale, pas d'une étape intermédiaire.
     */
    public function test_notifications(): void {
        global $DB;
        $this->assertSame('a {x} b', ep_render_template('{y} {x} {z}', ['y' => 'a', 'z' => 'b']));
        $default = ep_get_notification_template($this->ep->id, 'creditdecided');
        $this->assertFalse($default->custom);
        $this->assertStringContainsString('{ep}', $default->subject);

        ep_set_notification_template($this->ep->id, 'creditdecided', true, 'Décision : {ep}', 'Bonjour {student}.');
        $custom = ep_get_notification_template($this->ep->id, 'creditdecided');
        $this->assertTrue($custom->custom);
        $this->assertSame('Décision : {ep}', $custom->subject);

        $student = $this->getDataGenerator()->create_and_enrol(get_course($this->ep->course), 'student');
        $engagement = ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT);
        $this->getDataGenerator()->get_plugin_generator('mod_ep')->configure_type($this->ep, EP_TYPE_ENGAGEMENT, [
            'validationflow' => 'referent,deve',
        ]);
        $engagement = ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT);
        $creditid = ep_create_credit($this->ep, $student->id, $engagement, ['name' => 'Tutorat', 'claimedects' => 1]);

        $sink = $this->redirectMessages();
        $credit = $DB->get_record('ep_credit', ['id' => $creditid]);
        ep_validate_credit($credit, $this->teacher->id, 1, '', EP_ROLE_REFERENT);
        $this->assertCount(0, $sink->get_messages());
        ep_validate_credit($DB->get_record('ep_credit', ['id' => $creditid]), $this->deve->id, 1, 'Bravo', EP_ROLE_DEVE);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Décision : Tutorat', $messages[0]->subject);
        $this->assertStringContainsString(fullname($student), $messages[0]->fullmessage);

        ep_set_notification_template($this->ep->id, 'creditdecided', false, '', '');
        $second = ep_create_credit($this->ep, $student->id, $engagement, ['name' => 'BDE', 'claimedects' => 1]);
        ep_reject_credit($DB->get_record('ep_credit', ['id' => $second]), $this->deve->id, 'Non', EP_ROLE_DEVE);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Relance : les responsables d'EP reçoivent le compte des inscriptions qui les attendent
     * depuis plus d'une semaine.
     */
    public function test_pending_reminders(): void {
        global $DB;
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_ep');
        $activity = $gen->create_activity($this->ep, ['name' => 'Chirurgie']);
        ep_set_activity_teachers($activity->id, [$this->teacher->id]);
        $student = $this->getDataGenerator()->create_and_enrol(get_course($this->ep->course), 'student');
        $creditid = ep_register_to_activity($this->ep, $activity, $student->id, 2)->creditid;

        $sink = $this->redirectMessages();
        $this->assertSame(0, ep_send_pending_reminders($this->ep));
        $DB->set_field('ep_credit', 'timecreated', time() - 8 * DAYSECS, ['id' => $creditid]);
        $this->assertSame(1, ep_send_pending_reminders($this->ep));
        $messages = $sink->get_messages();
        $this->assertSame((int) $this->teacher->id, (int) $messages[0]->useridto);
        $this->assertStringContainsString('1', $messages[0]->subject);
        $sink->close();
    }
}
