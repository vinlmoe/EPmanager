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
 * Propositions d'EP et fiches : la fiche de chaque EP est une activité « Syllabus du module »
 * (mod_syllabus), gérée par l'EP ; proposition par un enseignant, soumission, décision de la DEVE
 * et état de l'EP, mise à jour d'un EP publié, reprise des anciennes fiches, avis des étudiants
 * limités aux inscrits, courriels.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_create_proposal
 * @covers     ::ep_ensure_activity_syllabus
 * @covers     ::ep_migrate_legacy_syllabus
 * @covers     ::ep_sync_syllabus_teachers
 * @covers     ::ep_sync_syllabus_details
 * @covers     ::ep_apply_syllabus_decision
 * @covers     ::ep_get_syllabus
 * @covers     ::ep_get_published_syllabi
 * @covers     ::ep_publish_syllabus_directly
 * @covers     ::ep_delete_activity
 * @covers     ::ep_syllabus_hours
 * @covers     ::ep_update_proposal_activity
 * @covers     ::ep_can_edit_syllabus
 * @covers     ::ep_get_proposals
 * @covers     ::mod_ep_syllabus_eligible_users
 * @covers     ::mod_ep_syllabus_review_required
 * @covers     ::mod_ep_syllabus_review_exempt
 * @covers     \mod_ep\observer
 * @covers     \mod_ep\task\migrate_syllabi
 * @covers     ::ep_send_notification
 * @covers     ::ep_get_notification_template
 * @covers     ::ep_set_notification_template
 * @covers     ::ep_render_template
 * @covers     ::ep_notify_credit_decided
 * @covers     ::ep_send_pending_reminders
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

    /** @var \stdClass Membre de la DEVE (gestionnaire). */
    private $deve;

    /** @var \mod_ep_generator */
    private $gen;

    /**
     * Un cours, une activité, deux enseignants et un membre de la DEVE.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 2]);
        $this->context = \context_module::instance($this->ep->cmid);
        $this->teacher = $gen->create_and_enrol($course, 'teacher');
        $this->other = $gen->create_and_enrol($course, 'teacher');
        $this->deve = $gen->create_and_enrol($course, 'manager');
        $this->gen = $gen->get_plugin_generator('mod_ep');
        $this->setUser($this->teacher);
    }

    /**
     * Crée une proposition de l'enseignant.
     *
     * @return \stdClass L'EP proposé.
     */
    private function proposal(): \stdClass {
        global $DB;
        $academic = ep_get_type_by_code($this->ep->id, EP_TYPE_ACADEMIC);
        $created = ep_create_proposal($this->ep, (object) [
            'name' => 'Échographie abdominale', 'typeid' => $academic->id, 'ects' => 2, 'capacity' => 12,
        ], $this->teacher->id);
        return $DB->get_record('ep_activity', ['id' => $created->activityid], '*', MUST_EXIST);
    }

    /**
     * Une proposition crée l'EP en brouillon, son auteur responsable, et sa fiche : une activité
     * « Syllabus du module » de la section des fiches, gérée par l'EP, dont l'auteur est
     * responsable ; elle n'est créée qu'une fois.
     */
    public function test_proposal_creates_a_syllabus_activity(): void {
        global $DB;
        $activity = $this->proposal();
        $this->assertSame(EP_ACTIVITY_DRAFT, $activity->state);
        $this->assertEquals($this->teacher->id, $activity->proposedby);
        $syllabus = ep_activity_syllabus_record($activity->id);
        $this->assertNotNull($syllabus);
        $this->assertSame(['mod_ep', (int) $activity->id], [$syllabus->component, (int) $syllabus->itemid]);
        $this->assertEquals($syllabus->cmid, $activity->syllabuscmid);
        $this->assertSame('Échographie abdominale', $syllabus->name);
        $this->assertArrayHasKey($this->teacher->id, syllabus_get_teachers((int) $syllabus->id));
        $section = $DB->get_field_sql('SELECT cs.name FROM {course_sections} cs JOIN {course_modules} cm ON cm.section = cs.id
                                        WHERE cm.id = ?', [$syllabus->cmid]);
        $this->assertSame(get_string('syllabussection', 'mod_ep'), $section);
        $this->assertEquals($syllabus->id, ep_ensure_activity_syllabus($this->ep, $activity)->id);
        $this->assertSame(1, $DB->count_records('syllabus', ['component' => 'mod_ep']));
        $this->assertTrue(ep_can_edit_syllabus($activity, $this->context, $this->teacher->id));
        $this->assertFalse(ep_can_edit_syllabus($activity, $this->context, $this->other->id));
        $this->assertSame(1, count(ep_get_proposals($this->ep, $this->context, $this->teacher->id)));
    }

    /**
     * Circuit d'un nouvel EP : soumise, la fiche fait passer l'EP à « proposé » ; renvoyée, il
     * redevient brouillon ; publiée, il entre au catalogue avec sa fiche en vigueur.
     */
    public function test_submit_return_and_publish(): void {
        global $DB;
        $activity = $this->proposal();
        $this->gen->write_syllabus($activity, (int) $this->teacher->id, ['objectives' => 'Lire une échographie',
            'personalhours' => 4], [['title' => 'Cours', 'kind' => 'lecture', 'duration' => 2],
            ['title' => 'TP', 'kind' => 'practical', 'duration' => 3]]);
        $this->assertSame('submitted', $this->gen->submit_syllabus($activity, (int) $this->teacher->id));
        $this->assertSame(EP_ACTIVITY_PROPOSED, $DB->get_field('ep_activity', 'state', ['id' => $activity->id]));
        $this->assertCount(1, ep_get_proposals($this->ep, $this->context, $this->deve->id));

        $this->assertTrue($this->gen->review_syllabus($activity, (int) $this->deve->id, 'return', 'Préciser'));
        $this->assertSame(EP_ACTIVITY_DRAFT, $DB->get_field('ep_activity', 'state', ['id' => $activity->id]));
        $this->assertCount(0, ep_get_proposals($this->ep, $this->context, $this->deve->id));

        $this->gen->write_syllabus($activity, (int) $this->teacher->id, ['objectives' => 'Lire et interpréter']);
        $this->gen->submit_syllabus($activity, (int) $this->teacher->id);
        $this->assertTrue($this->gen->review_syllabus($activity, (int) $this->deve->id, 'publish'));
        $activity = $DB->get_record('ep_activity', ['id' => $activity->id]);
        $this->assertSame(EP_ACTIVITY_PUBLISHED, $activity->state);
        $this->assertGreaterThan(0, (int) $activity->timepublished);

        $published = ep_get_syllabus($activity->id);
        $this->assertSame('Lire et interpréter', $published->objectives);
        $this->assertEquals($activity->id, $published->activityid);
        $hours = ep_syllabus_hours(array_values(ep_get_syllabus_sessions($published->id)), $published->personalhours);
        $this->assertEquals([5, 4, 9], [$hours->sessions, $hours->personal, $hours->total]);
        $this->assertArrayHasKey($activity->id, ep_get_published_syllabi([$activity->id]));
    }

    /**
     * Un nouvel EP passe toujours par la DEVE, même sans relecture des mises à jour ; la mise à
     * jour d'un EP publié suit le réglage commun, sauf publication directe choisie pour l'activité.
     */
    public function test_review_rules(): void {
        global $DB;
        syllabus_save_settings((object) ['reviewrequired' => 0]);
        $activity = $this->proposal();
        $this->gen->write_syllabus($activity, (int) $this->teacher->id, ['objectives' => 'A']);
        $this->assertSame('submitted', $this->gen->submit_syllabus($activity, (int) $this->teacher->id));
        $this->gen->review_syllabus($activity, (int) $this->deve->id, 'publish');

        $this->gen->write_syllabus($activity, (int) $this->teacher->id, ['objectives' => 'B']);
        $this->assertSame('published', $this->gen->submit_syllabus($activity, (int) $this->teacher->id));

        syllabus_save_settings((object) ['reviewrequired' => 1]);
        $this->gen->write_syllabus($activity, (int) $this->teacher->id, ['objectives' => 'C']);
        $this->assertSame('submitted', $this->gen->submit_syllabus($activity, (int) $this->teacher->id));
        $this->gen->review_syllabus($activity, (int) $this->deve->id, 'publish');

        $DB->set_field('ep', 'proposalsdirectpublish', 1, ['id' => $this->ep->id]);
        $this->gen->write_syllabus($activity, (int) $this->teacher->id, ['objectives' => 'D']);
        $this->assertSame('published', $this->gen->submit_syllabus($activity, (int) $this->teacher->id));
        $this->assertSame('D', ep_get_syllabus($activity->id)->objectives);
        $this->assertSame(EP_ACTIVITY_PUBLISHED, $DB->get_field('ep_activity', 'state', ['id' => $activity->id]));
    }

    /**
     * Refusée, une proposition est archivée ; un enseignant ne voit que ses propositions.
     */
    public function test_reject_and_proposal_lists(): void {
        global $DB;
        $activity = $this->proposal();
        $this->gen->write_syllabus($activity, (int) $this->teacher->id, ['objectives' => 'A']);
        $this->gen->submit_syllabus($activity, (int) $this->teacher->id);
        $this->assertCount(0, ep_get_proposals($this->ep, $this->context, $this->other->id));
        $this->assertCount(1, ep_get_proposals($this->ep, $this->context, $this->teacher->id));
        $this->gen->review_syllabus($activity, (int) $this->deve->id, 'reject', 'Hors programme');
        $this->assertSame(EP_ACTIVITY_ARCHIVED, $DB->get_field('ep_activity', 'state', ['id' => $activity->id]));
    }

    /**
     * Caractéristiques et responsables de l'EP reportés sur sa fiche.
     */
    public function test_details_and_teachers_follow_the_unit(): void {
        global $DB;
        $activity = $this->proposal();
        ep_save_activity($this->ep, (object) ['id' => $activity->id, 'name' => 'Échographie', 'code' => 'ECHO', 'ects' => 3]);
        $syllabus = ep_activity_syllabus_record($activity->id);
        $this->assertSame(['Échographie', 'ECHO', 3.0], [$syllabus->name, $syllabus->code, (float) $syllabus->ects]);
        ep_set_activity_teachers($activity->id, [$this->other->id]);
        $this->assertSame([(int) $this->other->id], array_map('intval', array_keys(syllabus_get_teachers((int) $syllabus->id))));
        $this->assertEquals($activity->id, $DB->get_field('syllabus', 'itemid', ['id' => $syllabus->id]));
    }

    /**
     * Avis des étudiants sur un EP : seuls ses inscrits sont appelés à répondre.
     */
    public function test_feedback_is_for_registered_students(): void {
        global $CFG, $DB;
        $activity = $this->gen->create_activity($this->ep, ['name' => 'Chirurgie']);
        $course = get_course($this->ep->course);
        $registered = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $notregistered = $this->getDataGenerator()->create_and_enrol($course, 'student');
        ep_register_to_activity($this->ep, $activity, $registered->id, 2);
        $syllabus = ep_ensure_activity_syllabus($this->ep, $activity);
        $DB->set_field('syllabus', 'evalopen', time() - 60, ['id' => $syllabus->id]);
        $syllabus = ep_activity_syllabus_record($activity->id);
        $context = \context_module::instance($syllabus->cmid);
        $this->assertNull(syllabus_evaluation_problem($syllabus, $context, (int) $registered->id));
        $this->assertSame('evalnotallowed', syllabus_evaluation_problem($syllabus, $context, (int) $notregistered->id));
        $this->assertSame(1, syllabus_count_eligible($context));

        // « Mes modules » : la fiche de l'EP n'y figure que pour ses inscrits.
        require_once($CFG->dirroot . '/mod/syllabusstudent/locallib.php');
        $this->assertArrayHasKey($syllabus->id, syllabusstudent_get_modules((int) $registered->id, 0));
        $this->assertArrayNotHasKey($syllabus->id, syllabusstudent_get_modules((int) $notregistered->id, 0));
    }

    /**
     * Une fiche rédigée avant la version 1.1 (tables ep_syllabus et ep_session) est reprise dans
     * l'activité syllabus de l'EP, avec ses versions et ses séances, par la tâche de reprise.
     */
    public function test_legacy_syllabus_is_migrated(): void {
        global $DB;
        $activity = $this->gen->create_activity($this->ep, ['name' => 'Parasitologie']);
        $now = time();
        $base = ['activityid' => $activity->id, 'objectives' => 'Ancien', 'outcomes' => '', 'prerequisitestext' => '',
            'content' => '', 'methods' => '', 'assessment' => '', 'resit' => '', 'bibliography' => '', 'language' => 'fr',
            'personalhours' => 2, 'authorid' => $this->teacher->id, 'timesubmitted' => $now, 'timereviewed' => $now,
            'timecreated' => $now, 'timemodified' => $now];
        $DB->insert_record('ep_syllabus', (object) ($base + ['version' => 1, 'status' => 'superseded']));
        $published = $DB->insert_record('ep_syllabus', (object) (['objectives' => 'En vigueur', 'version' => 2,
            'status' => 'published'] + $base));
        $DB->insert_record('ep_session', (object) ['syllabusid' => $published, 'sortorder' => 10, 'title' => 'Cours',
            'kind' => 'lecture', 'duration' => 2, 'teacherid' => null, 'teachername' => 'Dr X', 'timecreated' => $now]);

        $this->setAdminUser();
        ob_start();
        (new \mod_ep\task\migrate_syllabi())->execute();
        ob_end_clean();

        $this->assertSame(0, $DB->count_records('ep_syllabus', ['activityid' => $activity->id]));
        $version = ep_get_syllabus($activity->id);
        $this->assertSame(['En vigueur', 2], [$version->objectives, (int) $version->version]);
        $sessions = array_values(ep_get_syllabus_sessions($version->id));
        $this->assertSame(['Cours', 'Dr X'], [$sessions[0]->title, $sessions[0]->teachername]);
        $syllabusid = ep_activity_syllabus_record($activity->id)->id;
        $this->assertSame(2, $DB->count_records('syllabus_version', ['syllabusid' => $syllabusid]));
    }

    /**
     * Supprimer un EP retire sa fiche, ses responsables et les prérequis qui le citent ; un EP
     * auquel un crédit se rattache reste.
     */
    public function test_delete_activity_leaves_no_orphans(): void {
        global $DB;
        $activity = $this->proposal();
        $syllabus = ep_activity_syllabus_record($activity->id);
        $other = $this->proposal();
        $DB->insert_record('ep_activity_prereq', (object) ['activityid' => $other->id, 'requiredid' => $activity->id,
            'timecreated' => time()]);

        $this->assertTrue(ep_delete_activity($this->ep, $activity));
        $this->assertFalse($DB->record_exists('ep_activity', ['id' => $activity->id]));
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $syllabus->cmid, 'deletioninprogress' => 0]));
        $this->assertNull(ep_activity_syllabus_record($activity->id));
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
